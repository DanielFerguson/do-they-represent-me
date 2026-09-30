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
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('topic', 20);
            $table->string('name', 100)->nullable();
            $table->string('email', 254);
            $table->string('context', 300)->nullable();
            $table->string('context_url', 255)->nullable();
            $table->text('message');
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index(['handled_at', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
