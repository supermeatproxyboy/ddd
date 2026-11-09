<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->uuid('event_id')->primary();
            $table->string('event_type');
            $table->unsignedSmallInteger('event_version');
            $table->string('aggregate_type');
            $table->unsignedBigInteger('aggregate_id');
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->json('payload');
            $table->timestampTz('occurred_at', 6);
            $table->timestampTz('published_at', 6)->nullable();
            $table->index(['published_at', 'occurred_at']);
            $table->index(['aggregate_type', 'aggregate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
