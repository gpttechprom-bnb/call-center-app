<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('binotel_api_call_completed_histories')) {
            Schema::create('binotel_api_call_completed_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('binotel_api_call_completed_id');
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedInteger('waitsec')->nullable();
                $table->unsignedInteger('billsec')->nullable();
                $table->string('disposition')->nullable();
                $table->string('internal_number')->nullable();
                $table->string('internal_additional_data')->nullable();
                $table->string('employee_name')->nullable();
                $table->string('employee_email')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('binotel_api_call_completed_histories', function (Blueprint $table) {
            $table->foreign('binotel_api_call_completed_id', 'binotel_api_call_completed_hist_fk')
                ->references('id')
                ->on('binotel_api_call_completeds')
                ->cascadeOnDelete();
            $table->index(['binotel_api_call_completed_id', 'sort_order'], 'binotel_api_call_completed_history_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('binotel_api_call_completed_histories');
    }
};
