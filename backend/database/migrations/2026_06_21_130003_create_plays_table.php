<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plays', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->references('id')->on('games');
            $table->foreignUuid('player_id')->references('id')->on('players');
            $table->unsignedInteger('turn_index');
            $table->string('drew_from');
            $table->string('discarded_code')->nullable();
            $table->unsignedInteger('lowered_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plays');
    }
};
