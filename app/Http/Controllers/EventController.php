<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\EventCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

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
}