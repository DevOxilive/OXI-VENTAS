<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('physical_count_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('physical_count_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('round_number');
            $table->string('type', 20)->default('original');
            $table->string('scope', 30)->default('all');
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->unique(['physical_count_id', 'round_number']);
        });

        Schema::table('physical_count_entries', function (Blueprint $table) {
            $table->foreignId('physical_count_round_id')
                ->nullable()
                ->after('physical_count_id')
                ->constrained('physical_count_rounds')
                ->nullOnDelete();
        });

        DB::table('physical_counts')
            ->orderBy('id')
            ->get()
            ->each(function (object $audit): void {
                $originalStartedAt = $audit->started_at ?? $audit->created_at ?? now();
                $originalClosedAt = $audit->recapture_started_at
                    ? $audit->recapture_started_at
                    : $audit->closed_at;

                $originalRoundId = DB::table('physical_count_rounds')->insertGetId([
                    'physical_count_id' => $audit->id,
                    'round_number' => 1,
                    'type' => 'original',
                    'scope' => 'all',
                    'opened_by' => $audit->created_by,
                    'started_at' => $originalStartedAt,
                    'closed_at' => $originalClosedAt,
                    'applied_at' => $audit->recapture_started_at ? $audit->last_applied_at : null,
                    'created_at' => $originalStartedAt,
                    'updated_at' => now(),
                ]);

                DB::table('physical_count_entries')
                    ->where('physical_count_id', $audit->id)
                    ->when(
                        $audit->recapture_started_at,
                        fn ($query) => $query->where('created_at', '<', $audit->recapture_started_at)
                    )
                    ->update(['physical_count_round_id' => $originalRoundId]);

                if (! $audit->recapture_started_at) {
                    DB::table('physical_count_entries')
                        ->where('physical_count_id', $audit->id)
                        ->whereNull('physical_count_round_id')
                        ->update(['physical_count_round_id' => $originalRoundId]);

                    return;
                }

                $reopenRoundId = DB::table('physical_count_rounds')->insertGetId([
                    'physical_count_id' => $audit->id,
                    'round_number' => 2,
                    'type' => 'reopening',
                    'scope' => $audit->recapture_scope ?: 'all',
                    'opened_by' => $audit->created_by,
                    'started_at' => $audit->recapture_started_at,
                    'closed_at' => $audit->closed_at,
                    'applied_at' => $audit->last_applied_at,
                    'created_at' => $audit->recapture_started_at,
                    'updated_at' => now(),
                ]);

                DB::table('physical_count_entries')
                    ->where('physical_count_id', $audit->id)
                    ->whereNull('physical_count_round_id')
                    ->update(['physical_count_round_id' => $reopenRoundId]);
            });
    }

    public function down(): void
    {
        Schema::table('physical_count_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('physical_count_round_id');
        });

        Schema::dropIfExists('physical_count_rounds');
    }
};
