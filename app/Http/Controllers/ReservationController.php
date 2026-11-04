<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReservationController extends Controller
{
    public function __construct(private readonly ReservationService $service) {}

    public function store(StoreReservationRequest $request): JsonResponse
    {
        $reservation = $this->service->create(
            $request->user(),
            $request->validated('items'),
        );

        return response()->json(
            ['data' => ReservationResource::make($reservation)],
            201,
        );
    }

  
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Reservation::class);

        $query = Reservation::query()
            ->with(['reservationItems', 'tickets'])
            ->latest();

        if ($request->user()->role->value === 'buyer') {
            $query->forBuyer($request->user()->id);
        }

        $reservations = $query->paginate(15)->withQueryString();

        return ReservationResource::collection($reservations);
    }

  
    public function show(Request $request, Reservation $reservation): JsonResponse
    {
        $this->authorize('view', $reservation);

        $reservation->load(['reservationItems', 'tickets']);

        return response()->json(
            ['data' => ReservationResource::make($reservation)],
        );
    }
}
