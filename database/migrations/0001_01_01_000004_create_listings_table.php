<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users');
            $table->foreignId('category_id')->constrained('room_categories');
            $table->unsignedBigInteger('current_moderation_id')->nullable();
            $table->string('title');
            $table->text('description');
            $table->decimal('monthly_rent', 12, 2);
            $table->decimal('deposit_amount', 12, 2)->nullable();
            $table->decimal('area_m2', 8, 2);
            $table->integer('max_occupants');
            $table->integer('bedroom_count');
            $table->integer('bathroom_count');
            $table->enum('gender_requirement', ['ANY', 'MALE', 'FEMALE']);
            $table->unsignedInteger('ward_id');
            $table->string('street_address', 500);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->enum('occupancy_status', ['AVAILABLE', 'RENTED']);
            $table->enum('visibility_status', ['VISIBLE', 'HIDDEN', 'SUSPENDED']);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->bigInteger('view_count');
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->foreign('ward_id')->references('id')->on('wards');
            $table->index(['current_moderation_id', 'id'], 'listings_current_moderation_listing_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
