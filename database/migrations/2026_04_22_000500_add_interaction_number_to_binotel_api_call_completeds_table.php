<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('binotel_api_call_completeds', function (Blueprint $table) {
            $table->unsignedInteger('interaction_number')->nullable()->index();
        });

        $this->backfillInteractionNumbers();
    }

    public function down(): void
    {
        Schema::table('binotel_api_call_completeds', function (Blueprint $table) {
            $table->dropIndex(['interaction_number']);
            $table->dropColumn('interaction_number');
        });
    }

    private function backfillInteractionNumbers(): void
    {
        $rows = DB::table('binotel_api_call_completeds')
            ->select([
                'id',
                'call_details_start_time',
                'call_details_external_number',
                'call_details_employee_email',
                'call_details_internal_number',
                'call_details_employee_name',
            ])
            ->orderBy('call_details_start_time')
            ->orderBy('id')
            ->get();

        $groups = [];

        foreach ($rows as $row) {
            $key = $this->interactionKey($row);

            if ($key === null) {
                continue;
            }

            $groups[$key][] = $row;
        }

        foreach ($groups as $groupRows) {
            usort($groupRows, function (object $left, object $right): int {
                $leftTime = (int) ($left->call_details_start_time ?? 0);
                $rightTime = (int) ($right->call_details_start_time ?? 0);

                if ($leftTime !== $rightTime) {
                    return $leftTime <=> $rightTime;
                }

                return (int) $left->id <=> (int) $right->id;
            });

            foreach ($groupRows as $index => $row) {
                DB::table('binotel_api_call_completeds')
                    ->where('id', $row->id)
                    ->update(['interaction_number' => $index + 1]);
            }
        }
    }

    private function interactionKey(object $row): ?string
    {
        $phone = $this->normalizeInteractionPhone($row->call_details_external_number ?? null);
        $manager = $this->resolveManagerKey($row);

        if ($phone === '' || $manager === '') {
            return null;
        }

        return $phone.'::'.$manager;
    }

    private function normalizeInteractionPhone(mixed $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) ($value ?? '')) ?? '';

        if (strlen($digits) === 12 && str_starts_with($digits, '380')) {
            return substr($digits, 2);
        }

        return $digits;
    }

    private function resolveManagerKey(object $row): string
    {
        foreach ([
            $row->call_details_employee_email ?? null,
            $row->call_details_internal_number ?? null,
            $row->call_details_employee_name ?? null,
        ] as $value) {
            $normalized = $this->normalizeInteractionToken($value);

            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    private function normalizeInteractionToken(mixed $value): string
    {
        $normalized = trim((string) ($value ?? ''));
        $normalized = preg_replace('/^Wire:\s*/i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+Sip$/i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return function_exists('mb_strtolower')
            ? mb_strtolower(trim($normalized), 'UTF-8')
            : strtolower(trim($normalized));
    }
};
