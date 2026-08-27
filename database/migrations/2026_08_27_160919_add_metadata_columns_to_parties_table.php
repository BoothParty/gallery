<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Event metadata rendered by the public gallery hero: the title, the line
     * below it and the banner behind them. All three are optional; a party
     * without a display name falls back to its name.
     */
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
            $table->string('description')->nullable()->after('display_name');
            $table->string('banner_path')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'description', 'banner_path']);
        });
    }
};
