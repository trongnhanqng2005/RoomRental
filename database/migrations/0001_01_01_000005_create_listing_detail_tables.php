<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings');
            $table->string('image_url', 500);
            $table->boolean('is_cover');
            $table->integer('display_order');
            $table->timestamp('created_at');

            $table->unique(['listing_id', 'display_order']);
        });

        Schema::create('listing_amenities', function (Blueprint $table) {
            $table->foreignId('listing_id')->constrained('listings');
            $table->foreignId('amenity_id')->constrained('amenities');

            $table->primary(['listing_id', 'amenity_id']);
        });

        Schema::create('listing_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings');
            $table->unsignedInteger('fee_type_id');
            $table->unsignedInteger('fee_unit_id');
            $table->decimal('amount', 12, 2);
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->unique(['listing_id', 'fee_type_id']);
            $table->foreign('fee_type_id')->references('id')->on('fee_types');
            $table->foreign('fee_unit_id')->references('id')->on('fee_units');
        });

        Schema::create('listing_moderations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings');
            $table->integer('version_no');
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED']);
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->string('rejection_reason', 1000)->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();

            $table->unique(['listing_id', 'version_no']);
            $table->unique(['id', 'listing_id']);
        });

        DB::statement("ALTER TABLE listing_moderations ADD CONSTRAINT moderations_pending_unreviewed CHECK (status <> 'PENDING' OR (reviewed_by IS NULL AND reviewed_at IS NULL))");
        DB::statement("ALTER TABLE listing_moderations ADD CONSTRAINT moderations_review_fields_required CHECK (status NOT IN ('APPROVED', 'REJECTED') OR (reviewed_by IS NOT NULL AND reviewed_at IS NOT NULL))");
        DB::statement("ALTER TABLE listing_moderations ADD CONSTRAINT moderations_rejection_reason_required CHECK (status <> 'REJECTED' OR rejection_reason IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_moderations');
        Schema::dropIfExists('listing_fees');
        Schema::dropIfExists('listing_amenities');
        Schema::dropIfExists('listing_images');
    }
};
