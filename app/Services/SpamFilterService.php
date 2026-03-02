<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\EmailAccount;
use Exception;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class SpamFilterService
{
    protected DeploymentDetectionService $detectionService;
    protected StandaloneServiceHelper $standaloneHelper;

    public function __construct(
        DeploymentDetectionService $detectionService,
        StandaloneServiceHelper $standaloneHelper
    ) {
        $this->detectionService = $detectionService;
        $this->standaloneHelper = $standaloneHelper;
    }

    /**
     * Configure SpamAssassin for an email account
     */
    public function configureSpamAssassin(EmailAccount $emailAccount): bool
    {
        try {
            $userPrefsPath = $this->getSpamAssassinUserPrefsPath($emailAccount);
            $content = $this->generateSpamAssassinUserPrefs($emailAccount);

            if ($this->detectionService->isStandalone()) {
                $dir = dirname($userPrefsPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                file_put_contents($userPrefsPath, $content);
            } else {
                $process = new Process([
                    'docker', 'exec', 'spamassassin',
                    'bash', '-c',
                    "mkdir -p $(dirname {$userPrefsPath}) && cat > {$userPrefsPath}",
                ]);
                $process->setInput($content);
                $process->run();
            }

            return true;
        } catch (Exception $e) {
            Log::error("Failed to configure SpamAssassin for {$emailAccount->email_address}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enable or disable SpamAssassin for an email account
     */
    public function setSpamFilterEnabled(EmailAccount $emailAccount, bool $enabled): bool
    {
        $emailAccount->update(['spam_filter_enabled' => $enabled]);

        if ($enabled) {
            return $this->configureSpamAssassin($emailAccount);
        }

        // Remove user prefs when disabled
        try {
            $userPrefsPath = $this->getSpamAssassinUserPrefsPath($emailAccount);
            if ($this->detectionService->isStandalone() && file_exists($userPrefsPath)) {
                unlink($userPrefsPath);
            }
        } catch (Exception $e) {
            Log::warning("Could not remove SpamAssassin prefs for {$emailAccount->email_address}: " . $e->getMessage());
        }

        return true;
    }

    /**
     * Update spam threshold and action for an email account
     */
    public function updateSpamSettings(EmailAccount $emailAccount, int $threshold, string $action): bool
    {
        $emailAccount->update([
            'spam_threshold' => $threshold,
            'spam_action'    => $action,
        ]);

        if ($emailAccount->spam_filter_enabled) {
            return $this->configureSpamAssassin($emailAccount);
        }

        return true;
    }

    /**
     * Check if SpamAssassin is installed and available
     */
    public function isSpamAssassinAvailable(): bool
    {
        if ($this->detectionService->isStandalone()) {
            $process = new Process(['which', 'spamc']);
            $process->run();
            return $process->isSuccessful();
        }

        $process = new Process(['docker', 'inspect', '--format', '{{.State.Running}}', 'spamassassin']);
        $process->run();
        return trim($process->getOutput()) === 'true';
    }

    /**
     * Configure ClamAV virus scanning for a domain
     */
    public function configureClamAV(Domain $domain): bool
    {
        if (!$this->isClamAVAvailable()) {
            Log::info('ClamAV is not available, skipping virus scan configuration');
            return false;
        }

        try {
            $scanScript = $this->generateClamScanScript($domain);
            $scriptPath = "/etc/clamav/scan-{$domain->domain_name}.sh";

            if ($this->detectionService->isStandalone()) {
                file_put_contents($scriptPath, $scanScript);
                chmod($scriptPath, 0755);
            } else {
                $process = new Process([
                    'docker', 'exec', 'clamav',
                    'bash', '-c', "cat > {$scriptPath} && chmod +x {$scriptPath}",
                ]);
                $process->setInput($scanScript);
                $process->run();
            }

            return true;
        } catch (Exception $e) {
            Log::error("Failed to configure ClamAV for {$domain->domain_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Run a ClamAV scan for a domain's mail directory
     */
    public function scanMailDirectory(Domain $domain): array
    {
        if (!$this->isClamAVAvailable()) {
            return ['success' => false, 'message' => 'ClamAV is not available'];
        }

        try {
            $mailDir = "/var/mail/virtual/{$domain->domain_name}";
            $command = $this->detectionService->isStandalone()
                ? ['clamscan', '-r', '--infected', '--no-summary', $mailDir]
                : ['docker', 'exec', 'clamav', 'clamscan', '-r', '--infected', '--no-summary', $mailDir];

            $process = new Process($command);
            $process->setTimeout(300);
            $process->run();

            $infected = [];
            foreach (explode("\n", $process->getOutput()) as $line) {
                if (str_contains($line, 'FOUND')) {
                    $infected[] = trim($line);
                }
            }

            return [
                'success'       => true,
                'infected_files' => $infected,
                'clean'         => empty($infected),
            ];
        } catch (Exception $e) {
            Log::error("ClamAV scan failed for {$domain->domain_name}: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Check if ClamAV is installed and available
     */
    public function isClamAVAvailable(): bool
    {
        if ($this->detectionService->isStandalone()) {
            $process = new Process(['which', 'clamscan']);
            $process->run();
            return $process->isSuccessful();
        }

        $process = new Process(['docker', 'inspect', '--format', '{{.State.Running}}', 'clamav']);
        $process->run();
        return trim($process->getOutput()) === 'true';
    }

    /**
     * Generate SpamAssassin user_prefs content
     */
    protected function generateSpamAssassinUserPrefs(EmailAccount $emailAccount): string
    {
        $threshold = $emailAccount->spam_threshold ?? 5;
        $action    = $emailAccount->spam_action   ?? 'flag';

        $lines = [
            "# SpamAssassin user preferences for {$emailAccount->email_address}",
            "required_score {$threshold}",
        ];

        if ($action === 'delete') {
            $lines[] = 'discard_below_threshold 1';
        } elseif ($action === 'folder') {
            $lines[] = 'move_to_spam_folder 1';
        } else {
            // 'flag' – add X-Spam headers only
            $lines[] = 'rewrite_header Subject [SPAM]';
            $lines[] = 'add_header all Status _YESNO_, hits=_SCORE_ required=_REQD_ tests=_TESTS_';
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Get the path for SpamAssassin user prefs file
     */
    protected function getSpamAssassinUserPrefsPath(EmailAccount $emailAccount): string
    {
        $localPart = explode('@', $emailAccount->email_address)[0];
        $domain    = $emailAccount->domain->domain_name ?? 'default';
        return "/var/mail/virtual/{$domain}/{$localPart}/.spamassassin/user_prefs";
    }

    /**
     * Generate a ClamAV scan shell script for a domain
     */
    protected function generateClamScanScript(Domain $domain): string
    {
        $mailDir = "/var/mail/virtual/{$domain->domain_name}";
        return <<<BASH
#!/bin/bash
# ClamAV scan script for {$domain->domain_name}
MAIL_DIR="{$mailDir}"
LOG_FILE="/var/log/clamav/{$domain->domain_name}-scan.log"

clamscan -r --infected --no-summary "\$MAIL_DIR" >> "\$LOG_FILE" 2>&1
echo "Scan completed at \$(date)" >> "\$LOG_FILE"
BASH;
    }
}
