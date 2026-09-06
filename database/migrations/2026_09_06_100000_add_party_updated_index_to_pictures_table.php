<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The gallery polls an aggregate of `COUNT(*)` and `MAX(updated_at)` per
     * party. The foreign key index already covers the count; the composite
     * one lets the maximum resolve without touching a single row.
     */
    public function up(): void
    {
        Schema::table('pictures', function (Blueprint $table) {
            $table->index(['party_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::table('pictures', function (Blueprint $table) {
            $table->dropIndex(['party_id', 'updated_at']);
        });
    }
};
