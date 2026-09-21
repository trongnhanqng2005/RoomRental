<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->foreign(['current_moderation_id', 'id'], 'listings_current_moderation_foreign')
                ->references(['id', 'listing_id'])
                ->on('listing_moderations');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropForeign('listings_current_moderation_foreign');
        });
    }
};
