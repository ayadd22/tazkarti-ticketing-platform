<?php

use App\Enums\TicketStatusEnum;
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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->restrictOnDelete();
            $table->foreignId('ticket_type_id')->constrained()->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('status')->default(TicketStatusEnum::Valid->value);
            $table->string('file_path')->nullable();
            $table->timestamps();

            $table->index('reservation_id');
            $table->index('ticket_type_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
