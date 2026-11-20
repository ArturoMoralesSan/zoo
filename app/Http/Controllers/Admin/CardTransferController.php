<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CardTransferStatus;
use App\Enums\CardTransferType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CardTransfer\ConfirmCardImportRequest;
use App\Http\Requests\Admin\CardTransfer\StoreCardExportRequest;
use App\Http\Requests\Admin\CardTransfer\StoreCardImportRequest;
use App\Jobs\AnalyzeCardImportJob;
use App\Jobs\ExportCardsJob;
use App\Jobs\ImportCardsJob;
use App\Models\Card;
use App\Models\CardTransfer;
use App\Models\Species;
use App\Services\CardTransfer\CardTransferSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pestaña "Importar / Exportar" de tarjetas (paquetes .zip).
 *
 * El trabajo pesado corre en la cola; el panel consulta index() hasta que
 * termina. Sin la extensión zip todas las acciones responden 503.
 */
class CardTransferController extends Controller
{
    public function __construct(private CardTransferSupport $support) {}

    /**
     * Estado del servidor y últimos paquetes.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'available' => $this->support->available(),
                'unavailable_message' => $this->support->available() ? null : CardTransferSupport::UNAVAILABLE_MESSAGE,
                'max_upload_bytes' => $this->support->maxUploadBytes(),
                'can_export' => $user->can('cards.export'),
                'can_import' => $user->can('cards.import'),
                'card_count' => Card::count(),
                'species' => Species::query()
                    ->orderBy('common_name')
                    ->get(['id', 'common_name', 'scientific_name']),
                'transfers' => CardTransfer::query()
                    ->with('user:id,name')
                    ->latest('id')
                    ->limit(10)
                    ->get()
                    ->map(fn (CardTransfer $transfer) => $this->present($transfer)),
            ],
        ]);
    }

    public function storeExport(StoreCardExportRequest $request): JsonResponse
    {
        if ($response = $this->unavailable()) {
            return $response;
        }

        if ($this->running(CardTransferType::Export)) {
            return $this->error('Ya se está generando un paquete. Espera a que termine.', 409);
        }

        $transfer = CardTransfer::create([
            'user_id' => $request->user()->id,
            'type' => CardTransferType::Export,
            'status' => CardTransferStatus::Pending,
            'options' => ['include_qr_tokens' => $request->boolean('include_qr_tokens')],
            'original_name' => 'zoodeck-tarjetas-'.now()->format('Y-m-d-His').'.zip',
        ]);

        $transfer->update(['file_path' => CardTransfer::DIRECTORY.'/export-'.$transfer->id.'-'.bin2hex(random_bytes(8)).'.zip']);

        ExportCardsJob::dispatch($transfer);

        return $this->transferResponse($transfer, 201);
    }

    public function download(CardTransfer $transfer): StreamedResponse|JsonResponse
    {
        if ($transfer->type !== CardTransferType::Export
            || $transfer->status !== CardTransferStatus::Completed
            || ! $transfer->hasFile()) {
            return $this->error('El paquete ya no está disponible.', 404);
        }

        return Storage::disk(CardTransfer::DISK)->download(
            (string) $transfer->file_path,
            $transfer->original_name ?? 'zoodeck-tarjetas.zip',
            ['Content-Type' => 'application/zip']
        );
    }

    public function storeImport(StoreCardImportRequest $request): JsonResponse
    {
        if ($response = $this->unavailable()) {
            return $response;
        }

        if ($this->running(CardTransferType::Import)) {
            return $this->error('Ya hay una importación en curso. Espera a que termine.', 409);
        }

        $file = $request->file('package');

        $transfer = CardTransfer::create([
            'user_id' => $request->user()->id,
            'type' => CardTransferType::Import,
            'status' => CardTransferStatus::Pending,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'file_size' => $file->getSize(),
        ]);

        $path = $file->storeAs(
            CardTransfer::DIRECTORY,
            'import-'.$transfer->id.'-'.bin2hex(random_bytes(8)).'.zip',
            CardTransfer::DISK
        );

        if (! $path) {
            $transfer->delete();

            return $this->error('No se pudo guardar el paquete.', 500);
        }

        $transfer->update(['file_path' => $path]);

        AnalyzeCardImportJob::dispatch($transfer);

        return $this->transferResponse($transfer, 201);
    }

    public function confirmImport(ConfirmCardImportRequest $request, CardTransfer $transfer): JsonResponse
    {
        if ($response = $this->unavailable()) {
            return $response;
        }

        if ($transfer->type !== CardTransferType::Import
            || $transfer->status !== CardTransferStatus::AwaitingConfirmation) {
            return $this->error('Este paquete no está listo para importarse.', 409);
        }

        if ($this->running(CardTransferType::Import)) {
            return $this->error('Ya hay una importación en curso. Espera a que termine.', 409);
        }

        $transfer->update([
            // El trabajo la pasa a processing al empezar; así el panel sabe si la cola no avanza.
            'status' => CardTransferStatus::Pending,
            'options' => [
                'mode' => $request->validated('mode'),
                'replace_settings' => $request->boolean('replace_settings'),
                'species_map' => $request->speciesMap(),
            ],
        ]);

        ImportCardsJob::dispatch($transfer);

        return $this->transferResponse($transfer);
    }

    public function destroy(Request $request, CardTransfer $transfer): JsonResponse
    {
        $permission = $transfer->type === CardTransferType::Export ? 'cards.export' : 'cards.import';

        if (! $request->user()->can($permission)) {
            abort(403);
        }

        if ($transfer->status->isRunning()) {
            return $this->error('No se puede borrar mientras se procesa.', 409);
        }

        $transfer->delete();

        return response()->json(['success' => true, 'data' => null]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(CardTransfer $transfer): array
    {
        $options = $transfer->options ?? [];
        unset($options['species_map']);

        return [
            'id' => $transfer->id,
            'type' => $transfer->type->value,
            'status' => $transfer->status->value,
            'options' => $options,
            'original_name' => $transfer->original_name,
            'file_size' => $transfer->file_size,
            'summary' => $transfer->summary,
            'error' => $transfer->error,
            'user' => $transfer->user?->name,
            'created_at' => $transfer->created_at?->toJSON(),
            'updated_at' => $transfer->updated_at?->toJSON(),
            'can_download' => $transfer->type === CardTransferType::Export
                && $transfer->status === CardTransferStatus::Completed
                && $transfer->hasFile(),
        ];
    }

    private function running(CardTransferType $type): bool
    {
        return CardTransfer::query()
            ->where('type', $type)
            ->whereIn('status', [
                CardTransferStatus::Pending,
                CardTransferStatus::Analyzing,
                CardTransferStatus::Processing,
            ])
            // Un trabajo que lleva horas "en curso" murió sin avisar.
            ->where('updated_at', '>', now()->subHours(2))
            ->exists();
    }

    private function unavailable(): ?JsonResponse
    {
        return $this->support->available()
            ? null
            : $this->error(CardTransferSupport::UNAVAILABLE_MESSAGE, 503);
    }

    private function transferResponse(CardTransfer $transfer, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->present($transfer->fresh(['user']) ?? $transfer),
        ], $status);
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
