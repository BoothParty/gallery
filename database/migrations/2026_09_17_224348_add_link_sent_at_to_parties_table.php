<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the client was last sent the link to their audio space. Shown next
     * to the button that sends it, so the studio can tell a client who never
     * received it from one who ignored it.
     */
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table): void {
            $table->dateTime('link_sent_at')->nullable()->after('client_email');
        });
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table): void {
            $table->dropColumn('link_sent_at');
        });
    }
};
