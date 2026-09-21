<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users');
            $table->string('full_name', 150);
            $table->string('avatar_url', 500)->nullable();
            $table->string('contact_address', 500)->nullable();
            $table->string('zalo_number', 20)->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->timestamp('created_at');
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedInteger('role_id');
            $table->foreignId('assigned_by')->nullable()->constrained('users');
            $table->timestamp('assigned_at');

            $table->primary(['user_id', 'role_id']);
            $table->foreign('role_id')->references('id')->on('roles');
        });

        Schema::create('provinces', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->boolean('is_active');
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('province_id');
            $table->string('code', 20);
            $table->string('name', 150);
            $table->boolean('is_active');

            $table->unique(['province_id', 'code']);
            $table->foreign('province_id')->references('id')->on('provinces');
        });

        Schema::create('wards', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('district_id');
            $table->string('code', 20);
            $table->string('name', 150);
            $table->boolean('is_active');

            $table->unique(['district_id', 'code']);
            $table->foreign('district_id')->references('id')->on('districts');
        });

        Schema::create('room_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->boolean('is_active');
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
        });

        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->boolean('is_active');
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
        });

        Schema::create('fee_types', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->boolean('is_active');
        });

        Schema::create('fee_units', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->boolean('is_active');
        });

        Schema::create('report_reasons', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->boolean('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_reasons');
        Schema::dropIfExists('fee_units');
        Schema::dropIfExists('fee_types');
        Schema::dropIfExists('amenities');
        Schema::dropIfExists('room_categories');
        Schema::dropIfExists('wards');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('provinces');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('user_profiles');
    }
};
