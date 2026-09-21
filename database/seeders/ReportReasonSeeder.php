<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReportReasonSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('report_reasons')->upsert([
            ['code' => 'WRONG_ADDRESS', 'name' => 'Sai địa chỉ', 'is_active' => true],
            ['code' => 'WRONG_PRICE', 'name' => 'Sai giá', 'is_active' => true],
            ['code' => 'FRAUD', 'name' => 'Lừa đảo', 'is_active' => true],
            ['code' => 'INAPPROPRIATE_CONTENT', 'name' => 'Nội dung không phù hợp', 'is_active' => true],
            ['code' => 'OTHER', 'name' => 'Khác', 'is_active' => true],
        ], ['code'], ['name', 'is_active']);
    }
}
