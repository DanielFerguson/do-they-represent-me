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
        Schema::create('policy_imports', function (Blueprint $table) {
            $table->id();
            $table->char('sha256', 64);
            $table->string('path');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('summary');
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('policy_imports');
    }
};
