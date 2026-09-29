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
        Schema::create('proceedings_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('house_id')->constrained();
            $table->string('source_key')->unique();
            $table->string('title');
            $table->unsignedSmallInteger('first_sitting_number')->nullable();
            $table->unsignedSmallInteger('last_sitting_number')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('docx_url', 1024);
            $table->string('pdf_url', 1024)->nullable();
            $table->string('raw_path')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->unsignedSmallInteger('divisions_count')->default(0);
            $table->timestamp('fetched_at')->nullable();
            $table->timestamp('parsed_at')->nullable();
            $table->text('parse_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proceedings_documents');
    }
};
