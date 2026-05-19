<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('recurrence');
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->date('specific_date')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('shared')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
