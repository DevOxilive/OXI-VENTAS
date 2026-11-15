<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->date('sale_date');
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->timestamps();

            $table->unique(['sale_date', 'branch_id', 'product_id'], 'sales_daily_summary_unique');
            $table->index(['branch_id', 'sale_date', 'product_id'], 'sales_daily_summary_report_index');
        });

        $returnedByDetail = DB::table('sale_cancellation_details as cancellation_details')
            ->join('sale_cancellations as cancellations', 'cancellations.id', '=', 'cancellation_details.sale_cancellation_id')
            ->whereNull('cancellations.voided_at')
            ->select([
                'cancellation_details.sale_detail_id',
                DB::raw('SUM(COALESCE(cancellation_details.base_quantity, cancellation_details.quantity)) as returned_quantity'),
            ])
            ->groupBy('cancellation_details.sale_detail_id');

        $dailyRows = DB::table('sales')
            ->join('sale_details', 'sale_details.sale_id', '=', 'sales.id')
            ->leftJoinSub($returnedByDetail, 'returns', function ($join) {
                $join->on('returns.sale_detail_id', '=', 'sale_details.id');
            })
            ->whereIn('sales.status', ['completed', 'cancelled'])
            ->select([
                DB::raw('DATE(sales.date) as sale_date'),
                'sales.branch_id',
                'sale_details.product_id',
                DB::raw('SUM(COALESCE(sale_details.base_quantity, sale_details.quantity) - COALESCE(returns.returned_quantity, 0)) as quantity'),
            ])
            ->groupBy(DB::raw('DATE(sales.date)'), 'sales.branch_id', 'sale_details.product_id')
            ->havingRaw('ABS(SUM(COALESCE(sale_details.base_quantity, sale_details.quantity) - COALESCE(returns.returned_quantity, 0))) > 0.000001')
            ->get();

        $timestamp = now();
        $dailyRows->chunk(500)->each(function ($rows) use ($timestamp) {
            DB::table('sales_daily_summaries')->insert($rows->map(fn ($row) => [
                'sale_date' => $row->sale_date,
                'branch_id' => $row->branch_id,
                'product_id' => $row->product_id,
                'quantity' => $row->quantity,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->all());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_daily_summaries');
    }
};
