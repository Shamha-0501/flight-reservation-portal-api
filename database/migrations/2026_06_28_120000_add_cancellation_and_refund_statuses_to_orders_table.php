<?php

use App\Models\Order;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('cancellation_status', 40)
                ->nullable()
                ->after('status');
            $table->string('refund_status', 40)
                ->nullable()
                ->after('cancellation_status');

            $table->index(['tenant_id', 'cancellation_status']);
            $table->index(['tenant_id', 'refund_status']);
        });

        DB::table('orders')->orderBy('id')->chunkById(100, function ($orders) {
            foreach ($orders as $order) {
                [$cancellationStatus, $refundStatus] = match ($order->status) {
                    Order::STATUS_CANCELLATION_REQUESTED => [
                        Order::CANCELLATION_STATUS_REQUESTED,
                        null,
                    ],
                    Order::STATUS_CANCELLED => [
                        Order::CANCELLATION_STATUS_CANCELLED,
                        null,
                    ],
                    Order::STATUS_REFUNDED => [
                        Order::CANCELLATION_STATUS_CANCELLED,
                        Order::REFUND_STATUS_REFUNDED,
                    ],
                    'Cancelled - No Refund' => [
                        Order::CANCELLATION_STATUS_CANCELLED,
                        Order::REFUND_STATUS_NONE,
                    ],
                    'Refund Pending' => [
                        Order::CANCELLATION_STATUS_CANCELLED,
                        Order::REFUND_STATUS_PENDING,
                    ],
                    'Refund Unknown' => [
                        Order::CANCELLATION_STATUS_CANCELLED,
                        Order::REFUND_STATUS_UNKNOWN,
                    ],
                    default => [
                        Order::CANCELLATION_STATUS_NONE,
                        null,
                    ],
                };

                DB::table('orders')
                    ->where('id', $order->id)
                    ->update([
                        'cancellation_status' => $cancellationStatus,
                        'refund_status' => $refundStatus,
                    ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'cancellation_status']);
            $table->dropIndex(['tenant_id', 'refund_status']);
            $table->dropColumn([
                'cancellation_status',
                'refund_status',
            ]);
        });
    }
};
