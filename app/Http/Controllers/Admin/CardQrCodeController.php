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
                'card' => $card->only('id', 'name'),

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
                'card' => $card->only('id', 'name'),
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

        return redirect()
            ->route('admin.cards.qr-codes.index', $card)
            ->with(
                'success',
                'Código QR creado correctamente.'
            );
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
                'card' => $card->only('id', 'name'),
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

        return redirect()
            ->route('admin.cards.qr-codes.index', $card)
            ->with(
                'success',
                'Código QR actualizado correctamente.'
            );
    }

    /**
     * Generar un token nuevo; los QR impresos anteriormente dejan de funcionar.
     */
    public function regenerate(
        Card $card,
        CardQrCode $qrCode
    ): RedirectResponse {
        $qrCode->regenerateToken();

        return back()->with(
            'success',
            'Código QR regenerado. Descarga e imprime el nuevo QR.'
        );
    }

    /**
     * Eliminar código QR.
     */
    public function destroy(
        Card $card,
        CardQrCode $qrCode
    ): RedirectResponse {
        $qrCode->delete();

        return redirect()
            ->route('admin.cards.qr-codes.index', $card)
            ->with(
                'success',
                'Código QR eliminado correctamente.'
            );
    }
}
