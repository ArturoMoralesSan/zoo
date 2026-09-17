<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ExploreController extends Controller
{
    public function index(): JsonResponse
    {
        $events = Event::query()
            ->with('zone:id,name')
            ->where('is_active', true)
            ->where('is_featured', true)
            ->orderBy('start_at')
            ->limit(5)
            ->get()
            ->map(function (Event $event) {
                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'slug' => $event->slug,
                    'description' => $event->description,
                    'type' => $event->type,
                    'start_at' => $event->start_at?->toISOString(),
                    'end_at' => $event->end_at?->toISOString(),
                    'image' => $event->image
                        ? url(Storage::url($event->image))
                        : null,
                    'capacity' => $event->capacity,
                    'zone' => $event->zone
                        ? [
                            'id' => $event->zone->id,
                            'name' => $event->zone->name,
                        ]
                        : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'featured_events' => $events,
            ],
        ]);
    }
}