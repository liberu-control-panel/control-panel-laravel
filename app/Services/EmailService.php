<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\EmailAccount;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EmailService
{
    protected $containerManager;

    public function __construct(ContainerManagerService $containerManager)
    {
        $this->containerManager = $containerManager;
    }

    /**
     * Create email account
     */
    public function createEmailAccount(Domain $domain, array $data): EmailAccount
    {
        $emailAccount = EmailAccount::create([
            'user_id' => $domain->user_id,
            'domain_id' => $domain->id,
            'email_address' => $data['email_address'],
            'password' => Hash::make($data['password']),
            'quota' => $data['quota'] ?? 1024, // MB
            'forwarding_rules' => $data['forwarding_rules'] ?? []
        ]);

        // Create mailbox in Postfix/Dovecot
        $this->createMailboxInContainer($domain, $emailAccount, $data['password']);

        return $emailAccount;
    }

    /**
     * Create mailbox in container
     */
    protected function createMailboxInContainer(Domain $domain, EmailAccount $emailAccount, string $password): bool
    {
        try {
            // Create virtual mailbox entry
            $this->addVirtualMailbox($domain, $emailAccount);

            // Create virtual alias if needed
            $this->addVirtualAlias($domain, $emailAccount);

            // Create Dovecot user
            $this->createDovecotUser($domain, $emailAccount, $password);

            // Reload mail services
            $this->reloadMailServices($domain);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to create mailbox for {$emailAccount->email_address}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add virtual mailbox entry
     */
    protected function addVirtualMailbox(Domain $domain, EmailAccount $emailAccount): void
    {
        $virtualMailboxPath = "postfix/virtual_mailbox";
        $entry = "{$emailAccount->email_address} {$domain->domain_name}/{$emailAccount->email_address}/\n";

        // Read existing content
        $content = Storage::disk('local')->exists($virtualMailboxPath) 
            ? Storage::disk('local')->get($virtualMailboxPath) 
            : '';

        // Add new entry if not exists
        if (strpos($content, $emailAccount->email_address) === false) {
            $content .= $entry;
            Storage::disk('local')->put($virtualMailboxPath, $content);
        }
    }

    /**
     * Add virtual alias entry
     */
    protected function addVirtualAlias(Domain $domain, EmailAccount $emailAccount): void
    {
        $virtualAliasPath = "postfix/virtual_alias";
        $entry = "{$emailAccount->email_address} {$emailAccount->email_address}\n";

        // Read existing content
        $content = Storage::disk('local')->exists($virtualAliasPath) 
            ? Storage::disk('local')->get($virtualAliasPath) 
            : '';

        // Add new entry if not exists
        if (strpos($content, $emailAccount->email_address) === false) {
            $content .= $entry;
            Storage::disk('local')->put($virtualAliasPath, $content);
        }
    }

    /**
     * Create Dovecot user
     */
    protected function createDovecotUser(Domain $domain, EmailAccount $emailAccount, string $password): void
    {
        $dovecotUsersPath = "dovecot/users";
        $hashedPassword = $this->hashPasswordForDovecot($password);
        $entry = "{$emailAccount->email_address}:{$hashedPassword}::::\n";

        // Read existing content
        $content = Storage::disk('local')->exists($dovecotUsersPath) 
            ? Storage::disk('local')->get($dovecotUsersPath) 
            : '';

        // Add new entry if not exists
        if (strpos($content, $emailAccount->email_address) === false) {
            $content .= $entry;
            Storage::disk('local')->put($dovecotUsersPath, $content);
        }
    }

    /**
     * Hash password for Dovecot
     */
    protected function hashPasswordForDovecot(string $password): string
    {
        // Use SHA512-CRYPT for Dovecot
        return crypt($password, '$6$' . Str::random(16) . '$');
    }

    /**
     * Reload mail services
     */
    protected function reloadMailServices(Domain $domain): void
    {
        $postfixContainer = "postfix";
        $dovecotContainer = "dovecot";

        // Reload Postfix
        $postfixProcess = new Process(['docker', 'exec', $postfixContainer, 'postfix', 'reload']);
        $postfixProcess->run();

        // Reload Dovecot
        $dovecotProcess = new Process(['docker', 'exec', $dovecotContainer, 'doveadm', 'reload']);
        $dovecotProcess->run();
    }

    /**
     * Delete email account
     */
    public function deleteEmailAccount(EmailAccount $emailAccount): bool
    {
        try {
            $domain = $emailAccount->domain;

            // Remove from virtual mailbox
            $this->removeVirtualMailbox($emailAccount);

            // Remove from virtual alias
            $this->removeVirtualAlias($emailAccount);

            // Remove Dovecot user
            $this->removeDovecotUser($emailAccount);

            // Remove mailbox directory
            $this->removeMailboxDirectory($domain, $emailAccount);

            // Reload mail services
            $this->reloadMailServices($domain);

            // Delete from database
            $emailAccount->delete();

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete email account {$emailAccount->email_address}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove virtual mailbox entry
     */
    protected function removeVirtualMailbox(EmailAccount $emailAccount): void
    {
        $virtualMailboxPath = "postfix/virtual_mailbox";

        if (Storage::disk('local')->exists($virtualMailboxPath)) {
            $content = Storage::disk('local')->get($virtualMailboxPath);
            $lines = explode("\n", $content);
            $filteredLines = array_filter($lines, function($line) use ($emailAccount) {
                return strpos($line, $emailAccount->email_address) === false;
            });

            Storage::disk('local')->put($virtualMailboxPath, implode("\n", $filteredLines));
        }
    }

    /**
     * Remove virtual alias entry
     */
    protected function removeVirtualAlias(EmailAccount $emailAccount): void
    {
        $virtualAliasPath = "postfix/virtual_alias";

        if (Storage::disk('local')->exists($virtualAliasPath)) {
            $content = Storage::disk('local')->get($virtualAliasPath);
            $lines = explode("\n", $content);
            $filteredLines = array_filter($lines, function($line) use ($emailAccount) {
                return strpos($line, $emailAccount->email_address) === false;
            });

            Storage::disk('local')->put($virtualAliasPath, implode("\n", $filteredLines));
        }
    }

    /**
     * Remove Dovecot user
     */
    protected function removeDovecotUser(EmailAccount $emailAccount): void
    {
        $dovecotUsersPath = "dovecot/users";

        if (Storage::disk('local')->exists($dovecotUsersPath)) {
            $content = Storage::disk('local')->get($dovecotUsersPath);
            $lines = explode("\n", $content);
            $filteredLines = array_filter($lines, function($line) use ($emailAccount) {
                return strpos($line, $emailAccount->email_address) === false;
            });

            Storage::disk('local')->put($dovecotUsersPath, implode("\n", $filteredLines));
        }
    }

    /**
     * Remove mailbox directory
     */
    protected function removeMailboxDirectory(Domain $domain, EmailAccount $emailAccount): void
    {
        $dovecotContainer = "dovecot";
        $mailboxPath = "/var/mail/{$domain->domain_name}/{$emailAccount->email_address}";

        $process = new Process(['docker', 'exec', $dovecotContainer, 'rm', '-rf', $mailboxPath]);
        $process->run();
    }

    /**
     * Update email account password
     */
    public function updatePassword(EmailAccount $emailAccount, string $newPassword): bool
    {
        try {
            // Update in database
            $emailAccount->update(['password' => Hash::make($newPassword)]);

            // Update Dovecot user
            $this->updateDovecotPassword($emailAccount, $newPassword);

            // Reload Dovecot
            $dovecotProcess = new Process(['docker', 'exec', 'dovecot', 'doveadm', 'reload']);
            $dovecotProcess->run();

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to update password for {$emailAccount->email_address}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update Dovecot password
     */
    protected function updateDovecotPassword(EmailAccount $emailAccount, string $newPassword): void
    {
        $dovecotUsersPath = "dovecot/users";

        if (Storage::disk('local')->exists($dovecotUsersPath)) {
            $content = Storage::disk('local')->get($dovecotUsersPath);
            $lines = explode("\n", $content);
            $hashedPassword = $this->hashPasswordForDovecot($newPassword);

            $updatedLines = array_map(function($line) use ($emailAccount, $hashedPassword) {
                if (strpos($line, $emailAccount->email_address . ':') === 0) {
                    return "{$emailAccount->email_address}:{$hashedPassword}::::";
                }
                return $line;
            }, $lines);

            Storage::disk('local')->put($dovecotUsersPath, implode("\n", $updatedLines));
        }
    }

    /**
     * Create email alias
     */
    public function createEmailAlias(Domain $domain, string $alias, array $destinations): bool
    {
        try {
            $virtualAliasPath = "postfix/virtual_alias";
            $destinationList = implode(', ', $destinations);
            $entry = "{$alias} {$destinationList}\n";

            // Read existing content
            $content = Storage::disk('local')->exists($virtualAliasPath) 
                ? Storage::disk('local')->get($virtualAliasPath) 
                : '';

            // Add new entry if not exists
            if (strpos($content, $alias) === false) {
                $content .= $entry;
                Storage::disk('local')->put($virtualAliasPath, $content);

                // Reload Postfix
                $this->reloadMailServices($domain);

                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error("Failed to create email alias {$alias}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get mailbox usage
     */
    public function getMailboxUsage(EmailAccount $emailAccount): array
    {
        try {
            $domain = $emailAccount->domain;
            $dovecotContainer = "dovecot";
            $mailboxPath = "/var/mail/{$domain->domain_name}/{$emailAccount->email_address}";

            // Get mailbox size
            $sizeProcess = new Process(['docker', 'exec', $dovecotContainer, 'du', '-sb', $mailboxPath]);
            $sizeProcess->run();

            $usage = [
                'size_bytes' => 0,
                'size_human' => '0 B',
                'message_count' => 0,
                'quota_bytes' => $emailAccount->quota * 1024 * 1024, // Convert MB to bytes
                'quota_human' => $emailAccount->quota . ' MB'
            ];

            if ($sizeProcess->isSuccessful()) {
                $output = trim($sizeProcess->getOutput());
                if (preg_match('/^(\d+)/', $output, $matches)) {
                    $usage['size_bytes'] = (int) $matches[1];
                    $usage['size_human'] = $this->formatBytes($usage['size_bytes']);
                }
            }

            // Get message count
            $countProcess = new Process(['docker', 'exec', $dovecotContainer, 'find', $mailboxPath, '-name', '*.eml', '-type', 'f']);
            $countProcess->run();

            if ($countProcess->isSuccessful()) {
                $files = array_filter(explode("\n", trim($countProcess->getOutput())));
                $usage['message_count'] = count($files);
            }

            return $usage;
        } catch (\Exception $e) {
            Log::error("Failed to get mailbox usage for {$emailAccount->email_address}: " . $e->getMessage());
            return [
                'size_bytes' => 0,
                'size_human' => '0 B',
                'message_count' => 0,
                'quota_bytes' => $emailAccount->quota * 1024 * 1024,
                'quota_human' => $emailAccount->quota . ' MB'
            ];
        }
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Test email configuration
     */
    public function testEmailConfig(Domain $domain): array
    {
        try {
            $results = [
                'postfix' => $this->testPostfix(),
                'dovecot' => $this->testDovecot(),
                'dns' => $this->testEmailDNS($domain)
            ];

            return $results;
        } catch (\Exception $e) {
            Log::error("Failed to test email configuration for {$domain->domain_name}: " . $e->getMessage());
            return [
                'postfix' => ['status' => 'error', 'message' => $e->getMessage()],
                'dovecot' => ['status' => 'error', 'message' => $e->getMessage()],
                'dns' => ['status' => 'error', 'message' => $e->getMessage()]
            ];
        }
    }

    /**
     * Test Postfix
     */
    protected function testPostfix(): array
    {
        $process = new Process(['docker', 'exec', 'postfix', 'postfix', 'status']);
        $process->run();

        return [
            'status' => $process->isSuccessful() ? 'ok' : 'error',
            'message' => $process->isSuccessful() ? 'Postfix is running' : $process->getErrorOutput()
        ];
    }

    /**
     * Test Dovecot
     */
    protected function testDovecot(): array
    {
        $process = new Process(['docker', 'exec', 'dovecot', 'doveadm', 'service', 'status']);
        $process->run();

        return [
            'status' => $process->isSuccessful() ? 'ok' : 'error',
            'message' => $process->isSuccessful() ? 'Dovecot is running' : $process->getErrorOutput()
        ];
    }

    /**
     * Test email DNS records
     */
    protected function testEmailDNS(Domain $domain): array
    {
        $domainName = $domain->domain_name;
        $dnsTests = [];

        // Test MX record
        $mxProcess = new Process(['dig', '+short', 'MX', $domainName]);
        $mxProcess->run();

        $dnsTests['mx'] = [
            'status' => $mxProcess->isSuccessful() && !empty(trim($mxProcess->getOutput())) ? 'ok' : 'warning',
            'message' => $mxProcess->isSuccessful() ? 'MX record found' : 'MX record not found'
        ];

        // Test SPF record
        $spfProcess = new Process(['dig', '+short', 'TXT', $domainName]);
        $spfProcess->run();

        $spfFound = false;
        if ($spfProcess->isSuccessful()) {
            $txtRecords = explode("\n", trim($spfProcess->getOutput()));
            foreach ($txtRecords as $record) {
                if (strpos($record, 'v=spf1') !== false) {
                    $spfFound = true;
                    break;
                }
            }
        }

        $dnsTests['spf'] = [
            'status' => $spfFound ? 'ok' : 'warning',
            'message' => $spfFound ? 'SPF record found' : 'SPF record not found'
        ];

        return $dnsTests;
    }

    /**
     * Generate email statistics
     */
    public function getEmailStats(Domain $domain): array
    {
        try {
            $emailAccounts = $domain->emailAccounts;
            $totalQuota = $emailAccounts->sum('quota');
            $totalUsage = 0;
            $messageCount = 0;

            foreach ($emailAccounts as $account) {
                $usage = $this->getMailboxUsage($account);
                $totalUsage += $usage['size_bytes'];
                $messageCount += $usage['message_count'];
            }

            return [
                'account_count' => $emailAccounts->count(),
                'total_quota_mb' => $totalQuota,
                'total_usage_bytes' => $totalUsage,
                'total_usage_human' => $this->formatBytes($totalUsage),
                'total_messages' => $messageCount,
                'quota_usage_percent' => $totalQuota > 0 ? round(($totalUsage / ($totalQuota * 1024 * 1024)) * 100, 2) : 0
            ];
        } catch (\Exception $e) {
            Log::error("Failed to get email stats for {$domain->domain_name}: " . $e->getMessage());
            return [
                'account_count' => 0,
                'total_quota_mb' => 0,
                'total_usage_bytes' => 0,
                'total_usage_human' => '0 B',
                'total_messages' => 0,
                'quota_usage_percent' => 0
            ];
        }
    }
}