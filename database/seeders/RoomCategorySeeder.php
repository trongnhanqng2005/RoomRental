<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoomCategorySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            // Categories have no stable seed key. Initialize only an empty catalog
            // so a rerun cannot recreate a renamed or hidden Admin-managed row.
            if (DB::table('room_categories')->exists()) {
                return;
            }

            $now = now();

            foreach (['Phòng trọ', 'Căn hộ mini', 'Chung cư', 'Studio', 'Nhà nguyên căn', 'Ở ghép'] as $name) {
                DB::table('room_categories')->insert([
                    'name' => $name,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }
}
