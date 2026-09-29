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
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('house_id')->constrained();
            $table->foreignId('electorate_id')->constrained();
            $table->foreignId('party_id')->constrained();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('start_reason')->nullable();
            $table->string('end_reason')->nullable();
            $table->timestamps();

            $table->index(['house_id', 'starts_on', 'ends_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
