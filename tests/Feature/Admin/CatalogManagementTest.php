<?php

namespace Tests\Feature\Admin;

use App\Models\Amenity;
use App\Models\AuditLog;
use App\Models\RoomCategory;
use Database\Seeders\RoomCategorySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\Feature\Report\ReportFeatureTestCase;

class CatalogManagementTest extends ReportFeatureTestCase
{
    public function test_only_admin_capable_users_can_access_catalog_management(): void
    {
        $this->get(route('admin.categories.index'))->assertRedirect('/login');

        foreach ([['RENTER'], ['LANDLORD'], ['RENTER', 'LANDLORD']] as $roles) {
            $user = $this->userWithRoles($roles);
            $this->actingAs($user)->get(route('admin.categories.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.amenities.index'))->assertForbidden();
            $this->actingAs($user)->patch(route('admin.categories.hide', $this->category))->assertForbidden();
        }

        foreach ([['ADMIN'], ['SUPER_ADMIN'], ['ADMIN', 'LANDLORD']] as $roles) {
            $this->actingAs($this->userWithRoles($roles))
                ->get(route('admin.categories.index'))
                ->assertOk();
            $this->actingAs($this->userWithRoles($roles))
                ->get(route('admin.amenities.index'))
                ->assertOk();
        }
    }

    public function test_category_creation_normalizes_name_and_audits_the_active_record(): void
    {
        $admin = $this->userWithRoles(['ADMIN']);

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), [
                'name' => "  Căn   hộ\tmini  ",
                'description' => '  Mô tả  ',
            ])
            ->assertRedirect(route('admin.categories.index'));

        $category = RoomCategory::query()->where('name', 'Căn hộ mini')->firstOrFail();
        $this->assertTrue($category->is_active);
        $this->assertSame('Mô tả', $category->description);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'room_category.created',
            'entity_type' => RoomCategory::class,
            'entity_id' => $category->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), ['name' => "\u{00A0}Studio\u{00A0}\tApartment\u{00A0}"])
            ->assertRedirect();
        $this->assertDatabaseHas('room_categories', ['name' => 'Studio Apartment']);
    }

    public function test_category_names_are_unique_across_hidden_records_and_normalized_before_validation(): void
    {
        $this->category->update(['is_active' => false]);
        $admin = $this->userWithRoles(['ADMIN']);

        $this->actingAs($admin)
            ->from(route('admin.categories.index'))
            ->post(route('admin.categories.store'), ['name' => ' Phòng   trọ '])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, RoomCategory::query()->count());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_amenity_creation_normalizes_and_validates_name_and_description(): void
    {
        $admin = $this->userWithRoles(['ADMIN']);

        $this->actingAs($admin)
            ->post(route('admin.amenities.store'), [
                'name' => "  Khu   giặt\tủi  ",
                'description' => str_repeat('x', 501),
            ])
            ->assertSessionHasErrors('description');
        $this->assertDatabaseCount('amenities', 0);

        $this->actingAs($admin)
            ->post(route('admin.amenities.store'), [
                'name' => "  Khu   giặt\tủi  ",
                'description' => null,
            ])
            ->assertRedirect(route('admin.amenities.index'));

        $amenity = Amenity::query()->where('name', 'Khu giặt ủi')->firstOrFail();
        $this->assertTrue($amenity->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'amenity.created',
            'entity_type' => Amenity::class,
            'entity_id' => $amenity->id,
        ]);

        $amenity->forceFill(['is_active' => false])->save();
        $this->actingAs($admin)
            ->from(route('admin.amenities.index'))
            ->post(route('admin.amenities.store'), ['name' => 'Khu giặt ủi'])
            ->assertSessionHasErrors('name');
    }

    public function test_category_edit_preserves_listing_reference_and_does_not_change_moderation(): void
    {
        $listing = $this->listing($this->userWithRoles(['LANDLORD']));
        $admin = $this->userWithRoles(['ADMIN']);

        $this->actingAs($admin)
            ->put(route('admin.categories.update', $this->category), [
                'name' => 'Căn hộ mới',
                'description' => null,
            ])
            ->assertRedirect(route('admin.categories.index'));

        $this->assertSame($this->category->id, $listing->fresh()->category_id);
        $this->assertSame(1, $listing->moderations()->count());
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'room_category.updated',
            'entity_type' => RoomCategory::class,
            'entity_id' => $this->category->id,
        ]);
    }

    public function test_category_and_amenity_edit_reject_names_used_by_another_record(): void
    {
        $category = RoomCategory::query()->create(['name' => 'Căn hộ mini', 'is_active' => false]);
        $amenity = Amenity::query()->create(['name' => 'Máy giặt', 'is_active' => true]);
        $otherAmenity = Amenity::query()->create(['name' => 'Chỗ để xe', 'is_active' => false]);
        $admin = $this->userWithRoles(['ADMIN']);

        $this->actingAs($admin)
            ->from(route('admin.categories.edit', $category))
            ->put(route('admin.categories.update', $category), ['name' => ' Phòng   trọ '])
            ->assertSessionHasErrors('name');
        $this->actingAs($admin)
            ->from(route('admin.amenities.edit', $otherAmenity))
            ->put(route('admin.amenities.update', $otherAmenity), ['name' => ' Máy   giặt '])
            ->assertSessionHasErrors('name');

        $this->assertSame('Căn hộ mini', $category->fresh()->name);
        $this->assertSame('Máy giặt', $amenity->fresh()->name);
        $this->assertSame('Chỗ để xe', $otherAmenity->fresh()->name);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_noop_category_edit_and_repeated_state_changes_do_not_create_duplicate_audits(): void
    {
        $admin = $this->userWithRoles(['ADMIN']);
        $this->actingAs($admin)->put(route('admin.categories.update', $this->category), [
            'name' => ' Phòng   trọ ',
            'description' => null,
        ])->assertRedirect();

        $this->actingAs($admin)->patch(route('admin.categories.hide', $this->category))->assertRedirect();
        $this->actingAs($admin)->patch(route('admin.categories.hide', $this->category))->assertRedirect();
        $this->actingAs($admin)->patch(route('admin.categories.restore', $this->category))->assertRedirect();
        $this->actingAs($admin)->patch(route('admin.categories.restore', $this->category))->assertRedirect();

        $this->assertSame([
            'room_category.hidden',
            'room_category.restored',
        ], AuditLog::query()->orderBy('id')->pluck('action')->all());
    }

    public function test_category_audit_failure_rolls_back_creation(): void
    {
        AuditLog::creating(function (): void {
            throw new RuntimeException('Forced catalog audit failure.');
        });

        try {
            $response = $this->actingAs($this->userWithRoles(['ADMIN']))
                ->post(route('admin.categories.store'), ['name' => 'Căn hộ mới']);
        } finally {
            Event::forget('eloquent.creating: '.AuditLog::class);
        }

        $response->assertStatus(500);
        $this->assertDatabaseMissing('room_categories', ['name' => 'Căn hộ mới']);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_amenity_audit_failure_rolls_back_hide(): void
    {
        $amenity = Amenity::query()->create(['name' => 'Máy sấy', 'is_active' => true]);
        AuditLog::creating(function (): void {
            throw new RuntimeException('Forced catalog audit failure.');
        });

        try {
            $response = $this->actingAs($this->userWithRoles(['SUPER_ADMIN']))
                ->patch(route('admin.amenities.hide', $amenity));
        } finally {
            Event::forget('eloquent.creating: '.AuditLog::class);
        }

        $response->assertStatus(500);
        $this->assertTrue($amenity->fresh()->is_active);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_known_catalog_duplicate_key_race_returns_friendly_validation_error(): void
    {
        RoomCategory::creating(function (RoomCategory $category): void {
            if ($category->name === 'Cạnh tranh') {
                DB::table('room_categories')->insert([
                    'name' => 'Cạnh tranh',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        try {
            $this->actingAs($this->userWithRoles(['ADMIN']))
                ->from(route('admin.categories.index'))
                ->post(route('admin.categories.store'), ['name' => 'Cạnh tranh'])
                ->assertSessionHasErrors('name');
        } finally {
            Event::forget('eloquent.creating: '.RoomCategory::class);
        }

        $this->assertDatabaseMissing('room_categories', ['name' => 'Cạnh tranh']);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_amenity_lifecycle_preserves_existing_listing_pivot_and_audits_changes(): void
    {
        $amenity = Amenity::query()->create(['name' => 'Wi-Fi', 'is_active' => true]);
        $listing = $this->listing($this->userWithRoles(['LANDLORD']));
        $listing->amenities()->attach($amenity->id);
        $admin = $this->userWithRoles(['SUPER_ADMIN']);

        $this->actingAs($admin)
            ->put(route('admin.amenities.update', $amenity), [
                'name' => '  Wi-Fi   tốc độ cao ',
                'description' => 'Mạng không dây',
            ])
            ->assertRedirect();
        $this->actingAs($admin)
            ->put(route('admin.amenities.update', $amenity), [
                'name' => 'Wi-Fi tốc độ cao',
                'description' => 'Mạng không dây',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('listing_amenities', [
            'listing_id' => $listing->id,
            'amenity_id' => $amenity->id,
        ]);

        $this->actingAs($admin)->patch(route('admin.amenities.hide', $amenity))->assertRedirect();
        $this->actingAs($admin)->patch(route('admin.amenities.hide', $amenity))->assertRedirect();
        $this->assertFalse($amenity->fresh()->is_active);
        $this->assertSame(1, $listing->moderations()->count());

        $this->actingAs($admin)->patch(route('admin.amenities.restore', $amenity))->assertRedirect();
        $this->actingAs($admin)->patch(route('admin.amenities.restore', $amenity))->assertRedirect();
        $this->assertTrue($amenity->fresh()->is_active);

        $this->assertSame([
            'amenity.updated',
            'amenity.hidden',
            'amenity.restored',
        ], AuditLog::query()->orderBy('id')->pluck('action')->all());
    }

    public function test_catalog_database_unique_indexes_reject_duplicate_names(): void
    {
        RoomCategory::query()->create(['name' => 'Căn hộ', 'is_active' => true]);
        $this->expectException(QueryException::class);
        RoomCategory::query()->create(['name' => 'căn hộ', 'is_active' => false]);
    }

    public function test_amenity_database_unique_index_rejects_duplicate_names(): void
    {
        Amenity::query()->create(['name' => 'Wi-Fi', 'is_active' => true]);

        $this->expectException(QueryException::class);
        Amenity::query()->create(['name' => 'wi-fi', 'is_active' => false]);
    }

    public function test_room_category_seeder_inserts_missing_rows_without_overwriting_admin_state_or_names(): void
    {
        $this->category->forceFill(['name' => 'Phòng thuê', 'is_active' => false])->save();

        $this->seed(RoomCategorySeeder::class);
        $this->category->refresh();

        $this->assertSame('Phòng thuê', $this->category->name);
        $this->assertFalse($this->category->is_active);
        $this->assertDatabaseHas('room_categories', ['name' => 'Phòng trọ', 'is_active' => true]);
        $this->assertDatabaseHas('room_categories', ['name' => 'Căn hộ mini', 'is_active' => true]);
    }
}
