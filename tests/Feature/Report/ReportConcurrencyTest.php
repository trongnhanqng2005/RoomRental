<?php

namespace Tests\Feature\Report;

use App\Models\AuditLog;
use App\Models\EnforcementAction;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class ReportConcurrencyTest extends ReportFeatureTestCase
{
    public function test_concurrent_submissions_leave_exactly_one_pending_report(): void
    {
        $reporter = $this->userWithRoles(['RENTER']);
        $landlord = $this->userWithRoles(['LANDLORD']);
        $listing = $this->listing($landlord);
        $reasonId = $this->activeReasonId();
        $worker = <<<'PHP'
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[5], 'ready');

try {
    app(App\Services\ReportService::class)->submit(
        App\Models\User::query()->findOrFail((int) $argv[3]),
        (int) $argv[2],
        (int) $argv[4],
        'Báo cáo đồng thời.',
    );
    echo 'SUBMITTED';
} catch (Illuminate\Validation\ValidationException) {
    echo 'DUPLICATE';
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage());
    exit(1);
}
PHP;

        DB::commit();
        $outcomes = $this->runWorkersBehindRowLock('listings', $listing->id, [
            [$listing->id, $reporter->id, $reasonId],
            [$listing->id, $reporter->id, $reasonId],
        ], $worker);

        sort($outcomes);
        $this->assertSame(['DUPLICATE', 'SUBMITTED'], $outcomes);
        $this->assertSame(1, Report::query()
            ->where('reporter_id', $reporter->id)
            ->where('listing_id', $listing->id)
            ->where('status', 'PENDING')
            ->count());

        $this->cleanCommittedFixtures([$reporter, $landlord], $listing);
    }

    public function test_competing_admin_resolutions_produce_one_terminal_result_and_one_audit(): void
    {
        $reporter = $this->userWithRoles(['RENTER']);
        $landlord = $this->userWithRoles(['LANDLORD']);
        $adminOne = $this->userWithRoles(['ADMIN']);
        $adminTwo = $this->userWithRoles(['SUPER_ADMIN']);
        $listing = $this->listing($landlord);
        $report = $this->report($reporter, $listing);
        $worker = <<<'PHP'
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[5], 'ready');

try {
    $service = app(App\Services\ReportService::class);
    $admin = App\Models\User::query()->findOrFail((int) $argv[4]);

    if ($argv[2] === 'dismiss') {
        $service->dismiss(App\Models\Report::query()->findOrFail((int) $argv[3]), $admin, 'Không đủ thông tin.');
    } else {
        $service->resolve(App\Models\Report::query()->findOrFail((int) $argv[3]), $admin, 'WARNING', 'Cảnh báo hợp lệ.');
    }

    echo 'SUCCESS';
} catch (Symfony\Component\HttpKernel\Exception\ConflictHttpException) {
    echo 'STALE';
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage());
    exit(1);
}
PHP;

        DB::commit();
        $outcomes = $this->runWorkersBehindRowLock('reports', $report->id, [
            ['dismiss', $report->id, $adminOne->id],
            ['resolve', $report->id, $adminTwo->id],
        ], $worker);

        sort($outcomes);
        $this->assertSame(['STALE', 'SUCCESS'], $outcomes);
        $this->assertContains($report->fresh()->status, ['DISMISSED', 'RESOLVED']);
        $this->assertSame(
            $report->fresh()->status === 'RESOLVED' ? 1 : 0,
            EnforcementAction::query()->where('report_id', $report->id)->count(),
        );
        $this->assertSame(1, AuditLog::query()->where('entity_type', Report::class)->where('entity_id', $report->id)->count());

        $this->cleanCommittedFixtures([$reporter, $landlord, $adminOne, $adminTwo], $listing, $report);
    }

    /**
     * @param  array<int, array<int, int|string>>  $workerArguments
     * @return array<int, string>
     */
    private function runWorkersBehindRowLock(string $table, int $lockedId, array $workerArguments, string $script): array
    {
        $processes = [];
        $readyPaths = [];
        $lockHeld = false;

        try {
            DB::beginTransaction();
            $lockHeld = true;
            DB::table($table)->where('id', $lockedId)->lockForUpdate()->first();

            foreach ($workerArguments as $arguments) {
                $readyPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'roomrental-report-race-'.bin2hex(random_bytes(12));
                $readyPaths[] = $readyPath;
                $process = new Process([
                    PHP_BINARY,
                    '-r',
                    $script,
                    base_path(),
                    ...array_map('strval', $arguments),
                    $readyPath,
                ], base_path());
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }

            $readyDeadline = microtime(true) + 30;
            while (count(array_filter($readyPaths, 'is_file')) < count($readyPaths) && microtime(true) < $readyDeadline) {
                usleep(10_000);
            }

            $this->assertCount(count($readyPaths), array_filter($readyPaths, 'is_file'), 'All workers must reach the lock barrier.');
            usleep(100_000);
            DB::commit();
            $lockHeld = false;

            $outcomes = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                $outcomes[] = $process->getOutput();
            }

            return $outcomes;
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }

            foreach ($readyPaths as $readyPath) {
                if (is_file($readyPath)) {
                    unlink($readyPath);
                }
            }

            if ($lockHeld && DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }

    /** @param array<int, User> $users */
    private function cleanCommittedFixtures(array $users, Listing $listing, ?Report $report = null): void
    {
        if ($report) {
            DB::table('notifications')->where('entity_type', 'report')->where('entity_id', $report->id)->delete();
            DB::table('notifications')->where('entity_type', 'listing')->where('entity_id', $listing->id)->delete();
            DB::table('audit_logs')->where('entity_type', Report::class)->where('entity_id', $report->id)->delete();
            DB::table('enforcement_actions')->where('report_id', $report->id)->delete();
            DB::table('reports')->where('id', $report->id)->delete();
        } else {
            DB::table('reports')->where('listing_id', $listing->id)->delete();
        }

        DB::table('listings')->where('id', $listing->id)->update(['current_moderation_id' => null]);
        DB::table('listing_moderations')->where('listing_id', $listing->id)->delete();
        DB::table('listings')->where('id', $listing->id)->delete();
        $userIds = collect($users)->pluck('id')->all();
        DB::table('notifications')->whereIn('user_id', $userIds)->delete();
        DB::table('user_roles')->whereIn('user_id', $userIds)->delete();
        DB::table('user_profiles')->whereIn('user_id', $userIds)->delete();
        DB::table('users')->whereIn('id', $userIds)->delete();

        $districtId = $this->ward->district_id;
        $provinceId = DB::table('districts')->where('id', $districtId)->value('province_id');
        DB::table('wards')->where('id', $this->ward->id)->delete();
        DB::table('districts')->where('id', $districtId)->delete();
        DB::table('provinces')->where('id', $provinceId)->delete();
        DB::table('room_categories')->where('id', $this->category->id)->delete();
    }
}
