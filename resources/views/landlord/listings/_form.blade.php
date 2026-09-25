@php
    $isEdit = isset($listing);
    $currentListing = $isEdit ? $listing : null;
    $formAction = $isEdit ? route('landlord.listings.update', $currentListing) : route('landlord.listings.store');
    $oldAmenityIds = old('amenity_ids_submitted') !== null
        ? (array) old('amenity_ids', [])
        : old('amenity_ids', $currentListing?->amenities->pluck('id')->all() ?? []);
    $oldFees = old('fees', $currentListing?->fees->map(fn ($fee) => [
        'fee_type_id' => $fee->fee_type_id,
        'fee_unit_id' => $fee->fee_unit_id,
        'amount' => $fee->amount,
        'note' => $fee->note,
    ])->keyBy('fee_type_id')->all() ?? []);
    $feeByType = collect($oldFees)->keyBy(fn ($fee) => (string) ($fee['fee_type_id'] ?? ''));
    $retainedImageIds = $isEdit
        ? array_map('intval', old('existing_images', $currentListing->images->pluck('id')->all()))
        : [];
    $currentCoverId = $currentListing?->images->firstWhere('is_cover', true)?->id;
    $coverSelection = old('cover_selection', $isEdit ? 'existing:'.$currentCoverId : 'new:0');
@endphp

<form
    class="space-y-8"
    method="POST"
    action="{{ $formAction }}"
    enctype="multipart/form-data"
    novalidate
    data-submit-once
    data-listing-form
    data-cover-label="{{ __('ui.listings.cover') }}"
    data-remove-label="{{ __('ui.listings.remove_image') }}"
    data-move-up-label="{{ __('ui.listings.move_up') }}"
    data-move-down-label="{{ __('ui.listings.move_down') }}"
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-ui.error-summary
        :fields="[
            'category_id' => __('ui.listings.category'),
            'title' => __('ui.listings.title'),
            'description' => __('ui.listings.description'),
            'monthly_rent' => __('ui.listings.monthly_rent'),
            'area_m2' => __('ui.listings.area_m2'),
            'province_id' => __('ui.listings.province'),
            'district_id' => __('ui.listings.district'),
            'ward_id' => __('ui.listings.ward'),
            'amenity_ids' => __('ui.listings.amenities'),
            'fees' => __('ui.listings.fees'),
            'images' => __('ui.listings.images'),
            'existing_images' => __('ui.listings.images'),
            'new_images' => __('ui.listings.images'),
            'cover_selection' => __('ui.listings.cover_image'),
        ]"
        id="listing-errors"
    />

    <fieldset class="space-y-5">
        <legend class="text-xl font-bold tracking-[-0.03em] text-slate-950">{{ __('ui.listings.basic_section') }}</legend>
        <p class="text-sm leading-6 text-slate-500">{{ __('ui.listings.basic_description') }}</p>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-ui.input
                name="title"
                :label="__('ui.listings.title')"
                :value="$currentListing?->title"
                maxlength="255"
                icon="type"
                required
            />

            <x-ui.select
                name="category_id"
                :label="__('ui.listings.category')"
                :options="$categories->mapWithKeys(fn ($category) => [$category->id => $category->is_active ? $category->name : $category->name.' · '.__('ui.listings.currently_hidden')])->all()"
                :value="$currentListing?->category_id"
                :placeholder="__('ui.listings.select_category')"
                required
            />
        </div>

        <x-ui.textarea
            name="description"
            :label="__('ui.listings.description')"
            :value="$currentListing?->description"
            :hint="__('ui.listings.basic_description')"
            rows="6"
            required
        />

        <fieldset>
            <legend class="mb-3 block text-sm font-bold text-slate-800">{{ __('ui.listings.gender_requirement') }}</legend>
            <div class="grid gap-3 sm:grid-cols-3">
                @foreach ([['ANY', __('ui.listings.any_gender')], ['MALE', __('ui.listings.male_only')], ['FEMALE', __('ui.listings.female_only')]] as [$value, $label])
                    <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-control border border-line bg-white px-4 py-3 text-sm font-semibold text-slate-700 has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-800">
                        <input class="size-4 accent-brand-600" type="radio" name="gender_requirement" value="{{ $value }}" @checked(old('gender_requirement', $currentListing?->gender_requirement ?? 'ANY') === $value)>
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            @error('gender_requirement')
                <p class="mt-2 text-sm font-medium text-danger-700">{{ $message }}</p>
            @enderror
        </fieldset>
    </fieldset>

    <div class="border-t border-line"></div>

    <fieldset class="space-y-5">
        <legend class="text-xl font-bold tracking-[-0.03em] text-slate-950">{{ __('ui.listings.room_section') }}</legend>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <x-ui.input name="monthly_rent" :label="__('ui.listings.monthly_rent')" type="number" :value="$currentListing?->monthly_rent" inputmode="decimal" step="0.01" min="0" required />
            <x-ui.input name="deposit_amount" :label="__('ui.listings.deposit_amount')" type="number" :value="$currentListing?->deposit_amount" inputmode="decimal" step="0.01" min="0" />
            <x-ui.input name="area_m2" :label="__('ui.listings.area_m2')" type="number" :value="$currentListing?->area_m2" inputmode="decimal" step="0.01" min="0.01" required />
            <x-ui.input name="max_occupants" :label="__('ui.listings.max_occupants')" type="number" :value="$currentListing?->max_occupants" inputmode="numeric" min="1" required />
            <x-ui.input name="bedroom_count" :label="__('ui.listings.bedroom_count')" type="number" :value="$currentListing?->bedroom_count" inputmode="numeric" min="0" required />
            <x-ui.input name="bathroom_count" :label="__('ui.listings.bathroom_count')" type="number" :value="$currentListing?->bathroom_count" inputmode="numeric" min="0" required />
        </div>
    </fieldset>

    <div class="border-t border-line"></div>

    <fieldset class="space-y-5">
        <legend class="text-xl font-bold tracking-[-0.03em] text-slate-950">{{ __('ui.listings.address_section') }}</legend>
        <p class="text-sm leading-6 text-slate-500">{{ __('ui.listings.address_description') }}</p>

        @if ($provinces->isEmpty())
            <div class="rounded-control border border-warning-100 bg-warning-50 p-4 text-sm font-semibold leading-6 text-warning-700" role="status">{{ __('ui.listings.no_locations') }}</div>
        @endif

        <div class="grid gap-5 md:grid-cols-3">
            <div>
                <label class="mb-2 block text-sm font-bold text-slate-800" for="province_id">{{ __('ui.listings.province') }}</label>
                <select class="field-control" id="province_id" name="province_id" data-location="province" required @if ($errors->has('province_id')) aria-invalid="true" @endif>
                    <option value="">{{ __('ui.listings.select_province') }}</option>
                    @foreach ($provinces as $province)
                        <option value="{{ $province->id }}" @selected((string) old('province_id', $selectedProvinceId) === (string) $province->id)>{{ $province->name }}</option>
                    @endforeach
                </select>
                @error('province_id') <p class="mt-2 text-sm font-medium text-danger-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-2 block text-sm font-bold text-slate-800" for="district_id">{{ __('ui.listings.district') }}</label>
                <select class="field-control" id="district_id" name="district_id" data-location="district" required @if ($errors->has('district_id')) aria-invalid="true" @endif>
                    <option value="">{{ __('ui.listings.select_district') }}</option>
                    @foreach ($provinces as $province)
                        @foreach ($province->districts as $district)
                            <option value="{{ $district->id }}" data-location-parent="{{ $province->id }}" @selected((string) old('district_id', $selectedDistrictId) === (string) $district->id)>{{ $district->name }}</option>
                        @endforeach
                    @endforeach
                </select>
                @error('district_id') <p class="mt-2 text-sm font-medium text-danger-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-2 block text-sm font-bold text-slate-800" for="ward_id">{{ __('ui.listings.ward') }}</label>
                <select class="field-control" id="ward_id" name="ward_id" data-location="ward" required @if ($errors->has('ward_id')) aria-invalid="true" @endif>
                    <option value="">{{ __('ui.listings.select_ward') }}</option>
                    @foreach ($provinces as $province)
                        @foreach ($province->districts as $district)
                            @foreach ($district->wards as $ward)
                                <option value="{{ $ward->id }}" data-location-parent="{{ $district->id }}" @selected((string) old('ward_id', $currentListing?->ward_id) === (string) $ward->id)>{{ $ward->name }}</option>
                            @endforeach
                        @endforeach
                    @endforeach
                </select>
                @error('ward_id') <p class="mt-2 text-sm font-medium text-danger-700">{{ $message }}</p> @enderror
            </div>
        </div>

        <x-ui.input name="street_address" :label="__('ui.listings.street_address')" :value="$currentListing?->street_address" maxlength="500" autocomplete="street-address" icon="map-pinned" required />

        <details class="rounded-control border border-line bg-slate-50 p-4">
            <summary class="cursor-pointer text-sm font-bold text-slate-800">{{ __('ui.listings.latitude') }} / {{ __('ui.listings.longitude') }}</summary>
            <div class="mt-4 grid gap-5 sm:grid-cols-2">
                <x-ui.input name="latitude" :label="__('ui.listings.latitude')" type="number" :value="$currentListing?->latitude" inputmode="decimal" step="0.0000001" min="-90" max="90" />
                <x-ui.input name="longitude" :label="__('ui.listings.longitude')" type="number" :value="$currentListing?->longitude" inputmode="decimal" step="0.0000001" min="-180" max="180" />
            </div>
        </details>
    </fieldset>

    <div class="border-t border-line"></div>

    <fieldset class="space-y-5">
        <legend class="text-xl font-bold tracking-[-0.03em] text-slate-950">{{ __('ui.listings.amenities_section') }}</legend>
        <p class="text-sm leading-6 text-slate-500">{{ __('ui.listings.amenities_description') }}</p>
        <input type="hidden" name="amenity_ids_submitted" value="1">

        @if ($amenities->isEmpty())
            <p class="rounded-control border border-line bg-slate-50 p-4 text-sm leading-6 text-slate-500">{{ __('ui.listings.no_amenities') }}</p>
        @else
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($amenities as $amenity)
                    <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-control border border-line bg-white px-4 py-3 text-sm font-semibold text-slate-700 has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-800">
                        <input class="size-4 accent-brand-600" type="checkbox" name="amenity_ids[]" value="{{ $amenity->id }}" @checked(in_array($amenity->id, array_map('intval', (array) $oldAmenityIds), true))>
                        <span>{{ $amenity->name }}@unless ($amenity->is_active) · {{ __('ui.listings.currently_hidden') }}@endunless</span>
                    </label>
                @endforeach
            </div>
        @endif
        @error('amenity_ids') <p class="text-sm font-medium text-danger-700">{{ $message }}</p> @enderror
    </fieldset>

    <div class="border-t border-line"></div>

    <fieldset class="space-y-5">
        <legend class="text-xl font-bold tracking-[-0.03em] text-slate-950">{{ __('ui.listings.fees_section') }}</legend>
        <p class="text-sm leading-6 text-slate-500">{{ __('ui.listings.fees_description') }}</p>

        <div class="space-y-3">
            @foreach ($feeTypes as $feeType)
                @php
                    $fee = $feeByType->get((string) $feeType->id, []);
                    $feeEnabled = $fee !== [];
                @endphp
                <div class="rounded-control border border-line bg-white p-4" data-fee-row>
                    <label class="flex min-h-11 items-center gap-3 text-sm font-bold text-slate-800">
                        <input class="size-4 accent-brand-600" type="checkbox" data-fee-toggle @checked($feeEnabled)>
                        <span>{{ $feeType->name }}</span>
                        <span class="ml-auto text-xs font-medium text-slate-400">{{ __('ui.listings.enable_fee') }}</span>
                    </label>
                    <div class="mt-4 grid gap-4 sm:grid-cols-3" data-fee-fields>
                        <input type="hidden" name="fees[{{ $feeType->id }}][fee_type_id]" value="{{ $feeType->id }}" @disabled(! $feeEnabled)>
                        <div>
                            <label class="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500" for="fee-{{ $feeType->id }}-unit">{{ __('ui.listings.fee_unit') }}</label>
                            <select class="field-control" id="fee-{{ $feeType->id }}-unit" name="fees[{{ $feeType->id }}][fee_unit_id]" @disabled(! $feeEnabled)>
                                <option value="">{{ __('ui.listings.fee_unit_placeholder') }}</option>
                                @foreach ($feeUnits as $feeUnit)
                                    <option value="{{ $feeUnit->id }}" @selected((string) ($fee['fee_unit_id'] ?? '') === (string) $feeUnit->id)>{{ $feeUnit->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500" for="fee-{{ $feeType->id }}-amount">{{ __('ui.listings.fee_amount') }}</label>
                            <input class="field-control" id="fee-{{ $feeType->id }}-amount" name="fees[{{ $feeType->id }}][amount]" type="number" min="0" step="0.01" value="{{ $fee['amount'] ?? '' }}" @disabled(! $feeEnabled)>
                        </div>
                        <div>
                            <label class="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500" for="fee-{{ $feeType->id }}-note">{{ __('ui.listings.fee_note') }}</label>
                            <input class="field-control" id="fee-{{ $feeType->id }}-note" name="fees[{{ $feeType->id }}][note]" maxlength="500" value="{{ $fee['note'] ?? '' }}" @disabled(! $feeEnabled)>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        @error('fees') <p class="text-sm font-medium text-danger-700">{{ $message }}</p> @enderror
    </fieldset>

    <div class="border-t border-line"></div>

    <fieldset class="space-y-5" id="listing-images">
        <legend class="text-xl font-bold tracking-[-0.03em] text-slate-950">{{ __('ui.listings.images_section') }}</legend>
        <p class="text-sm leading-6 text-slate-500">{{ __('ui.listings.images_description') }}</p>

        <div class="flex flex-col gap-3 rounded-control border border-dashed border-brand-300 bg-brand-50/50 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <label class="block text-sm font-bold text-slate-800" for="listing-image-input">{{ __('ui.listings.choose_images') }}</label>
                <p class="mt-1 text-sm text-slate-500"><span>{{ __('ui.listings.image_count') }}: </span><strong data-image-count>0/8</strong></p>
            </div>
            <input class="field-control max-w-full sm:max-w-sm" id="listing-image-input" type="file" name="{{ $isEdit ? 'new_images[]' : 'images[]' }}" accept="image/jpeg,image/png,image/webp" multiple data-image-input>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-image-grid>
            @if ($isEdit)
                @foreach ($currentListing->images as $image)
                    @if (in_array((int) $image->id, $retainedImageIds, true))
                        <article class="listing-image-item group relative overflow-hidden rounded-control border border-line bg-slate-50" data-image-item data-image-kind="existing" data-image-id="{{ $image->id }}">
                            <img class="aspect-[4/3] w-full object-cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->image_url) }}" alt="{{ __('ui.listings.image') }} {{ $loop->iteration }}" loading="lazy">
                            <input type="hidden" name="existing_images[]" value="{{ $image->id }}">
                            <div class="flex items-center justify-between gap-2 border-t border-line bg-white p-2">
                                <label class="inline-flex min-h-10 items-center gap-2 text-xs font-bold text-slate-700">
                                    <input class="size-4 accent-brand-600" type="radio" name="cover_selection" value="existing:{{ $image->id }}" data-cover-radio @checked((string) $coverSelection === 'existing:'.$image->id)>
                                    <span>{{ __('ui.listings.cover') }}</span>
                                </label>
                                <button class="grid size-10 place-items-center rounded-control text-slate-500 transition hover:bg-danger-50 hover:text-danger-700" type="button" aria-label="{{ __('ui.listings.remove_image') }}" data-image-remove>
                                    <i class="size-4" data-lucide="trash-2"></i>
                                </button>
                            </div>
                            <div class="flex items-center justify-between border-t border-line px-2 py-1.5 text-xs text-slate-500">
                                <span><span data-image-order>{{ $loop->iteration }}</span> · {{ __('ui.listings.image') }}</span>
                                <span class="flex gap-1">
                                    <button class="grid size-8 place-items-center rounded-control hover:bg-slate-100" type="button" aria-label="{{ __('ui.listings.move_up') }}" data-image-move="up"><i class="size-4" data-lucide="chevron-up"></i></button>
                                    <button class="grid size-8 place-items-center rounded-control hover:bg-slate-100" type="button" aria-label="{{ __('ui.listings.move_down') }}" data-image-move="down"><i class="size-4" data-lucide="chevron-down"></i></button>
                                </span>
                            </div>
                        </article>
                    @endif
                @endforeach
            @endif
        </div>

        @foreach (['images', 'existing_images', 'new_images', 'cover_selection'] as $imageError)
            @error($imageError) <p class="text-sm font-medium text-danger-700">{{ $message }}</p> @enderror
        @endforeach
    </fieldset>

    @if ($isEdit)
        <div class="rounded-control border border-warning-100 bg-warning-50 p-4 text-sm leading-6 text-warning-700" role="note">
            <i class="mr-1 inline-block size-4 align-[-0.15em]" data-lucide="clock-3"></i>
            {{ __('ui.listings.update_notice') }}
        </div>
    @endif

    <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:items-center sm:justify-between">
        <a class="inline-flex min-h-11 items-center justify-center rounded-control border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 shadow-sm transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800" href="{{ route('landlord.listings.index') }}">{{ __('ui.listings.back_to_listings') }}</a>
        <x-ui.button class="w-full sm:w-auto sm:min-w-48" type="submit" :data-loading-text="__('ui.listings.saving')">
            <i class="size-[18px]" data-lucide="check"></i>
            <span data-submit-label>{{ $isEdit ? __('ui.listings.save_changes') : __('ui.listings.submit') }}</span>
        </x-ui.button>
    </div>
</form>
