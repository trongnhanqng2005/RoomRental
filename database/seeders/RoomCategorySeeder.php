<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoomCategorySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        foreach (['Phòng trọ', 'Căn hộ mini', 'Chung cư', 'Studio', 'Nhà nguyên căn', 'Ở ghép'] as $name) {
            if (DB::table('room_categories')->where('name', $name)->exists()) {
                continue;
            }

            DB::table('room_categories')->insert([
                'name' => $name,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
