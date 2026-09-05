<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How the banner sits behind the gallery hero. The crop is applied in CSS
     * rather than cut into the file, so the stored banner stays whole and the
     * host can re-frame it as often as they like without losing quality.
     *
     * The columns carry a default instead of being nullable: "no framing
     * chosen" and "centred, unzoomed" render the same image, and a default
     * saves every consumer a null coalesce.
     */
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table): void {
            $table->decimal('banner_position_x', 5, 2)->default(50)->after('banner_path');
            $table->decimal('banner_position_y', 5, 2)->default(50)->after('banner_position_x');
            $table->decimal('banner_zoom', 4, 2)->default(1)->after('banner_position_y');
        });
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table): void {
            $table->dropColumn(['banner_position_x', 'banner_position_y', 'banner_zoom']);
        });
    }
};
