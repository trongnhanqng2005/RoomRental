<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReferenceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_initializes_reference_data_without_demo_or_location_data(): void
    {
        $this->seed();

        $this->assertDatabaseCount('roles', 4);
        $this->assertDatabaseCount('room_categories', 6);
        $this->assertDatabaseCount('fee_types', 5);
        $this->assertDatabaseCount('fee_units', 5);
        $this->assertDatabaseCount('report_reasons', 5);
        $this->assertDatabaseCount('amenities', 0);
        $this->assertDatabaseCount('provinces', 0);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('listings', 0);
        $this->assertDatabaseCount('viewing_slots', 0);
        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_rerunning_database_seeder_preserves_admin_renamed_and_hidden_categories(): void
    {
        $this->seed();

        $category = DB::table('room_categories')->where('name', 'Phòng trọ')->first();
        $this->assertNotNull($category);

        DB::table('room_categories')->where('id', $category->id)->update([
            'name' => 'Danh mục đã được Admin đổi tên',
            'is_active' => false,
        ]);

        $this->seed();

        $this->assertDatabaseCount('room_categories', 6);
        $this->assertDatabaseHas('room_categories', [
            'id' => $category->id,
            'name' => 'Danh mục đã được Admin đổi tên',
            'is_active' => false,
        ]);
        $this->assertDatabaseMissing('room_categories', ['name' => 'Phòng trọ']);
        $this->assertDatabaseCount('roles', 4);
        $this->assertDatabaseCount('fee_types', 5);
        $this->assertDatabaseCount('fee_units', 5);
        $this->assertDatabaseCount('report_reasons', 5);
    }
}
