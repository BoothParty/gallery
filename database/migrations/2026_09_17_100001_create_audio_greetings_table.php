<?php

use App\Models\Party;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The message a client records for their guests to hear when they pick up
     * the handset. One per party, hence the unique foreign key: a table rather
     * than eight columns on `parties`, seven of which would be null for every
     * party sold without the audio guestbook.
     */
    public function up(): void
    {
        Schema::create('audio_greetings', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Party::class)->unique()->constrained()->cascadeOnDelete();
            $table->string('original_path');
            $table->string('mp3_path')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('client_note', 120)->nullable();
            $table->string('status', 16)->default('draft');
            $table->text('rejection_reason')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('validated_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_greetings');
    }
};
