<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_categories', function (Blueprint $table) {
            $table->unique('name', 'room_categories_name_unique');
        });

        Schema::table('amenities', function (Blueprint $table) {
            $table->unique('name', 'amenities_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('amenities', function (Blueprint $table) {
            $table->dropUnique('amenities_name_unique');
        });

        Schema::table('room_categories', function (Blueprint $table) {
            $table->dropUnique('room_categories_name_unique');
        });
    }
};
