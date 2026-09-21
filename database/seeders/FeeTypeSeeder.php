<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeeTypeSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('fee_types')->upsert([
            ['code' => 'ELECTRICITY', 'name' => 'Điện', 'is_active' => true],
            ['code' => 'WATER', 'name' => 'Nước', 'is_active' => true],
            ['code' => 'INTERNET', 'name' => 'Internet', 'is_active' => true],
            ['code' => 'PARKING', 'name' => 'Gửi xe', 'is_active' => true],
            ['code' => 'SERVICE', 'name' => 'Dịch vụ', 'is_active' => true],
        ], ['code'], ['name', 'is_active']);
    }
}
