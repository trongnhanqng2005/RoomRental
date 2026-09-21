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
            DB::table('room_categories')->updateOrInsert(
                ['name' => $name],
                fn (bool $exists) => $exists
                    ? ['is_active' => true, 'updated_at' => $now]
                    : ['is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }
}
