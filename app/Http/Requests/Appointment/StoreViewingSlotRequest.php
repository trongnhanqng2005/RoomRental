<?php

namespace App\Http\Requests\Appointment;

use App\Models\ViewingSlot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class StoreViewingSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageViewingSlots', $this->route('listing'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'viewing_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['viewing_date', 'start_time', 'end_time'])) {
                return;
            }

            $start = CarbonImmutable::parse(
                $this->string('viewing_date')->toString().' '.$this->string('start_time')->toString(),
                ViewingSlot::TIMEZONE,
            );
            $end = CarbonImmutable::parse(
                $this->string('viewing_date')->toString().' '.$this->string('end_time')->toString(),
                ViewingSlot::TIMEZONE,
            );

            if (! $start->lessThan($end)) {
                $validator->errors()->add('end_time', __('validation.viewing_slot_time_order'));
            }

            if ($start->lessThanOrEqualTo(CarbonImmutable::now(ViewingSlot::TIMEZONE))) {
                $validator->errors()->add('viewing_date', __('validation.viewing_slot_future'));
            }
        });
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'viewing_date' => __('ui.appointments.viewing_date'),
            'start_time' => __('ui.appointments.start_time'),
            'end_time' => __('ui.appointments.end_time'),
        ];
    }
}
