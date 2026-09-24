<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\AppointmentService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class AutoCancelOverdueAppointments extends Command
{
    protected $signature = 'appointments:auto-cancel';

    protected $description = 'Automatically cancel pending appointments whose viewing time has started.';

    public function handle(AppointmentService $appointmentService): int
    {
        $localNow = CarbonImmutable::now('Asia/Ho_Chi_Minh');
        $cancelled = 0;

        Appointment::query()
            ->where('status', 'PENDING')
            ->whereHas('slot', function ($query) use ($localNow): void {
                $query->where('viewing_date', '<', $localNow->toDateString())
                    ->orWhere(function ($sameDay) use ($localNow): void {
                        $sameDay->where('viewing_date', $localNow->toDateString())
                            ->whereTime('start_time', '<=', $localNow->format('H:i:s'));
                    });
            })
            ->orderBy('id')
            ->chunkById(100, function ($appointments) use ($appointmentService, &$cancelled): void {
                foreach ($appointments as $appointment) {
                    if ($appointmentService->autoCancelOverdue($appointment->id)) {
                        $cancelled++;
                    }
                }
            });

        $this->info(__('ui.appointments.scheduler_completed', ['count' => $cancelled]));

        return self::SUCCESS;
    }
}
