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
        Schema::create('division_party_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id')->constrained();
            $table->unsignedSmallInteger('ayes');
            $table->unsignedSmallInteger('noes');
            $table->unsignedSmallInteger('eligible');
            $table->string('position', 5);

            $table->unique(['division_id', 'party_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('division_party_positions');
    }
};
