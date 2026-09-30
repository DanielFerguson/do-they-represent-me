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
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('electorate_id')->constrained();
            $table->string('ballot_group', 3)->nullable();
            $table->unsignedSmallInteger('ballot_position');
            $table->string('given_names');
            $table->string('surname');
            $table->string('ballot_party')->nullable();
            $table->foreignId('party_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['election_id', 'electorate_id', 'ballot_group', 'ballot_position'])->nullsNotDistinct();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
