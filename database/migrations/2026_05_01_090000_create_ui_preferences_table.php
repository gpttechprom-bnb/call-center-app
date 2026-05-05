<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ui_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('profile_key', 64)->nullable();
            $table->string('scope', 120);
            $table->json('preferences');
            $table->timestamps();

            $table->unique(['user_id', 'scope']);
            $table->unique(['profile_key', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ui_preferences');
    }
};
