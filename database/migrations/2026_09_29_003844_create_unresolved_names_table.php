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
        Schema::create('unresolved_names', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->constrained()->cascadeOnDelete();
            $table->string('raw_name');
            $table->string('side', 3);
            $table->foreignId('resolved_member_id')->nullable()->constrained('members');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['division_id', 'raw_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unresolved_names');
    }
};
