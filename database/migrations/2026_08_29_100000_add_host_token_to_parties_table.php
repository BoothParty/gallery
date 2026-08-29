<?php

use App\Models\Party;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Secret of the link that lets a host edit their own gallery without an
     * account. Stored in the clear so the administration can show the link
     * again at any time; a hash would make the link unrecoverable.
     */
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table): void {
            $table->string('host_token', 64)->nullable()->unique()->after('name');
        });

        Party::query()
            ->whereNull('host_token')
            ->each(function (Party $party): void {
                $party->forceFill(['host_token' => Str::random(48)])->save();
            });
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table): void {
            $table->dropUnique(['host_token']);
            $table->dropColumn('host_token');
        });
    }
};
