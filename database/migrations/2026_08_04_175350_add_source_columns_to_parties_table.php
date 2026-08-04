<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keep track of the provider event a party was created from, so the
     * administration can tell which upcoming events were already imported.
     */
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->string('source_key')->nullable()->after('name');
            $table->string('source_event_id')->nullable()->after('source_key');

            $table->index(['source_key', 'source_event_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->dropIndex(['source_key', 'source_event_id']);
            $table->dropColumn(['source_key', 'source_event_id']);
        });
    }
};
