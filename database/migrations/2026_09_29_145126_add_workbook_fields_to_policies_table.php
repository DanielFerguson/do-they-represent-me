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
        Schema::table('policies', function (Blueprint $table) {
            $table->unsignedSmallInteger('number')->unique()->after('id');
            $table->text('agree_means')->nullable()->default(null)->change();
            $table->text('arguments_for')->nullable()->after('rationale');
            $table->text('arguments_against')->nullable()->after('arguments_for');
            $table->text('sources')->nullable()->after('arguments_against');
            $table->text('verification_notes')->nullable()->after('sources');
            $table->text('reviewer_notes')->nullable()->after('verification_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('policies', function (Blueprint $table) {
            $table->dropColumn(['number', 'arguments_for', 'arguments_against', 'sources', 'verification_notes', 'reviewer_notes']);
            $table->string('agree_means', 3)->default('aye')->change();
        });
    }
};
