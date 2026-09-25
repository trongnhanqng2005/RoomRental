<?php

namespace App\Services;

use App\Models\Amenity;
use App\Models\AuditLog;
use App\Models\RoomCategory;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatalogAdministrationService
{
    /** @param array{name: string, description?: string|null} $data */
    public function createCategory(array $data, User $actor): RoomCategory
    {
        try {
            return DB::transaction(function () use ($data, $actor): RoomCategory {
                $category = RoomCategory::query()->create([
                    'name' => $this->normalizeName($data['name']),
                    'description' => $data['description'] ?? null,
                    'is_active' => true,
                ]);

                $this->audit($actor, RoomCategory::class, $category->id, 'room_category.created');

                return $category;
            });
        } catch (QueryException $exception) {
            $this->translateDuplicate($exception, 'room_categories_name_unique');
        }
    }

    /** @param array{name: string, description?: string|null} $data */
    public function updateCategory(RoomCategory $requested, array $data, User $actor): RoomCategory
    {
        try {
            return DB::transaction(function () use ($requested, $data, $actor): RoomCategory {
                $category = RoomCategory::query()->lockForUpdate()->findOrFail($requested->id);
                $name = $this->normalizeName($data['name']);
                $description = $data['description'] ?? null;

                if ($category->name === $name && $category->description === $description) {
                    return $category;
                }

                $category->forceFill(['name' => $name, 'description' => $description])->save();
                $this->audit($actor, RoomCategory::class, $category->id, 'room_category.updated');

                return $category;
            });
        } catch (QueryException $exception) {
            $this->translateDuplicate($exception, 'room_categories_name_unique');
        }
    }

    public function setCategoryActive(RoomCategory $requested, bool $active, User $actor): void
    {
        DB::transaction(function () use ($requested, $active, $actor): void {
            $category = RoomCategory::query()->lockForUpdate()->findOrFail($requested->id);

            if ((bool) $category->is_active === $active) {
                return;
            }

            $category->forceFill(['is_active' => $active])->save();
            $this->audit(
                $actor,
                RoomCategory::class,
                $category->id,
                $active ? 'room_category.restored' : 'room_category.hidden',
            );
        });
    }

    /** @param array{name: string, description?: string|null} $data */
    public function createAmenity(array $data, User $actor): Amenity
    {
        try {
            return DB::transaction(function () use ($data, $actor): Amenity {
                $amenity = Amenity::query()->create([
                    'name' => $this->normalizeName($data['name']),
                    'description' => $data['description'] ?? null,
                    'is_active' => true,
                ]);

                $this->audit($actor, Amenity::class, $amenity->id, 'amenity.created');

                return $amenity;
            });
        } catch (QueryException $exception) {
            $this->translateDuplicate($exception, 'amenities_name_unique');
        }
    }

    /** @param array{name: string, description?: string|null} $data */
    public function updateAmenity(Amenity $requested, array $data, User $actor): Amenity
    {
        try {
            return DB::transaction(function () use ($requested, $data, $actor): Amenity {
                $amenity = Amenity::query()->lockForUpdate()->findOrFail($requested->id);
                $name = $this->normalizeName($data['name']);
                $description = $data['description'] ?? null;

                if ($amenity->name === $name && $amenity->description === $description) {
                    return $amenity;
                }

                $amenity->forceFill(['name' => $name, 'description' => $description])->save();
                $this->audit($actor, Amenity::class, $amenity->id, 'amenity.updated');

                return $amenity;
            });
        } catch (QueryException $exception) {
            $this->translateDuplicate($exception, 'amenities_name_unique');
        }
    }

    public function setAmenityActive(Amenity $requested, bool $active, User $actor): void
    {
        DB::transaction(function () use ($requested, $active, $actor): void {
            $amenity = Amenity::query()->lockForUpdate()->findOrFail($requested->id);

            if ((bool) $amenity->is_active === $active) {
                return;
            }

            $amenity->forceFill(['is_active' => $active])->save();
            $this->audit(
                $actor,
                Amenity::class,
                $amenity->id,
                $active ? 'amenity.restored' : 'amenity.hidden',
            );
        });
    }

    private function normalizeName(string $name): string
    {
        $trimmed = preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $name);
        $trimmed ??= trim($name);
        $normalized = preg_replace('/[\s\p{Z}]+/u', ' ', $trimmed);

        return $normalized ?? $trimmed;
    }

    private function audit(User $actor, string $entityType, int $entityId, string $action): void
    {
        AuditLog::query()->create([
            'actor_user_id' => $actor->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'created_at' => now(),
        ]);
    }

    private function translateDuplicate(QueryException $exception, string $constraint): never
    {
        $constraintName = null;

        if (preg_match("/for key [`']([^`']+)[`']/", $exception->getMessage(), $matches) === 1) {
            $constraintParts = explode('.', $matches[1]);
            $constraintName = $constraintParts[count($constraintParts) - 1];
        }

        if (($exception->errorInfo[1] ?? null) !== 1062 || $constraintName !== $constraint) {
            throw $exception;
        }

        throw ValidationException::withMessages(['name' => __('validation.catalog_name_unique')]);
    }
}
