<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('listing_id')->constrained('listings');
            $table->timestamp('created_at');

            $table->primary(['user_id', 'listing_id']);
        });

        Schema::create('viewing_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings');
            $table->date('viewing_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['OPEN', 'CLOSED']);
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->unique(['listing_id', 'viewing_date', 'start_time', 'end_time'], 'viewing_slots_listing_schedule_unique');
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_id')->constrained('viewing_slots');
            $table->foreignId('renter_id')->constrained('users');
            $table->enum('status', ['PENDING', 'ACCEPTED', 'REJECTED', 'CANCELLED', 'COMPLETED', 'AUTO_CANCELLED']);
            $table->string('renter_note', 1000)->nullable();
            $table->string('landlord_response', 1000)->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->string('cancellation_reason', 1000)->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            // NULL values remain non-conflicting, preserving non-active appointment history.
            $table->unsignedBigInteger('active_slot_id')
                ->storedAs("CASE WHEN status IN ('PENDING', 'ACCEPTED') THEN slot_id ELSE NULL END");

            $table->unique('active_slot_id', 'appointments_one_active_per_slot_unique');
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users');
            $table->foreignId('listing_id')->constrained('listings');
            $table->unsignedInteger('reason_id');
            $table->text('description')->nullable();
            $table->enum('status', ['PENDING', 'RESOLVED', 'DISMISSED']);
            $table->foreignId('handled_by')->nullable()->constrained('users');
            $table->string('resolution_reason', 1000)->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            // Only pending rows materialize this value; historical rows generate NULL.
            $table->unsignedBigInteger('pending_listing_id')
                ->storedAs("CASE WHEN status = 'PENDING' THEN listing_id ELSE NULL END");

            $table->foreign('reason_id')->references('id')->on('report_reasons');
            $table->unique(['reporter_id', 'pending_listing_id'], 'reports_one_pending_per_reporter_listing_unique');
        });

        DB::statement("ALTER TABLE reports ADD CONSTRAINT reports_dismissal_reason_required CHECK (status <> 'DISMISSED' OR resolution_reason IS NOT NULL)");

        Schema::create('enforcement_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('reports');
            $table->foreignId('admin_id')->constrained('users');
            $table->enum('action_type', ['WARNING', 'SUSPEND_LISTING', 'LOCK_ACCOUNT']);
            $table->foreignId('target_user_id')->nullable()->constrained('users');
            $table->foreignId('target_listing_id')->nullable()->constrained('listings');
            $table->string('reason', 1000);
            $table->timestamp('created_at');

        });

        DB::statement("ALTER TABLE enforcement_actions ADD CONSTRAINT enforcement_user_target_required CHECK (action_type NOT IN ('WARNING', 'LOCK_ACCOUNT') OR target_user_id IS NOT NULL)");
        DB::statement("ALTER TABLE enforcement_actions ADD CONSTRAINT enforcement_listing_target_required CHECK (action_type <> 'SUSPEND_LISTING' OR target_listing_id IS NOT NULL)");

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('notification_type', 50);
            $table->string('title');
            $table->string('message', 1000);
            $table->string('entity_type', 50)->nullable();
            $table->bigInteger('entity_id')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->constrained('users');
            $table->string('action', 100);
            $table->string('entity_type', 50);
            $table->bigInteger('entity_id');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('enforcement_actions');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('viewing_slots');
        Schema::dropIfExists('favorites');
    }
};
