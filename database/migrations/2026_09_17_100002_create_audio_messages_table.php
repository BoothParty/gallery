<?php

use App\Models\Party;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The messages the guests left on the handset, uploaded by the studio
     * after the event. Already MP3 when they come off the device, so they are
     * stored untouched; `position` is the order they were recorded in.
     */
    public function up(): void
    {
        Schema::create('audio_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Party::class)->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('title')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->dateTime('recorded_at')->nullable();
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->index(['party_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_messages');
    }
};
