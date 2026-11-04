@php
    // Currency falls back to 'EGP' if not configured in config/app.php.
    // Set config('app.currency') when a payment gateway is integrated.
    $currency = config('app.currency', 'EGP');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Pending Payment</title>
    <style>
        body  { font-family: Arial, sans-serif; color: #333; line-height: 1.6; margin: 0; padding: 0; }
        .wrap { max-width: 600px; margin: 40px auto; padding: 24px; border: 1px solid #e5e5e5; border-radius: 6px; }
        h1    { font-size: 22px; margin-bottom: 4px; }
        .sub  { color: #666; font-size: 14px; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th    { text-align: left; padding: 8px 12px; background: #f8f8f8; border-bottom: 1px solid #e5e5e5; font-size: 13px; }
        td    { padding: 8px 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .notice { background: #fffbeb; border: 1px solid #fcd34d; padding: 12px 16px; border-radius: 4px; font-size: 13px; }
        .footer { margin-top: 32px; font-size: 12px; color: #aaa; }
    </style>
</head>
<body>
<div class="wrap">

    <h1>Your reservation is pending payment</h1>
    <p class="sub">Hi {{ $reservation->buyer->name }}, your seats are being held. Complete payment to secure your tickets.</p>

    <table>
        <tr>
            <th>Field</th>
            <th>Value</th>
        </tr>
        <tr>
            <td>Reservation ID</td>
            <td>#{{ $reservation->id }}</td>
        </tr>
        <tr>
            <td>Status</td>
            <td>{{ ucfirst($reservation->status->value) }}</td>
        </tr>
        <tr>
            <td>Total Amount</td>
            <td>{{ number_format((float) $reservation->total_amount, 2) }} {{ $currency }}</td>
        </tr>
        <tr>
            <td>Pay Before</td>
            <td>
                @if ($reservation->expires_at)
                    {{ $reservation->expires_at->format('d M Y, H:i') }} (UTC)
                @else
                    —
                @endif
            </td>
        </tr>
    </table>

    {{-- Ticket breakdown by type --}}
    @if ($reservation->reservationItems->isNotEmpty())
    <table>
        <tr>
            <th>Ticket Type ID</th>
            <th>Qty</th>
            <th>Unit Price</th>
            <th>Subtotal</th>
        </tr>
        @foreach ($reservation->reservationItems as $item)
        <tr>
            <td>#{{ $item->ticket_type_id }}</td>
            <td>{{ $item->quantity }}</td>
            <td>{{ number_format((float) $item->unit_price, 2) }} {{ $currency }}</td>
            <td>{{ number_format((float) $item->unit_price * $item->quantity, 2) }} {{ $currency }}</td>
        </tr>
        @endforeach
    </table>
    @endif

    <div class="notice">
        @if ($reservation->expires_at)
            ⏳ Your seats are held until {{ $reservation->expires_at->format('H:i') }} (UTC) on {{ $reservation->expires_at->format('d M Y') }}.
        @else
            ⏳ Your seats are temporarily held.
        @endif
        Please complete payment before your reservation expires.
        Ticket codes will be issued after payment is confirmed.
    </div>

    <p class="footer">
        This email was sent by Tazkarti. If you did not make this reservation, please ignore this message.
    </p>

</div>
</body>
</html>
