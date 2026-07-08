<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'group_by' => ['nullable', 'in:day,week,month'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
        ]);

        $payload = $this->buildReportPayload(
            Carbon::parse($validated['from'])->startOfDay(),
            Carbon::parse($validated['to'])->endOfDay(),
            $validated['group_by'] ?? 'month',
            $validated['tenant_id'] ?? null
        );

        return response()->json([
            'ok' => true,
            'data' => $payload,
        ]);
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'group_by' => ['nullable', 'in:day,week,month'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'format' => ['required', 'in:csv,pdf'],
        ]);

        $payload = $this->buildReportPayload(
            Carbon::parse($validated['from'])->startOfDay(),
            Carbon::parse($validated['to'])->endOfDay(),
            $validated['group_by'] ?? 'month',
            $validated['tenant_id'] ?? null
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

    private function buildReportPayload(Carbon $from, Carbon $to, string $groupBy, ?int $tenantId = null): array
    {
        $ordersQuery = Order::query()->whereBetween('created_at', [$from, $to]);

        if ($tenantId) {
            $ordersQuery->where('tenant_id', $tenantId);
        }

        /** @var Collection<int, Order> $orders */
        $orders = $ordersQuery->with(['tenant'])->get();

        $revenue = (float) $orders->sum('total_amount');
        $bookingVolume = $orders->count();
        $cancellationCount = $orders->where('cancellation_status', Order::CANCELLATION_STATUS_REQUESTED)->count();
        $refundTotals = (float) $orders
            ->whereIn('refund_status', [Order::REFUND_STATUS_PENDING, Order::REFUND_STATUS_REFUNDED])
            ->sum('total_amount');
        $customerCounts = $orders->whereNotNull('user_id')->pluck('user_id')->unique()->count();

        $series = $this->buildSeries($orders, $from, $to, $groupBy);
        $topAgencies = $this->buildTopAgencies($orders, $tenantId);

        return [
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'group_by' => $groupBy,
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
                default => $cursor->copy()->addDay(),
            };
        }

        return $buckets;
    }

    private function buildTopAgencies(Collection $orders, ?int $tenantId = null): array
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
            default => $date->startOfDay(),
        };
    }

    private function bucketKey(Carbon $date, string $groupBy): string
    {
        return match ($groupBy) {
            'week' => $date->copy()->startOfWeek()->toDateString(),
            'month' => $date->copy()->startOfMonth()->toDateString(),
            default => $date->toDateString(),
        };
    }

    private function bucketLabel(Carbon $date, string $groupBy): string
    {
        return match ($groupBy) {
            'week' => $date->copy()->startOfWeek()->format('M d') . ' - ' . $date->copy()->endOfWeek()->format('M d'),
            'month' => $date->format('M Y'),
            default => $date->format('M d'),
        };
    }

    private function buildCsv(array $payload): string
    {
        $lines = [
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
        $rows = '';

        foreach ($payload['series'] as $row) {
            $rows .= '<tr>'
                . '<td>' . e($row['label']) . '</td>'
                . '<td>' . e((string) $row['bookings']) . '</td>'
                . '<td>' . e((string) $row['cancellations']) . '</td>'
                . '<td>' . e($row['revenue']['amount'] . ' ' . $row['revenue']['currency']) . '</td>'
                . '</tr>';
        }

        return '<html><head><meta charset="utf-8"><style>'
            . 'body{font-family:DejaVu Sans,sans-serif;font-size:12px;color:#0f172a;}'
            . 'h1{font-size:20px;margin-bottom:12px;}'
            . 'table{width:100%;border-collapse:collapse;margin-top:16px;}'
            . 'th,td{border:1px solid #cbd5e1;padding:8px;text-align:left;}'
            . 'th{background:#eff6ff;}'
            . '.summary{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;}'
            . '.card{border:1px solid #cbd5e1;border-radius:10px;padding:10px;}'
            . '</style></head><body>'
            . '<h1>Admin Reports</h1>'
            . '<div class="summary">'
            . '<div class="card"><strong>Total Revenue</strong><div>' . e($summary['revenue']['amount'] . ' ' . $summary['revenue']['currency']) . '</div></div>'
            . '<div class="card"><strong>Booking Volume</strong><div>' . e((string) $summary['booking_volume']) . '</div></div>'
            . '<div class="card"><strong>Cancellation Rate</strong><div>' . e((string) $summary['cancellation_rate']) . '%</div></div>'
            . '<div class="card"><strong>Refund Totals</strong><div>' . e($summary['refund_totals']['amount'] . ' ' . $summary['refund_totals']['currency']) . '</div></div>'
            . '<div class="card"><strong>Customer Counts</strong><div>' . e((string) $summary['customer_counts']) . '</div></div>'
            . '<div class="card"><strong>Top Agency</strong><div>' . e($summary['top_agencies'][0]['name'] ?? 'N/A') . '</div></div>'
            . '</div>'
            . '<table><thead><tr><th>Period</th><th>Bookings</th><th>Cancellations</th><th>Revenue</th></tr></thead><tbody>' . $rows . '</tbody></table>'
            . '</body></html>';
    }
}
