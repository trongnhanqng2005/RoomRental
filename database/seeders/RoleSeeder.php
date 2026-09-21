<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $createdAt = now();

        DB::table('roles')->upsert([
            ['code' => 'SUPER_ADMIN', 'name' => 'Super Admin', 'created_at' => $createdAt],
            ['code' => 'ADMIN', 'name' => 'Admin', 'created_at' => $createdAt],
            ['code' => 'RENTER', 'name' => 'Người tìm trọ', 'created_at' => $createdAt],
            ['code' => 'LANDLORD', 'name' => 'Chủ trọ', 'created_at' => $createdAt],
        ], ['code'], ['name']);
    }
}
