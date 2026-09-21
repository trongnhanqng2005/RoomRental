<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeeUnitSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('fee_units')->upsert([
            ['code' => 'FIXED_MONTHLY', 'name' => 'Cố định hàng tháng', 'is_active' => true],
            ['code' => 'PER_PERSON', 'name' => 'Theo người', 'is_active' => true],
            ['code' => 'PER_KWH', 'name' => 'Theo kWh', 'is_active' => true],
            ['code' => 'PER_M3', 'name' => 'Theo m³', 'is_active' => true],
            ['code' => 'PER_VEHICLE', 'name' => 'Theo xe', 'is_active' => true],
        ], ['code'], ['name', 'is_active']);
    }
}
