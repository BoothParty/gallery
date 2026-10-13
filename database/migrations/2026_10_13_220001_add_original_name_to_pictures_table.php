<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Path of the picture inside the folder it was dropped from, root folder
     * excluded. Pictures sent by the booth leave it null, and nulls never
     * collide under the unique index.
     */
    public function up(): void
    {
        Schema::table('pictures', function (Blueprint $table): void {
            $table->string('original_name')->nullable()->after('path');
            $table->unique(['party_id', 'original_name']);
        });
    }

    public function down(): void
    {
        Schema::table('pictures', function (Blueprint $table): void {
            $table->dropUnique(['party_id', 'original_name']);
            $table->dropColumn('original_name');
        });
    }
};
