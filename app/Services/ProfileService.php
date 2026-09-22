<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ProfileService
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(User $user, array $data): void
    {
        try {
            DB::transaction(function () use ($user, $data): void {
                $user = User::query()->lockForUpdate()->findOrFail($user->id);
                $profile = $user->profile()->lockForUpdate()->first();

                if (! $profile) {
                    throw new RuntimeException('The user profile is missing.');
                }

                foreach (['email', 'phone'] as $field) {
                    if (array_key_exists($field, $data)) {
                        $user->{$field} = $data[$field];
                    }
                }

                if ($user->email === null && $user->phone === null) {
                    throw ValidationException::withMessages([
                        'email' => __('validation.required_without', [
                            'attribute' => __('validation.attributes.email'),
                            'values' => __('validation.attributes.phone'),
                        ]),
                        'phone' => __('validation.required_without', [
                            'attribute' => __('validation.attributes.phone'),
                            'values' => __('validation.attributes.email'),
                        ]),
                    ]);
                }

                if ($user->isDirty()) {
                    $user->save();
                }

                $profileData = array_intersect_key($data, array_flip([
                    'full_name',
                    'contact_address',
                    'zalo_number',
                ]));

                if ($profileData !== []) {
                    $profile->fill($profileData);

                    if ($profile->isDirty()) {
                        $profile->save();
                    }
                }
            });
        } catch (QueryException $exception) {
            $this->throwDuplicateIdentifierValidationException($exception);

            throw $exception;
        }
    }

    /**
     * @throws ValidationException
     */
    private function throwDuplicateIdentifierValidationException(QueryException $exception): void
    {
        if ((int) ($exception->errorInfo[1] ?? 0) !== 1062) {
            return;
        }

        $field = match (true) {
            Str::contains($exception->getMessage(), 'users_email_unique') => 'email',
            Str::contains($exception->getMessage(), 'users_phone_unique') => 'phone',
            default => null,
        };

        if (! $field) {
            return;
        }

        throw ValidationException::withMessages([
            $field => __('validation.unique', ['attribute' => __('validation.attributes.'.$field)]),
        ]);
    }
}
