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
        Schema::table('stance_snapshots', function (Blueprint $table) {
            $table->text('payload')->change();
            $table->timestamp('published_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stance_snapshots', function (Blueprint $table) {
            $table->dropColumn('published_at');
            $table->jsonb('payload')->change();
        });
    }
};
