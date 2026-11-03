<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventController extends Controller
{
    
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Event::query()
            ->published()
            ->with(['venue.city', 'ticketTypes']);

      
        if ($search = $request->string('search')->trim()->value()) {
            $query->search($search);
        }

        if ($city = $request->string('city')->trim()->value()) {
            $query->inCity($city);
        }

        if ($type = $request->string('type')->trim()->value()) {
            $query->ofType($type);
        }

        if ($request->boolean('upcoming')) {
            $query->upcoming();
        }

       
        $query->orderBy('starts_at');

       
        $perPage = min((int) $request->input('per_page', 15), 50);
        $perPage = max($perPage, 1);

        $events = $query->paginate($perPage)->withQueryString();

        return EventResource::collection($events);
    }

    
    public function show(int $id): EventResource|JsonResponse
    {
        $event = Event::published()
            ->with(['venue.city', 'ticketTypes'])
            ->find($id);

        if ($event === null) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        return EventResource::make($event);
    }
}
