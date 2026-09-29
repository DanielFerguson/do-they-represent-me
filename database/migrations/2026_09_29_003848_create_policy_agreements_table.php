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
        Schema::create('policy_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_id')->constrained()->cascadeOnDelete();
            $table->morphs('subject');
            $table->unsignedSmallInteger('votes_same')->default(0);
            $table->unsignedSmallInteger('votes_same_strong')->default(0);
            $table->unsignedSmallInteger('votes_differ')->default(0);
            $table->unsignedSmallInteger('votes_differ_strong')->default(0);
            $table->unsignedSmallInteger('votes_absent')->default(0);
            $table->unsignedSmallInteger('votes_absent_strong')->default(0);
            $table->decimal('agreement', 5, 4)->nullable();
            $table->string('category');
            $table->timestamp('computed_at');

            $table->unique(['policy_id', 'subject_type', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('policy_agreements');
    }
};
