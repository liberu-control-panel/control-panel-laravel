<?php

namespace App\Console;

use Exception;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Models\BackupSchedule;
use App\Services\BackupService;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Run each active backup schedule according to its configured cron expression
        $schedules = BackupSchedule::active()->with('domain')->get();

        foreach ($schedules as $backupSchedule) {
            $schedule->call(function () use ($backupSchedule) {
                try {
                    /** @var BackupService $backupService */
                    $backupService = app(BackupService::class);
                    $backupService->createFullBackup($backupSchedule->domain, [
                        'type'           => $backupSchedule->type,
                        'name'           => $backupSchedule->name . ' - ' . now()->format('Y-m-d H:i:s'),
                        'destination_id' => $backupSchedule->destination_id,
                        'is_automated'   => true,
                    ]);
                    $backupSchedule->update(['last_run_at' => now()]);
                    Log::info("Scheduled backup '{$backupSchedule->name}' completed successfully.");
                } catch (Exception $e) {
                    Log::error("Scheduled backup '{$backupSchedule->name}' failed: " . $e->getMessage());
                }
            })->cron($backupSchedule->toCronExpression());
        }

        // Daily log cleanup task
        $schedule->command('log:clear')->daily();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
