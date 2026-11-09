<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->string('kind', 30);
            $table->date('occurred_on');
            $table->text('body');
            $table->text('reflection')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'occurred_on', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entries');
    }
};
