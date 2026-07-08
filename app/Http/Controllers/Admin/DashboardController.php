<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
        ]);

        $tenantId = $validated['tenant_id'] ?? null;
        $ordersQuery = Order::query()->when(
            $tenantId,
            fn ($query) => $query->where('tenant_id', $tenantId)
        );

        $recentWindowStart = now()->subMonth();
        $trendWindowStart = now()->startOfDay()->subDays(6);

        $recentBookings = (clone $ordersQuery)
            ->where('created_at', '>=', $recentWindowStart)
            ->with(['user', 'passengers'])
            ->latest()
            ->limit(10)
            ->get();

        $trendRows = (clone $ordersQuery)
            ->selectRaw('DATE(created_at) as booking_date')
            ->selectRaw('COUNT(*) as bookings')
            ->selectRaw('SUM(CASE WHEN cancellation_status = ? THEN 1 ELSE 0 END) as cancellations', [Order::CANCELLATION_STATUS_REQUESTED])
            ->selectRaw('SUM(total_amount) as revenue')
            ->where('created_at', '>=', $trendWindowStart)
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at)')
            ->get()
            ->keyBy('booking_date');

        $trend = collect(range(0, 6))->map(function (int $offset) use ($trendRows, $trendWindowStart) {
            $date = $trendWindowStart->copy()->addDays($offset);
            $key = $date->toDateString();
            $row = $trendRows->get($key);

            return [
                'date' => $key,
                'label' => $date->format('D'),
                'bookings' => (int) ($row->bookings ?? 0),
                'cancellations' => (int) ($row->cancellations ?? 0),
                'revenue' => number_format((float) ($row->revenue ?? 0), 2, '.', ''),
            ];
        })->values();

        return response()->json([
            'ok' => true,
            'data' => [
                'tenant' => [
                    'id' => $tenantId,
                    'key' => 'platform',
                    'name' => 'Platform',
                ],
                'stats' => [
                    'total_bookings' => (clone $ordersQuery)->count(),
                    'active_bookings' => (clone $ordersQuery)
                        ->where('cancellation_status', '!=', Order::CANCELLATION_STATUS_CANCELLED)
                        ->where(function ($query) {
                            $query->whereNull('refund_status')
                                ->orWhere('refund_status', '!=', Order::REFUND_STATUS_REFUNDED);
                        })
                        ->count(),
                    'cancellation_requests' => (clone $ordersQuery)
                        ->where('cancellation_status', Order::CANCELLATION_STATUS_REQUESTED)
                        ->count(),
                    'refund_pendings' => (clone $ordersQuery)
                        ->where('refund_status', Order::REFUND_STATUS_PENDING)
                        ->count(),
                    'reschedule_requests' => (clone $ordersQuery)
                        ->where('meta->change->status', 'requested')
                        ->count(),
                    'customers' => (clone $ordersQuery)
                        ->whereNotNull('user_id')
                        ->distinct('user_id')
                        ->count('user_id'),
                    'agencies' => Tenant::count(),
                    'revenue' => [
                        'amount' => number_format((float) ((clone $ordersQuery)->sum('total_amount')), 2, '.', ''),
                        'currency' => config('finance.default_currency', 'LKR'),
                    ],
                ],
                'recent_bookings' => OrderResource::collection($recentBookings)->resolve(),
                'recent_bookings_window' => [
                    'from' => $recentWindowStart->toISOString(),
                    'to' => now()->toISOString(),
                    'limit' => 10,
                ],
                'trend' => [
                    'from' => $trendWindowStart->toDateString(),
                    'to' => now()->toDateString(),
                    'points' => $trend,
                ],
            ],
        ]);
    }
}
