<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Event\StoreEventRequest;
use App\Http\Requests\Admin\Event\UpdateEventRequest;
use App\Models\Event;
use App\Models\ZooZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $type = request('type');
        $status = request('status');
        $zoneId = request('zoo_zone_id');

        $events = Event::query()
            ->with('zone:id,name')
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%");
                });
            })
            ->when(
                $type,
                fn ($query, $type) => $query->where('type', $type)
            )
            ->when(
                $status === 'active',
                fn ($query) => $query->where('is_active', true)
            )
            ->when(
                $status === 'inactive',
                fn ($query) => $query->where('is_active', false)
            )
            ->when(
                $zoneId,
                fn ($query, $zoneId) => $query->where('zoo_zone_id', $zoneId)
            )
            ->orderBy('start_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render(
            'admin/events/Index',
            [
                'events' => $events,

                'zones' => ZooZone::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                    ]),

                'types' => Event::query()
                    ->whereNotNull('type')
                    ->where('type', '!=', '')
                    ->distinct()
                    ->orderBy('type')
                    ->pluck('type'),

                'filters' => [
                    'search' => $search,
                    'type' => $type,
                    'status' => $status,
                    'zoo_zone_id' => $zoneId,
                ],
            ]
        );
    }

    public function create(): Response
    {
        return Inertia::render(
            'admin/events/Create',
            [
                'zones' => ZooZone::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                    ]),
            ]
        );
    }

    public function store(
        StoreEventRequest $request
    ): RedirectResponse {
        $validated = $request->validated();

        $imagePath = null;

        if (
            $request->hasFile('image') &&
            $request->file('image')->isValid()
        ) {
            $imagePath = $this->moveUploadedFile(
                $request->file('image'),
                'events'
            );
        }

        Event::create([
            'name' => $validated['name'],

            'slug' => $this->generateUniqueSlug(
                $validated['name']
            ),

            'description' => $validated['description'] ?? null,

            'type' => $validated['type'] ?? null,

            'start_at' => $validated['start_at'],

            'end_at' => $validated['end_at'] ?? null,

            'zoo_zone_id' => $validated['zoo_zone_id'] ?? null,

            'image' => $imagePath,

            'capacity' => $validated['capacity'] ?? null,

            'is_featured' => $request->boolean('is_featured'),

            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.events.index')
            ->with(
                'success',
                'Evento creado correctamente.'
            );
    }

    public function edit(Event $event): Response
    {
        $event->load('zone');

        return Inertia::render(
            'admin/events/Edit',
            [
                'event' => $event,

                'zones' => ZooZone::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                    ]),
            ]
        );
    }

    public function update(
        UpdateEventRequest $request,
        Event $event
    ): RedirectResponse {
        $validated = $request->validated();

        $data = [
            'name' => $validated['name'],

            'slug' => $this->generateUniqueSlug(
                $validated['name'],
                $event->id
            ),

            'description' => $validated['description'] ?? null,

            'type' => $validated['type'] ?? null,

            'start_at' => $validated['start_at'],

            'end_at' => $validated['end_at'] ?? null,

            'zoo_zone_id' => $validated['zoo_zone_id'] ?? null,

            'capacity' => $validated['capacity'] ?? null,

            'is_featured' => $request->boolean('is_featured'),

            'is_active' => $request->boolean('is_active'),
        ];

        if (
            $request->hasFile('image') &&
            $request->file('image')->isValid()
        ) {
            if (
                $event->image &&
                Storage::disk('public')->exists($event->image)
            ) {
                Storage::disk('public')->delete(
                    $event->image
                );
            }

            $data['image'] =
                $this->moveUploadedFile(
                    $request->file('image'),
                    'events'
                );
        }

        $event->update($data);

        return redirect()
            ->route('admin.events.index')
            ->with(
                'success',
                'Evento actualizado correctamente.'
            );
    }

    public function destroy(Event $event): RedirectResponse
    {
        if (
            $event->image &&
            Storage::disk('public')->exists($event->image)
        ) {
            Storage::disk('public')->delete(
                $event->image
            );
        }

        $event->delete();

        return redirect()
            ->route('admin.events.index')
            ->with(
                'success',
                'Evento eliminado correctamente.'
            );
    }

    private function generateUniqueSlug(
        string $name,
        ?int $ignoreId = null
    ): string {
        $slug = Str::slug($name);

        $originalSlug = $slug;
        $counter = 1;

        while (
            Event::query()
                ->where('slug', $slug)
                ->when(
                    $ignoreId,
                    fn ($query) => $query->where('id', '!=', $ignoreId)
                )
                ->exists()
        ) {
            $slug = $originalSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function moveUploadedFile(
        UploadedFile $file,
        string $directory
    ): string {
        $directory = trim(
            $directory,
            '/'
        );

        $destination = storage_path(
            'app/public/'.$directory
        );

        File::ensureDirectoryExists(
            $destination
        );

        $extension =
            $file->getClientOriginalExtension();

        $filename =
            uniqid('', true).'.'.$extension;

        $file->move(
            $destination,
            $filename
        );

        return $directory.'/'.$filename;
    }
}
