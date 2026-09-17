<?php

use App\Models\Party;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Modules, public identifiers and client metadata.
     *
     * The subdomain becomes optional: only the events sold as a published
     * gallery get one, the rest being addressed by uuid. Both uuids are
     * nullable in the schema so that existing rows can be backfilled, but the
     * model mints them on creation, so no row ever carries a null.
     */
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table): void {
            $table->string('name')->nullable()->change();
            $table->uuid('gallery_uuid')->nullable()->unique()->after('name');
            $table->uuid('client_uuid')->nullable()->unique()->after('gallery_uuid');
            $table->boolean('has_gallery')->default(true)->after('client_uuid');
            $table->boolean('has_audio_guestbook')->default(false)->after('has_gallery');
            $table->string('client_email')->nullable()->after('has_audio_guestbook');
            $table->dateTime('event_at')->nullable()->after('expire_at');
            $table->dateTime('greeting_reminder_sent_at')->nullable()->after('event_at');
            $table->dateTime('expiry_reminder_sent_at')->nullable()->after('greeting_reminder_sent_at');
        });

        Party::query()
            ->whereNull('client_uuid')
            ->each(function (Party $party): void {
                $party->forceFill([
                    'gallery_uuid' => (string) Str::uuid(),
                    'client_uuid' => (string) Str::uuid(),
                ])->save();
            });
    }

    /**
     * Rolling back fails if any party has been created without a subdomain in
     * the meantime: the column cannot go back to NOT NULL while it holds
     * nulls. That is the honest behaviour — the rollback would otherwise have
     * to invent a name for a party that never had one.
     */
    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table): void {
            $table->dropUnique(['gallery_uuid']);
            $table->dropUnique(['client_uuid']);
            $table->dropColumn([
                'gallery_uuid',
                'client_uuid',
                'has_gallery',
                'has_audio_guestbook',
                'client_email',
                'event_at',
                'greeting_reminder_sent_at',
                'expiry_reminder_sent_at',
            ]);
            $table->string('name')->nullable(false)->change();
        });
    }
};
