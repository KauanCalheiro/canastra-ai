<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->foreignUuid('sequence_id')->nullable()->references('id')->on('sequences');
            $table->unsignedInteger('sequence_position')->nullable();
            $table->string('role')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn(['sequence_id', 'sequence_position', 'role']);
        });
    }
};
