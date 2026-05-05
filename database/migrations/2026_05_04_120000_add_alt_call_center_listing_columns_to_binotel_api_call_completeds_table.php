<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('binotel_api_call_completeds')) {
            return;
        }

        Schema::table('binotel_api_call_completeds', function (Blueprint $table): void {
            $table->string('interaction_phone_key', 32)->nullable()->after('interaction_number');
            $table->string('interaction_manager_key', 191)->nullable()->after('interaction_phone_key');
            $table->string('direction_key', 8)->nullable()->after('call_details_call_type');
            $table->string('employee_display_name', 191)->nullable()->after('call_details_employee_email');
        });

        Schema::table('binotel_api_call_completeds', function (Blueprint $table): void {
            if (! $this->hasIndex('binotel_api_call_completeds', 'binotel_calls_start_time_idx')) {
                $table->index('call_details_start_time', 'binotel_calls_start_time_idx');
            }

            if (! $this->hasIndex('binotel_api_call_completeds', 'binotel_calls_employee_display_idx')) {
                $table->index('employee_display_name', 'binotel_calls_employee_display_idx');
            }

            if (! $this->hasIndex('binotel_api_call_completeds', 'binotel_calls_interaction_history_idx')) {
                $table->index([
                    'interaction_phone_key',
                    'interaction_manager_key',
                    'call_details_start_time',
                ], 'binotel_calls_interaction_history_idx');
            }

            if (! $this->hasIndex('binotel_api_call_completeds', 'binotel_calls_interaction_direction_time_idx')) {
                $table->index([
                    'interaction_number',
                    'direction_key',
                    'call_details_start_time',
                ], 'binotel_calls_interaction_direction_time_idx');
            }
        });

        $this->backfillLookupColumns();
    }

    public function down(): void
    {
        if (! Schema::hasTable('binotel_api_call_completeds')) {
            return;
        }

        Schema::table('binotel_api_call_completeds', function (Blueprint $table): void {
            if ($this->hasIndex('binotel_api_call_completeds', 'binotel_calls_interaction_direction_time_idx')) {
                $table->dropIndex('binotel_calls_interaction_direction_time_idx');
            }

            if ($this->hasIndex('binotel_api_call_completeds', 'binotel_calls_interaction_history_idx')) {
                $table->dropIndex('binotel_calls_interaction_history_idx');
            }

            if ($this->hasIndex('binotel_api_call_completeds', 'binotel_calls_employee_display_idx')) {
                $table->dropIndex('binotel_calls_employee_display_idx');
            }

            if ($this->hasIndex('binotel_api_call_completeds', 'binotel_calls_start_time_idx')) {
                $table->dropIndex('binotel_calls_start_time_idx');
            }

            $table->dropColumn([
                'interaction_phone_key',
                'interaction_manager_key',
                'direction_key',
                'employee_display_name',
            ]);
        });
    }

    private function backfillLookupColumns(): void
    {
        DB::table('binotel_api_call_completeds')
            ->select([
                'id',
                'call_details_external_number',
                'call_details_employee_email',
                'call_details_internal_number',
                'call_details_employee_name',
                'call_details_call_type',
            ])
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('binotel_api_call_completeds')
                        ->where('id', $row->id)
                        ->update([
                            'interaction_phone_key' => $this->normalizeInteractionPhone($row->call_details_external_number ?? null) ?: null,
                            'interaction_manager_key' => $this->interactionManagerKey(
                                $row->call_details_employee_email ?? null,
                                $row->call_details_internal_number ?? null,
                                $row->call_details_employee_name ?? null,
                            ) ?: null,
                            'direction_key' => $this->directionKey($row->call_details_call_type ?? null),
                            'employee_display_name' => $this->employeeDisplayName(
                                $row->call_details_employee_name ?? null,
                                $row->call_details_internal_number ?? null,
                            ),
                        ]);
                }
            }, 'id');
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return DB::table(DB::raw("information_schema.statistics"))
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }

    private function normalizeInteractionPhone(mixed $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) ($value ?? '')) ?? '';

        if (strlen($digits) === 12 && str_starts_with($digits, '380')) {
            return substr($digits, 2);
        }

        return $digits;
    }

    private function normalizeInteractionToken(mixed $value): string
    {
        $normalized = trim((string) ($value ?? ''));
        $normalized = preg_replace('/^Wire:\s*/i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+Sip$/i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        return function_exists('mb_strtolower')
            ? mb_strtolower($normalized, 'UTF-8')
            : strtolower($normalized);
    }

    private function interactionManagerKey(mixed $email, mixed $internalNumber, mixed $employeeName): string
    {
        foreach ([$email, $internalNumber, $employeeName] as $candidate) {
            $normalized = $this->normalizeInteractionToken($candidate);

            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    private function employeeDisplayName(mixed $employeeName, mixed $internalNumber): string
    {
        $name = trim((string) ($employeeName ?? ''));
        if ($name !== '') {
            return $name;
        }

        $internal = trim((string) ($internalNumber ?? ''));
        if ($internal !== '') {
            return 'Внутрішній номер '.$internal;
        }

        return 'Не визначено';
    }

    private function directionKey(mixed $callType): string
    {
        $normalized = trim((string) ($callType ?? ''));

        return in_array($normalized, ['0', 'in', 'incoming'], true) ? 'in' : 'out';
    }
};
