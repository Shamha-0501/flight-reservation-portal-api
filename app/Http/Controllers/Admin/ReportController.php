<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\ActivityLog;
use App\Models\BookingAddon;
use App\Support\RoleCatalog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        return $this->report($request);
    }

    public function platform(Request $request)
    {
        return $this->report($request, 'platform');
    }

    public function tenant(Request $request)
    {
        return $this->report($request, 'tenant');
    }

    private function report(Request $request, ?string $expectedScope = null)
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'group_by' => ['nullable', 'in:week,month,year'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'booking_status' => ['nullable', 'string', 'max:40'],
            'cancellation_status' => ['nullable', 'string', 'max:40'],
            'refund_status' => ['nullable', 'string', 'max:40'],
            'airline' => ['nullable', 'string', 'max:3'],
            'route' => ['nullable', 'string', 'max:20'],
        ]);
        $scope = $this->resolveReportScope($request, $expectedScope);

        $payload = $this->buildReportPayload(
            Carbon::parse($validated['from'])->startOfDay(),
            Carbon::parse($validated['to'])->endOfDay(),
            $validated['group_by'] ?? 'month',
            $scope,
            $this->normalizeFilters($validated, $scope)
        );

        return response()->json([
            'ok' => true,
            'data' => $payload,
        ]);
    }

    public function export(Request $request)
    {
        return $this->exportReport($request);
    }

    public function exportPlatform(Request $request)
    {
        return $this->exportReport($request, 'platform');
    }

    public function exportTenant(Request $request)
    {
        return $this->exportReport($request, 'tenant');
    }

    private function exportReport(Request $request, ?string $expectedScope = null)
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'group_by' => ['nullable', 'in:week,month,year'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'booking_status' => ['nullable', 'string', 'max:40'],
            'cancellation_status' => ['nullable', 'string', 'max:40'],
            'refund_status' => ['nullable', 'string', 'max:40'],
            'airline' => ['nullable', 'string', 'max:3'],
            'route' => ['nullable', 'string', 'max:20'],
            'format' => ['required', 'in:csv,pdf'],
        ]);
        $scope = $this->resolveReportScope($request, $expectedScope);

        $payload = $this->buildReportPayload(
            Carbon::parse($validated['from'])->startOfDay(),
            Carbon::parse($validated['to'])->endOfDay(),
            $validated['group_by'] ?? 'month',
            $scope,
            $this->normalizeFilters($validated, $scope)
        );

        $filenameBase = 'admin-reports-' . Carbon::parse($validated['from'])->toDateString() . '-to-' . Carbon::parse($validated['to'])->toDateString();

        if ($validated['format'] === 'csv') {
            $csv = $this->buildCsv($payload);

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filenameBase . '.csv"',
            ]);
        }

        $pdf = Pdf::loadHTML($this->buildPdfHtml($payload));

        return $pdf->download($filenameBase . '.pdf');
    }

    /**
     * Tenant scope is derived exclusively from the authenticated active membership.
     * Frontend input must never decide which tenant's records are included.
     *
     * @return array{kind: 'platform'|'tenant', tenant_id: ?int, name: string}
     */
    private function resolveReportScope(Request $request, ?string $expectedScope = null): array
    {
        $user = $request->user();
        $role = $user?->primaryRoleKey();

        if (RoleCatalog::isPlatformRole($role)) {
            $scope = ['kind' => 'platform', 'tenant_id' => null, 'name' => 'Flight Portal'];
            if ($expectedScope === null || $expectedScope === $scope['kind']) {
                return $scope;
            }
        }

        if (RoleCatalog::isTenantRole($role)) {
            $tenant = $user?->primaryTenant();
            if ($tenant?->isActive()) {
                $scope = ['kind' => 'tenant', 'tenant_id' => $tenant->id, 'name' => $tenant->name];
                if ($expectedScope === null || $expectedScope === $scope['kind']) {
                    return $scope;
                }
            }
        }

        throw new AuthorizationException('You are not authorized to access reports.');
    }

    private function buildReportPayload(Carbon $from, Carbon $to, string $groupBy, array $scope, array $filters = [], bool $includeComparison = true): array
    {
        $ordersQuery = Order::query()->whereBetween('created_at', [$from, $to]);

        $this->applyOrderFilters($ordersQuery, $scope, $filters);

        /** @var Collection<int, Order> $orders */
        $orders = $this->applyOfferFilters($ordersQuery->with(['tenant', 'user:id,name,email'])->get(), $filters);

        $revenue = (float) $orders->sum('total_amount');
        $bookingVolume = $orders->count();
        $cancellationCount = $orders->where('cancellation_status', Order::CANCELLATION_STATUS_REQUESTED)->count();
        $refundTotals = (float) $orders
            ->whereIn('refund_status', [Order::REFUND_STATUS_PENDING, Order::REFUND_STATUS_REFUNDED])
            ->sum('total_amount');
        $customerCounts = $orders->whereNotNull('user_id')->pluck('user_id')->unique()->count();

        $series = $this->buildSeries($orders, $from, $to, $groupBy);
        $topAgencies = $scope['kind'] === 'platform' ? $this->buildTopAgencies($orders) : [];
        $airlinesAndRoutes = $this->buildAirlinesAndRoutes($orders);
        $systemActivity = $scope['kind'] === 'platform'
            ? $this->buildSystemActivity($from, $to)
            : null;
        $operations = $this->buildOperations($orders);
        $customers = $this->buildCustomers($orders);
        $addonUsage = $this->buildAddonUsage($orders);

        $payload = [
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'group_by' => $groupBy,
            ],
            'scope' => [
                'kind' => $scope['kind'],
                'name' => $scope['name'],
            ],
            'summary' => [
                'revenue' => [
                    'amount' => number_format($revenue, 2, '.', ''),
                    'currency' => config('finance.default_currency', 'LKR'),
                ],
                'booking_volume' => $bookingVolume,
                'cancellation_rate' => $bookingVolume > 0 ? round(($cancellationCount / $bookingVolume) * 100, 1) : 0,
                'refund_totals' => [
                    'amount' => number_format($refundTotals, 2, '.', ''),
                    'currency' => config('finance.default_currency', 'LKR'),
                ],
                'customer_counts' => $customerCounts,
                'top_agencies' => $topAgencies,
            ],
            'series' => $series,
            'airlines_and_routes' => $airlinesAndRoutes,
            'system_activity' => $systemActivity,
            'operations' => $operations,
            'customers' => $customers,
            'addon_usage' => $addonUsage,
            'filters' => $filters,
            'filter_options' => $this->buildFilterOptions($orders, $scope),
        ];

        if ($includeComparison) {
            $days = $from->diffInDays($to) + 1;
            $previousTo = $from->copy()->subDay()->endOfDay();
            $previousFrom = $previousTo->copy()->subDays($days - 1)->startOfDay();
            $previous = $this->buildReportPayload($previousFrom, $previousTo, $groupBy, $scope, $filters, false);
            $payload['comparison'] = $this->buildComparison($payload['summary'], $previous['summary']);
        }

        return $payload;
    }

    private function normalizeFilters(array $validated, array $scope): array
    {
        if ($scope['kind'] !== 'platform' && !empty($validated['tenant_id'])) {
            throw new AuthorizationException('Tenant reports cannot select another tenant.');
        }

        return array_filter([
            'tenant_id' => $scope['kind'] === 'platform' ? ($validated['tenant_id'] ?? null) : null,
            'booking_status' => $validated['booking_status'] ?? null,
            'cancellation_status' => $validated['cancellation_status'] ?? null,
            'refund_status' => $validated['refund_status'] ?? null,
            'airline' => isset($validated['airline']) ? strtoupper($validated['airline']) : null,
            'route' => isset($validated['route']) ? strtoupper($validated['route']) : null,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    private function applyOrderFilters($query, array $scope, array $filters): void
    {
        $tenantId = $scope['tenant_id'] ?? ($filters['tenant_id'] ?? null);
        if ($tenantId) $query->where('tenant_id', $tenantId);
        foreach (['booking_status' => 'status', 'cancellation_status' => 'cancellation_status', 'refund_status' => 'refund_status'] as $filter => $column) {
            if (!empty($filters[$filter])) $query->where($column, $filters[$filter]);
        }
    }

    private function applyOfferFilters(Collection $orders, array $filters): Collection
    {
        if (empty($filters['airline']) && empty($filters['route'])) return $orders;
        return $orders->filter(function (Order $order) use ($filters) {
            $dimensions = $this->extractFlightDimensions($order);
            return (empty($filters['airline']) || ($dimensions['airline']['key'] ?? null) === $filters['airline'])
                && (empty($filters['route']) || $dimensions['route'] === $filters['route']);
        })->values();
    }

    private function buildComparison(array $current, array $previous): array
    {
        return [
            'previous_range' => $previous,
            'growth' => [
                'booking_volume' => $this->growth($current['booking_volume'], $previous['booking_volume']),
                'revenue' => $this->growth((float) $current['revenue']['amount'], (float) $previous['revenue']['amount']),
                'customer_counts' => $this->growth($current['customer_counts'], $previous['customer_counts']),
                'refund_totals' => $this->growth((float) $current['refund_totals']['amount'], (float) $previous['refund_totals']['amount']),
            ],
        ];
    }

    private function growth(float|int $current, float|int $previous): ?float
    {
        return $previous == 0 ? null : round((($current - $previous) / $previous) * 100, 1);
    }

    private function buildFilterOptions(Collection $orders, array $scope): array
    {
        $dimensions = $orders->map(fn (Order $order) => $this->extractFlightDimensions($order));
        return [
            'booking_statuses' => $orders->pluck('status')->filter()->unique()->values()->all(),
            'cancellation_statuses' => $orders->pluck('cancellation_status')->filter()->unique()->values()->all(),
            'refund_statuses' => $orders->pluck('refund_status')->filter()->unique()->values()->all(),
            'airlines' => $dimensions->pluck('airline.key')->filter()->unique()->values()->all(),
            'routes' => $dimensions->pluck('route')->filter()->unique()->values()->all(),
            'tenants' => $scope['kind'] === 'platform' ? Tenant::query()->where('status', 'active')->orderBy('name')->get(['id', 'name'])->map(fn (Tenant $tenant) => ['id' => $tenant->id, 'name' => $tenant->name])->all() : [],
        ];
    }

    private function buildOperations(Collection $orders): array
    {
        return [
            'pending_cancellations' => $orders->where('cancellation_status', Order::CANCELLATION_STATUS_REQUESTED)->count(),
            'pending_refunds' => $orders->where('refund_status', Order::REFUND_STATUS_PENDING)->count(),
            'rescheduled_orders' => $orders->where('status', Order::STATUS_RESCHEDULED)->count(),
            'refunded_orders' => $orders->where('refund_status', Order::REFUND_STATUS_REFUNDED)->count(),
        ];
    }

    private function buildCustomers(Collection $orders): array
    {
        $customers = $orders
            ->filter(fn (Order $order) => $order->user !== null)
            ->groupBy('user_id')
            ->map(function (Collection $customerOrders) {
                /** @var Order $firstOrder */
                $firstOrder = $customerOrders->first();
                return [
                    'id' => $firstOrder->user_id,
                    'name' => $firstOrder->user?->name,
                    'email' => $firstOrder->user?->email,
                    'bookings' => $customerOrders->count(),
                    'booking_value' => [
                        'amount' => number_format((float) $customerOrders->sum('total_amount'), 2, '.', ''),
                        'currency' => config('finance.default_currency', 'LKR'),
                    ],
                ];
            })
            ->sortByDesc('bookings')
            ->take(5)
            ->values()
            ->all();

        return ['top_customers' => $customers];
    }

    private function buildAddonUsage(Collection $orders): array
    {
        $orderIds = $orders->pluck('id')->all();
        if ($orderIds === []) {
            return ['total_usage' => 0, 'items' => []];
        }

        $items = BookingAddon::query()
            ->whereIn('order_id', $orderIds)
            ->get()
            ->groupBy(fn (BookingAddon $addon) => $addon->addon_code . '|' . $addon->addon_name)
            ->map(function (Collection $addons) {
                /** @var BookingAddon $firstAddon */
                $firstAddon = $addons->first();
                return [
                    'code' => $firstAddon->addon_code,
                    'name' => $firstAddon->addon_name,
                    'usage' => $addons->count(),
                    'value' => [
                        'amount' => number_format((float) $addons->sum('price'), 2, '.', ''),
                        'currency' => $firstAddon->currency,
                    ],
                ];
            })
            ->sortByDesc('usage')
            ->take(8)
            ->values()
            ->all();

        return [
            'total_usage' => array_sum(array_column($items, 'usage')),
            'items' => $items,
        ];
    }

    /**
     * Flight dimensions are read from the persisted Duffel offer snapshot. Orders
     * without that stored snapshot are deliberately excluded from these rankings.
     */
    private function buildAirlinesAndRoutes(Collection $orders): array
    {
        $airlines = [];
        $routes = [];

        foreach ($orders as $order) {
            $dimensions = $this->extractFlightDimensions($order);
            $amount = (float) $order->total_amount;

            if ($dimensions['airline']) {
                $key = $dimensions['airline']['key'];
                $airlines[$key] ??= ['key' => $key, 'name' => $dimensions['airline']['name'], 'bookings' => 0, 'revenue' => 0.0];
                $airlines[$key]['bookings']++;
                $airlines[$key]['revenue'] += $amount;
            }

            if ($dimensions['route']) {
                $key = $dimensions['route'];
                $routes[$key] ??= ['key' => $key, 'name' => $key, 'bookings' => 0, 'revenue' => 0.0];
                $routes[$key]['bookings']++;
                $routes[$key]['revenue'] += $amount;
            }
        }

        $format = function (array $items): array {
            return collect($items)
                ->sortByDesc('bookings')
                ->take(5)
                ->values()
                ->map(fn (array $item) => [
                    'key' => $item['key'],
                    'name' => $item['name'],
                    'bookings' => $item['bookings'],
                    'revenue' => [
                        'amount' => number_format($item['revenue'], 2, '.', ''),
                        'currency' => config('finance.default_currency', 'LKR'),
                    ],
                ])->all();
        };

        return ['top_airlines' => $format($airlines), 'top_routes' => $format($routes)];
    }

    private function extractFlightDimensions(Order $order): array
    {
        $offer = is_array($order->meta) ? ($order->meta['offer'] ?? null) : null;
        $slice = is_array($offer) && is_array($offer['slices'] ?? null) ? ($offer['slices'][0] ?? null) : null;
        if (!is_array($slice)) {
            return ['airline' => null, 'route' => null];
        }

        $origin = $this->iataCode($slice['origin'] ?? null);
        $destination = $this->iataCode($slice['destination'] ?? null);
        $segments = is_array($slice['segments'] ?? null) ? $slice['segments'] : [];
        $firstSegment = $segments[0] ?? null;
        $carrier = is_array($firstSegment) ? ($firstSegment['marketing_carrier'] ?? $firstSegment['operating_carrier'] ?? null) : null;
        $carrierCode = is_array($carrier) ? $this->iataCode($carrier) : null;
        $carrierName = is_array($carrier) && is_string($carrier['name'] ?? null) ? trim($carrier['name']) : null;

        return [
            'airline' => $carrierCode ? ['key' => $carrierCode, 'name' => $carrierName ?: $carrierCode] : null,
            'route' => $origin && $destination ? $origin . ' - ' . $destination : null,
        ];
    }

    private function iataCode(mixed $location): ?string
    {
        if (is_string($location) && preg_match('/^[A-Za-z0-9]{2,3}$/', $location)) {
            return strtoupper($location);
        }

        if (!is_array($location)) {
            return null;
        }

        $code = $location['iata_code'] ?? $location['code'] ?? null;
        return is_string($code) && preg_match('/^[A-Za-z0-9]{2,3}$/', $code) ? strtoupper($code) : null;
    }

    private function buildSystemActivity(Carbon $from, Carbon $to): array
    {
        $totalEvents = ActivityLog::query()
            ->whereBetween('created_at', [$from, $to])
            ->count();
        $activities = ActivityLog::query()
            ->whereBetween('created_at', [$from, $to])
            ->with(['user:id,name', 'tenant:id,name'])
            ->latest()
            ->limit(8)
            ->get();

        $actionCounts = ActivityLog::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderByDesc('count')
            ->limit(6)
            ->get()
            ->map(fn (ActivityLog $activity) => ['action' => $activity->action, 'count' => (int) $activity->count])
            ->all();

        return [
            'total_events' => $totalEvents,
            'top_actions' => $actionCounts,
            'recent_events' => $activities->map(fn (ActivityLog $activity) => [
                'id' => $activity->id,
                'action' => $activity->action,
                'category' => $activity->category,
                'title' => $activity->title,
                'actor_name' => $activity->user?->name,
                'tenant_name' => $activity->tenant?->name,
                'created_at' => $activity->created_at?->toIso8601String(),
            ])->all(),
        ];
    }

    private function buildSeries(Collection $orders, Carbon $from, Carbon $to, string $groupBy): array
    {
        $buckets = [];
        $cursor = $this->normalizeBucketStart($from->copy(), $groupBy);
        $end = $this->normalizeBucketStart($to->copy(), $groupBy);

        while ($cursor->lessThanOrEqualTo($end)) {
            $bucketKey = $this->bucketKey($cursor, $groupBy);
            $bucketOrders = $orders->filter(function (Order $order) use ($bucketKey, $groupBy) {
                return $this->bucketKey(Carbon::parse($order->created_at), $groupBy) === $bucketKey;
            });

            $buckets[] = [
                'label' => $this->bucketLabel($cursor, $groupBy),
                'bookings' => $bucketOrders->count(),
                'cancellations' => $bucketOrders->where('cancellation_status', Order::CANCELLATION_STATUS_REQUESTED)->count(),
                'revenue' => [
                    'amount' => number_format((float) $bucketOrders->sum('total_amount'), 2, '.', ''),
                    'currency' => config('finance.default_currency', 'LKR'),
                ],
            ];

            $cursor = match ($groupBy) {
                'week' => $cursor->copy()->addWeek(),
                'month' => $cursor->copy()->addMonthNoOverflow(),
                'year' => $cursor->copy()->addYear(),
            };
        }

        return $buckets;
    }

    private function buildTopAgencies(Collection $orders): array
    {
        $grouped = $orders
            ->groupBy('tenant_id')
            ->map(function (Collection $tenantOrders, $groupTenantId) {
                return [
                    'tenant_id' => (int) $groupTenantId,
                    'bookings' => $tenantOrders->count(),
                    'revenue' => (float) $tenantOrders->sum('total_amount'),
                ];
            })
            ->sortByDesc('bookings')
            ->take(5)
            ->values();

        $tenants = Tenant::query()
            ->whereIn('id', $grouped->pluck('tenant_id')->all())
            ->get()
            ->keyBy('id');

        return $grouped->map(function (array $row, int $index) use ($tenants) {
            $tenant = $tenants->get($row['tenant_id']);

            return [
                'id' => $tenant?->id ?? $index + 1,
                'key' => $tenant?->key ?? ('tenant-' . $row['tenant_id']),
                'name' => $tenant?->name ?? 'Agency ' . $row['tenant_id'],
                'bookings' => $row['bookings'],
                'revenue' => [
                    'amount' => number_format($row['revenue'], 2, '.', ''),
                    'currency' => config('finance.default_currency', 'LKR'),
                ],
            ];
        })->all();
    }

    private function normalizeBucketStart(Carbon $date, string $groupBy): Carbon
    {
        return match ($groupBy) {
            'week' => $date->startOfWeek(),
            'month' => $date->startOfMonth(),
            'year' => $date->startOfYear(),
        };
    }

    private function bucketKey(Carbon $date, string $groupBy): string
    {
        return match ($groupBy) {
            'week' => $date->copy()->startOfWeek()->toDateString(),
            'month' => $date->copy()->startOfMonth()->toDateString(),
            'year' => $date->copy()->startOfYear()->toDateString(),
        };
    }

    private function bucketLabel(Carbon $date, string $groupBy): string
    {
        return match ($groupBy) {
            'week' => $date->copy()->startOfWeek()->format('M d') . ' - ' . $date->copy()->endOfWeek()->format('M d'),
            'month' => $date->format('M Y'),
            'year' => $date->format('Y'),
        };
    }

    private function buildCsv(array $payload): string
    {
        $lines = [
            ['Report Scope', $payload['scope']['name']],
            ['Date Range', $payload['range']['from'] . ' to ' . $payload['range']['to']],
            ['Grouping', $payload['range']['group_by']],
            ['Applied Filters', $payload['filters'] ? json_encode($payload['filters']) : 'None'],
            [],
            ['Period', 'Bookings', 'Cancellations', 'Revenue'],
        ];

        foreach ($payload['series'] as $row) {
            $lines[] = [
                $row['label'],
                (string) $row['bookings'],
                (string) $row['cancellations'],
                (string) $row['revenue']['amount'] . ' ' . $row['revenue']['currency'],
            ];
        }

        $csv = implode("\n", array_map(function (array $row) {
            return implode(',', array_map(function ($cell) {
                return '"' . str_replace('"', '""', (string) $cell) . '"';
            }, $row));
        }, $lines));

        return $csv;
    }

    private function buildPdfHtml(array $payload): string
    {
        $summary = $payload['summary'];
        $scope = $payload['scope'];
        $range = $payload['range'];
        $comparison = $payload['comparison'] ?? null;
        $rows = '';
        $bars = '';
        $maxBookings = max(1, ...array_map(
            static fn (array $row) => (int) $row['bookings'],
            $payload['series']
        ));

        foreach ($payload['series'] as $row) {
            $rows .= '<tr>'
                . '<td>' . e($row['label']) . '</td>'
                . '<td>' . e((string) $row['bookings']) . '</td>'
                . '<td>' . e((string) $row['cancellations']) . '</td>'
                . '<td>' . e($row['revenue']['amount'] . ' ' . $row['revenue']['currency']) . '</td>'
                . '</tr>';
            $width = min(100, max(0, ((int) $row['bookings'] / $maxBookings) * 100));
            $bars .= '<div class="bar-row"><span>' . e($row['label']) . '</span>'
                . '<span class="bar" style="width:' . e(number_format($width, 2, '.', '')) . '%"></span>'
                . '<strong>' . e((string) $row['bookings']) . '</strong></div>';
        }

        return '<html><head><meta charset="utf-8"><style>'
            . 'body{font-family:DejaVu Sans,sans-serif;font-size:12px;color:#0f172a;}'
            . 'h1{font-size:20px;margin-bottom:12px;}'
            . 'table{width:100%;border-collapse:collapse;margin-top:16px;}'
            . 'th,td{border:1px solid #cbd5e1;padding:8px;text-align:left;}'
            . 'th{background:#eff6ff;}'
            . '.summary{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;}'
            . '.card{border:1px solid #cbd5e1;border-radius:10px;padding:10px;}'
            . '.chart{border:1px solid #cbd5e1;border-radius:10px;padding:10px;margin-top:16px;}'
            . '.bar-row{height:18px;margin:6px 0;white-space:nowrap;}'
            . '.bar-row span:first-child{display:inline-block;width:110px;overflow:hidden;}'
            . '.bar{display:inline-block;height:12px;background:#2563eb;vertical-align:middle;}'
            . '.bar-row strong{margin-left:6px;}'
            . '</style></head><body>'
            . '<h1>' . e($scope['kind'] === 'platform' ? 'Platform Reports' : 'Tenant Reports') . '</h1>'
            . '<p><strong>Scope:</strong> ' . e($scope['name']) . '<br>'
            . '<strong>Date range:</strong> ' . e($range['from'] . ' to ' . $range['to']) . '<br>'
            . '<strong>Grouping:</strong> ' . e(ucfirst($range['group_by'])) . '<br>'
            . '<strong>Applied filters:</strong> ' . e($payload['filters'] ? json_encode($payload['filters']) : 'None') . '<br>'
            . '<strong>Generated:</strong> ' . e(now()->toDateTimeString()) . '</p>'
            . '<div class="summary">'
            . '<div class="card"><strong>Total Revenue</strong><div>' . e($summary['revenue']['amount'] . ' ' . $summary['revenue']['currency']) . '</div></div>'
            . '<div class="card"><strong>Booking Volume</strong><div>' . e((string) $summary['booking_volume']) . '</div></div>'
            . '<div class="card"><strong>Cancellation Rate</strong><div>' . e((string) $summary['cancellation_rate']) . '%</div></div>'
            . '<div class="card"><strong>Refund Totals</strong><div>' . e($summary['refund_totals']['amount'] . ' ' . $summary['refund_totals']['currency']) . '</div></div>'
            . '<div class="card"><strong>Customer Counts</strong><div>' . e((string) $summary['customer_counts']) . '</div></div>'
            . '<div class="card"><strong>Top Agency</strong><div>' . e($summary['top_agencies'][0]['name'] ?? 'N/A') . '</div></div>'
            . '</div>'
            . ($comparison ? '<h2>Previous Period Comparison</h2><p>'
                . 'Bookings: ' . e((string) ($comparison['growth']['booking_volume'] ?? 'N/A')) . '% · '
                . 'Revenue: ' . e((string) ($comparison['growth']['revenue'] ?? 'N/A')) . '% · '
                . 'Customers: ' . e((string) ($comparison['growth']['customer_counts'] ?? 'N/A')) . '%'
                . '</p>' : '')
            . '<h2>Bookings by Period</h2><div class="chart">' . $bars . '</div>'
            . '<table><thead><tr><th>Period</th><th>Bookings</th><th>Cancellations</th><th>Revenue</th></tr></thead><tbody>' . $rows . '</tbody></table>'
            . '</body></html>';
    }
}
