<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TicketType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TicketTypeController extends Controller
{
    public function index(Request $request)
    {
        $ticketTypes = TicketType::query()
            ->when(
                $request->search,
                function ($query, $search) {
                    $query->where(function ($q) use ($search) {
                        $q->where(
                            'name',
                            'like',
                            "%{$search}%"
                        )
                            ->orWhere(
                                'description',
                                'like',
                                "%{$search}%"
                            );
                    });
                }
            )
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render(
            'admin/ticket-types/Index',
            [
                'ticketTypes' => $ticketTypes,
                'filters' => [
                    'search' => $request->search,
                ],
            ]
        );
    }

    public function create()
    {
        return Inertia::render(
            'admin/ticket-types/Create'
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                'unique:ticket_types,name',
            ],
            'price' => [
                'required',
                'numeric',
                'min:0',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'is_active' => [
                'boolean',
            ],
        ]);

        TicketType::create($validated);

        return redirect()
            ->route('admin.ticket-types.index')
            ->with(
                'success',
                'Tipo de boleto creado correctamente.'
            );
    }

    public function edit(TicketType $ticketType)
    {
        return Inertia::render(
            'admin/ticket-types/Edit',
            [
                'ticketType' => $ticketType,
            ]
        );
    }

    public function update(
        Request $request,
        TicketType $ticketType
    ) {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique(
                    'ticket_types',
                    'name'
                )->ignore($ticketType->id),
            ],
            'price' => [
                'required',
                'numeric',
                'min:0',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'is_active' => [
                'boolean',
            ],
        ]);

        $ticketType->update($validated);

        return redirect()
            ->route('admin.ticket-types.index')
            ->with(
                'success',
                'Tipo de boleto actualizado correctamente.'
            );
    }

    public function destroy(TicketType $ticketType)
    {
        if ($ticketType->tickets()->exists()) {
            return redirect()
                ->back()
                ->with(
                    'error',
                    'No se puede eliminar este tipo de boleto porque tiene boletos asociados.'
                );
        }

        $ticketType->delete();

        return redirect()
            ->route('admin.ticket-types.index')
            ->with(
                'success',
                'Tipo de boleto eliminado correctamente.'
            );
    }
}