<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every grid — the public gallery, the host's dashboard, the admin one —
     * reads one party's pictures newest first. The existing
     * `(party_id, updated_at)` index serves the polled aggregate, not this
     * order, so each page was filesorted.
     */
    public function up(): void
    {
        Schema::table('pictures', function (Blueprint $table): void {
            $table->index(['party_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('pictures', function (Blueprint $table): void {
            $table->dropIndex(['party_id', 'created_at']);
        });
    }
};
