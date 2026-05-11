<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Order Reference</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111827;
            font-size: 13px;
            line-height: 1.5;
        }

        .header {
            border-bottom: 2px solid #2563eb;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }

        .title {
            font-size: 26px;
            font-weight: bold;
            margin: 0;
            color: #111827;
        }

        .subtitle {
            color: #6b7280;
            margin-top: 4px;
        }

        .section {
            margin-bottom: 24px;
        }

        .section-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #111827;
        }

        .box {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 14px;
            background: #f9fafb;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f3f4f6;
            color: #374151;
            text-align: left;
            padding: 8px;
            border: 1px solid #e5e7eb;
        }

        td {
            padding: 8px;
            border: 1px solid #e5e7eb;
        }

        .label {
            color: #6b7280;
            width: 35%;
        }

        .value {
            font-weight: bold;
        }

        .total {
            font-size: 18px;
            font-weight: bold;
            color: #2563eb;
        }

        .footer {
            margin-top: 40px;
            color: #6b7280;
            font-size: 11px;
            border-top: 1px solid #e5e7eb;
            padding-top: 12px;
        }
    </style>
</head>

<body>

    <div class="header">
        <h1 class="title">Flight Booking Reference</h1>
        <div class="subtitle">{{ $tenant }} | Issued on {{ now()->format('d M Y, h:i A') }}</div>
    </div>

    <div class="section">
        <div class="section-title">Booking Summary</div>

        <table>
            <tr>
                <td class="label">Booking Reference</td>
                <td class="value">{{ $order->booking_reference ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Duffel Order ID</td>
                <td class="value">{{ $order->duffel_order_id ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Order Status</td>
                <td class="value">{{ ucfirst($order->status ?? 'created') }}</td>
            </tr>
            <tr>
                <td class="label">Order Type</td>
                <td class="value">{{ ucfirst($order->type ?? 'instant') }}</td>
            </tr>
            <tr>
                <td class="label">Void Window Ends</td>
                <td class="value">
                    {{ $order->void_window_ends_at ? \Carbon\Carbon::parse($order->void_window_ends_at)->format('d M Y, h:i A') : 'N/A' }}
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Passenger Details</div>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Passenger</th>
                    <th>Type</th>
                    <th>Date of Birth</th>
                    <th>Gender</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->passengers as $index => $passenger)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>
                            {{ $passenger->title }}
                            {{ $passenger->given_name }}
                            {{ $passenger->family_name }}
                        </td>
                        <td>{{ ucfirst(str_replace('_', ' ', $passenger->type)) }}</td>
                        <td>{{ $passenger->dob }}</td>
                        <td>{{ ucfirst($passenger->gender) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @php
        $offer = $order->meta['offer'] ?? [];
        $slices = $offer['slices'] ?? [];
    @endphp

    @if (!empty($slices))
        <div class="section">
            <div class="section-title">Flight Itinerary</div>

            @foreach ($slices as $slice)
                @foreach (($slice['segments'] ?? []) as $segment)
                    <div class="box" style="margin-bottom:10px;">
                        <strong>
                            {{ $segment['origin']['iata_code'] ?? 'N/A' }}
                            →
                            {{ $segment['destination']['iata_code'] ?? 'N/A' }}
                        </strong>

                        <br>

                        Airline:
                        {{ $segment['marketing_carrier']['name'] ?? 'N/A' }}

                        <br>

                        Flight:
                        {{ $segment['marketing_carrier_flight_number'] ?? 'N/A' }}

                        <br>

                        Departure:
                        {{ isset($segment['departing_at']) ? \Carbon\Carbon::parse($segment['departing_at'])->format('d M Y, h:i A') : 'N/A' }}

                        <br>

                        Arrival:
                        {{ isset($segment['arriving_at']) ? \Carbon\Carbon::parse($segment['arriving_at'])->format('d M Y, h:i A') : 'N/A' }}
                    </div>
                @endforeach
            @endforeach
        </div>
    @endif

    <div class="section">
        <div class="section-title">Payment Summary</div>

        <table>
            <tr>
                <td class="label">Base Amount</td>
                <td class="value">{{ $order->base_currency }} {{ $order->base_amount }}</td>
            </tr>
            <tr>
                <td class="label">Tax Amount</td>
                <td class="value">{{ $order->tax_currency }} {{ $order->tax_amount }}</td>
            </tr>
            <tr>
                <td class="label">Total Amount</td>
                <td class="total">{{ $order->total_currency }} {{ $order->total_amount }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        This document is an order reference for your flight booking.
        Please keep it for future viewing, cancellation, or support requests.
        <br>
        © {{ date('Y') }} {{ $tenant }}. All rights reserved.
    </div>

</body>
</html>