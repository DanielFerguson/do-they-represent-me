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
        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parliament_id')->constrained();
            $table->foreignId('house_id')->constrained();
            $table->foreignId('proceedings_document_id')->constrained();
            $table->unsignedSmallInteger('sitting_number');
            $table->date('sitting_date');
            $table->unsignedSmallInteger('sequence');
            $table->string('body');
            $table->unsignedSmallInteger('item_number')->nullable();
            $table->text('item_title')->nullable();
            $table->text('question')->nullable();
            $table->string('stage')->nullable();
            $table->string('presiding_role')->nullable();
            $table->foreignId('presiding_member_id')->nullable()->constrained('members');
            $table->string('result')->nullable();
            $table->unsignedSmallInteger('ayes_count');
            $table->unsignedSmallInteger('noes_count');
            $table->text('summary')->nullable();
            $table->boolean('is_free_vote')->default(false);
            $table->boolean('needs_review')->default(false);
            $table->timestamps();

            $table->unique(['parliament_id', 'house_id', 'sitting_number', 'sequence']);
            $table->index('sitting_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('divisions');
    }
};
