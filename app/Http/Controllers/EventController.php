<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\EventCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use App\Enums\EventStatusEnum;
use App\Enums\EventTypeEnum;
use Illuminate\Validation\Rule;
use App\Services\EventCancellationService;


class EventController extends Controller
{
    public function index(
        Request $request,
        EventCacheService $cache
    ): AnonymousResourceCollection {
        $search = $request->string('search')->trim()->value();
        $city = $request->string('city')->trim()->value();
        $type = $request->string('type')->trim()->value();
        $upcoming = $request->boolean('upcoming');

        $perPage = min((int) $request->input('per_page', 15), 50);
        $perPage = max($perPage, 1);

        $page = max((int) $request->input('page', 1), 1);

        $cacheIdentifier = 'listing:' . sha1(json_encode([
            'search' => $search,
            'city' => $city,
            'type' => $type,
            'upcoming' => $upcoming,
            'per_page' => $perPage,
            'page' => $page,
        ]));

        $events = Cache::remember(
            $cache->key($cacheIdentifier),
            now()->addMinutes(5),
            function () use ($search, $city, $type, $upcoming, $perPage) {
                $query = Event::query()
                    ->published()
                    ->with(['venue.city', 'ticketTypes']);

                if ($search) {
                    $query->search($search);
                }

                if ($city) {
                    $query->inCity($city);
                }

                if ($type) {
                    $query->ofType($type);
                }

                if ($upcoming) {
                    $query->upcoming();
                }

                return $query
                    ->orderBy('starts_at')
                    ->paginate($perPage)
                    ->withQueryString();
            }
        );

        return EventResource::collection($events);
    }

    public function show(
        int $id,
        EventCacheService $cache
    ): EventResource|JsonResponse {
        $event = Cache::remember(
            $cache->key("event:{$id}"),
            now()->addMinutes(5),
            fn () => Event::published()
                ->with(['venue.city', 'ticketTypes'])
                ->find($id)
        );

        if ($event === null) {
            return response()->json([
                'message' => 'Event not found.',
            ], 404);
        }

        return EventResource::make($event);
    }

    public function store(Request $request): EventResource
{
    $this->authorize('create', Event::class);

    $data = $request->validate([
        'venue_id' => ['required', 'exists:venues,id'],
        'title' => ['required', 'string', 'max:255'],
        'description' => ['nullable', 'string'],
        'event_type' => ['required', Rule::enum(EventTypeEnum::class)],
        'starts_at' => ['required', 'date', 'after:now'],
        'ends_at' => ['required', 'date', 'after:starts_at'],
    ]);

    $event = Event::create([
        ...$data,
        'organizer_id' => $request->user()->id,
        'status' => EventStatusEnum::Draft,
    ]);

    return EventResource::make($event->load(['venue.city', 'ticketTypes']));
}

public function update(Request $request, Event $event): EventResource
{
    $this->authorize('update', $event);

    $data = $request->validate([
        'venue_id' => ['required', 'exists:venues,id'],
        'title' => ['required', 'string', 'max:255'],
        'description' => ['nullable', 'string'],
        'event_type' => ['required', Rule::enum(EventTypeEnum::class)],
        'starts_at' => ['required', 'date', 'after:now'],
        'ends_at' => ['required', 'date', 'after:starts_at'],
    ]);

    $event->update($data);

    return EventResource::make($event->load(['venue.city', 'ticketTypes']));
}

public function destroy(Event $event): JsonResponse
{
    $this->authorize('delete', $event);

    if ($event->ticketTypes()->exists()) {
        return response()->json([
            'message' => 'Cannot delete an event that has ticket types.',
        ], 409);
    }

    $event->delete();

    return response()->json([
        'message' => 'Event deleted successfully.',
    ]);
}

public function cancel(
    Event $event,
    EventCancellationService $service
): JsonResponse {
    $this->authorize('update', $event);

    $event = $service->cancel($event);

    return response()->json([
        'message' => 'Event cancelled successfully.',
        'data' => EventResource::make(
            $event->load(['venue.city', 'ticketTypes'])
        ),
    ]);
}
}