<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Models\Backup;
use App\Services\BackupService;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $backups = Backup::all();

        foreach ($backups as $backup) {
            $schedule->call(function () use ($backup) {
                try {
                    $backupService = new BackupService();
                    $backupService->createBackup($backup);
                    Log::info("Backup created successfully for {$backup->name}");
                } catch (\Exception $e) {
                    Log::error("Failed to create backup for {$backup->name}: " . $e->getMessage());
                }
            })->cron($this->getCronExpression($backup));
        }

        // Add a daily log cleanup task
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

    private function getCronExpression(Backup $backup): string
    {
        $time = $backup->time;
        $frequency = $backup->frequency;

        switch ($frequency) {
            case 'daily':
                return "{$time->format('i')} {$time->format('H')} * * *";
            case 'weekly':
                return "{$time->format('i')} {$time->format('H')} * * 0";
            case 'monthly':
                return "{$time->format('i')} {$time->format('H')} 1 * *";
            default:
                return "0 0 * * *"; // Default to daily at midnight
        }
    }
}
