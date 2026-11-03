<?php

use App\Enums\EventStatusEnum;
use App\Enums\EventTypeEnum;
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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('event_type')->default(EventTypeEnum::Concert->value);
            $table->string('status')->default(EventStatusEnum::Draft->value);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('published_at')->nullable();
            $table->timestamps();

            $table->index('organizer_id');
            $table->index('venue_id');
            $table->index('status');
            $table->index('starts_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
