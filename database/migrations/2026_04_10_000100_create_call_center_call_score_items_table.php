<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('call_center_call_score_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_center_call_id')
                ->constrained('call_center_calls')
                ->cascadeOnDelete();
            $table->string('title');
            $table->unsignedTinyInteger('score');
            $table->text('text');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_center_call_score_items');
    }
};
