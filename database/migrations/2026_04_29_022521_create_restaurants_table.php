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
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete()->index();
            $table->string('name');
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->json('cuisine_tags');
            $table->json('vibe_tags');
            $table->unsignedTinyInteger('price_level')->nullable();
            $table->string('source');
            $table->string('patio_quality')->default('none');
            $table->string('indoor_vibe_when_cold')->default('neutral');
            $table->unsignedSmallInteger('avg_duration_minutes')->nullable();
            $table->timestamp('last_visited_at')->nullable();
            $table->unsignedInteger('visit_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurants');
    }
};
