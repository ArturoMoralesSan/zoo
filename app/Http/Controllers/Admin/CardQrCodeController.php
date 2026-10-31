<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Card\StoreCardQrCodeRequest;
use App\Models\Card;
use App\Models\CardQrCode;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CardQrCodeController extends Controller
{
    /**
     * Códigos QR de una tarjeta.
     */
    public function index(Card $card): Response
    {
        $qrCodes = $card->qrCodes()
            ->when(
                request('search'),
                fn ($query, $search) => $query->where(
                    'label',
                    'like',
                    "%{$search}%"
                )
            )
            ->when(
                request('is_active') !== null &&
                request('is_active') !== '',
                fn ($query) => $query->where(
                    'is_active',
                    filter_var(
                        request('is_active'),
                        FILTER_VALIDATE_BOOLEAN
                    )
                )
            )
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (CardQrCode $qrCode) => $qrCode->makeVisible('token'));

        return Inertia::render(
            'admin/card-qr-codes/Index',
            [
                'card' => $this->cardSummary($card),

                'qrCodes' => $qrCodes,

                'filters' => [
                    'search' => request('search'),
                    'is_active' => request('is_active'),
                ],
            ]
        );
    }

    /**
     * Formulario para crear.
     */
    public function create(Card $card): Response
    {
        return Inertia::render(
            'admin/card-qr-codes/Create',
            [
                'card' => $this->cardSummary($card),
            ]
        );
    }

    /**
     * Guardar código QR.
     */
    public function store(
        StoreCardQrCodeRequest $request,
        Card $card
    ): RedirectResponse {
        $card->qrCodes()->create(
            $request->qrCodeAttributes()
        );

        $this->flashSuccess('Código QR creado correctamente.');

        return redirect()->route('admin.cards.qr-codes.index', $card);
    }

    /**
     * Formulario para editar.
     */
    public function edit(
        Card $card,
        CardQrCode $qrCode
    ): Response {
        return Inertia::render(
            'admin/card-qr-codes/Edit',
            [
                'card' => $this->cardSummary($card),
                'qrCode' => $qrCode->makeVisible('token'),
            ]
        );
    }

    /**
     * Actualizar código QR.
     */
    public function update(
        StoreCardQrCodeRequest $request,
        Card $card,
        CardQrCode $qrCode
    ): RedirectResponse {
        $qrCode->update(
            $request->qrCodeAttributes()
        );

        $this->flashSuccess('Código QR actualizado correctamente.');

        return redirect()->route('admin.cards.qr-codes.index', $card);
    }

    /**
     * Generar un token nuevo; los QR impresos anteriormente dejan de funcionar.
     */
    public function regenerate(
        Card $card,
        CardQrCode $qrCode
    ): RedirectResponse {
        $qrCode->regenerateToken();

        $this->flashSuccess('Código QR regenerado. Descarga e imprime el nuevo QR.');

        return back();
    }

    /**
     * Eliminar código QR.
     */
    public function destroy(
        Card $card,
        CardQrCode $qrCode
    ): RedirectResponse {
        $qrCode->delete();

        $this->flashSuccess('Código QR eliminado correctamente.');

        return redirect()->route('admin.cards.qr-codes.index', $card);
    }

    /**
     * Datos de la tarjeta para las pantallas de códigos QR.
     *
     * @return array{id: int, name: string, species: array{common_name: string}|null}
     */
    private function cardSummary(Card $card): array
    {
        return [
            ...$card->only('id', 'name'),
            'species' => $card->species?->only('common_name'),
        ];
    }

    /**
     * Mensaje de éxito (toast) para la siguiente página.
     */
    private function flashSuccess(string $message): void
    {
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $message,
        ]);
    }
}
