<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Weight of the uploaded file, in bytes. Pictures stored before it was
     * recorded leave it null.
     */
    public function up(): void
    {
        Schema::table('pictures', function (Blueprint $table): void {
            $table->unsignedBigInteger('size')->nullable()->after('height');
        });
    }

    public function down(): void
    {
        Schema::table('pictures', function (Blueprint $table): void {
            $table->dropColumn('size');
        });
    }
};
