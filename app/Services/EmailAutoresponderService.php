<?php

namespace App\Services;

use App\Models\EmailAccount;
use App\Models\EmailAlias;
use App\Models\Domain;
use Exception;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class EmailAutoresponderService
{
    /**
     * Setup autoresponder for an email account
     */
    public function setupAutoresponder(EmailAccount $emailAccount, array $data): EmailAccount
    {
        $emailAccount->update([
            'autoresponder_enabled' => $data['enabled'] ?? true,
            'autoresponder_subject' => $data['subject'] ?? 'Auto-reply',
            'autoresponder_message' => $data['message'],
            'autoresponder_start_date' => $data['start_date'] ?? now(),
            'autoresponder_end_date' => $data['end_date'] ?? null,
        ]);

        // Configure Dovecot/Sieve autoresponder
        $this->configureSieveAutoresponder($emailAccount);

        return $emailAccount;
    }

    /**
     * Disable autoresponder
     */
    public function disableAutoresponder(EmailAccount $emailAccount): EmailAccount
    {
        $emailAccount->update([
            'autoresponder_enabled' => false,
        ]);

        // Remove Sieve script
        $this->removeSieveAutoresponder($emailAccount);

        return $emailAccount;
    }

    /**
     * Configure Sieve autoresponder script
     */
    protected function configureSieveAutoresponder(EmailAccount $emailAccount): void
    {
        $sieveDir = storage_path("app/sieve/{$emailAccount->domain->domain_name}/{$emailAccount->email_address}");
        
        if (!file_exists($sieveDir)) {
            mkdir($sieveDir, 0755, true);
        }

        // Generate Sieve script
        $sieveScript = $this->generateSieveScript($emailAccount);
        
        $sieveFile = "{$sieveDir}/autoresponder.sieve";
        file_put_contents($sieveFile, $sieveScript);

        // Compile Sieve script
        $this->compileSieveScript($sieveFile);

        // Activate script as default
        $activeLink = "{$sieveDir}/.dovecot.sieve";
        if (file_exists($activeLink)) {
            unlink($activeLink);
        }
        symlink($sieveFile, $activeLink);
    }

    /**
     * Generate Sieve autoresponder script
     */
    protected function generateSieveScript(EmailAccount $emailAccount): string
    {
        $subject = addslashes($emailAccount->autoresponder_subject);
        $message = addslashes($emailAccount->autoresponder_message);
        $from = $emailAccount->email_address;

        $dateCondition = '';
        if ($emailAccount->autoresponder_start_date || $emailAccount->autoresponder_end_date) {
            $dateCondition = "if allof (\n";
            
            if ($emailAccount->autoresponder_start_date) {
                $startDate = $emailAccount->autoresponder_start_date->format('Y-m-d');
                $dateCondition .= "    currentdate :value \"ge\" \"date\" \"{$startDate}\",\n";
            }
            
            if ($emailAccount->autoresponder_end_date) {
                $endDate = $emailAccount->autoresponder_end_date->format('Y-m-d');
                $dateCondition .= "    currentdate :value \"le\" \"date\" \"{$endDate}\",\n";
            }
            
            $dateCondition = rtrim($dateCondition, ",\n") . "\n) {\n";
        }

        $script = <<<SIEVE
require ["vacation", "envelope", "date", "relational"];

# Autoresponder for {$from}
# Only reply to direct messages
if allof (
    not header :contains "precedence" ["list", "bulk", "junk"],
    not header :contains "X-Spam-Flag" "YES",
    not header :contains "Auto-Submitted" "auto-",
    header :contains "to" "{$from}"
) {
SIEVE;

        if ($dateCondition) {
            $script .= "\n    {$dateCondition}";
        }

        $script .= <<<SIEVE

    vacation
        :days 1
        :subject "{$subject}"
        :from "{$from}"
        :addresses ["{$from}"]
        "{$message}";
SIEVE;

        if ($dateCondition) {
            $script .= "\n    }";
        }

        $script .= "\n}\n";

        return $script;
    }

    /**
     * Compile Sieve script
     */
    protected function compileSieveScript(string $sieveFile): void
    {
        $process = new Process(['sievec', $sieveFile]);
        $process->run();

        if (!$process->isSuccessful()) {
            Log::warning("Failed to compile Sieve script: " . $process->getErrorOutput());
        }
    }

    /**
     * Remove Sieve autoresponder
     */
    protected function removeSieveAutoresponder(EmailAccount $emailAccount): void
    {
        $sieveDir = storage_path("app/sieve/{$emailAccount->domain->domain_name}/{$emailAccount->email_address}");
        $activeLink = "{$sieveDir}/.dovecot.sieve";
        
        if (file_exists($activeLink)) {
            unlink($activeLink);
        }
    }

    /**
     * Check if autoresponder should be active
     */
    public function isAutoresponderActive(EmailAccount $emailAccount): bool
    {
        if (!$emailAccount->autoresponder_enabled) {
            return false;
        }

        $now = now();

        if ($emailAccount->autoresponder_start_date && $now->lt($emailAccount->autoresponder_start_date)) {
            return false;
        }

        if ($emailAccount->autoresponder_end_date && $now->gt($emailAccount->autoresponder_end_date)) {
            return false;
        }

        return true;
    }
}

class EmailAliasService
{
    protected $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Create email alias
     */
    public function createAlias(Domain $domain, array $data): EmailAlias
    {
        $alias = EmailAlias::create([
            'user_id' => $domain->user_id,
            'domain_id' => $domain->id,
            'alias_address' => $data['alias_address'],
            'destination_addresses' => $data['destination_addresses'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        // Add to Postfix virtual alias map
        $this->addToVirtualAliasMap($alias);

        return $alias;
    }

    /**
     * Update email alias
     */
    public function updateAlias(EmailAlias $alias, array $data): EmailAlias
    {
        $wasActive = $alias->is_active;
        
        $alias->update($data);

        // Handle activation/deactivation
        if ($wasActive && !$alias->is_active) {
            $this->removeFromVirtualAliasMap($alias);
        } elseif (!$wasActive && $alias->is_active) {
            $this->addToVirtualAliasMap($alias);
        } elseif ($alias->is_active) {
            // Alias is active and was modified, update
            $this->removeFromVirtualAliasMap($alias);
            $this->addToVirtualAliasMap($alias);
        }

        return $alias;
    }

    /**
     * Delete email alias
     */
    public function deleteAlias(EmailAlias $alias): bool
    {
        try {
            if ($alias->is_active) {
                $this->removeFromVirtualAliasMap($alias);
            }

            $alias->delete();
            return true;
        } catch (Exception $e) {
            Log::error("Failed to delete email alias {$alias->alias_address}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add alias to Postfix virtual alias map
     */
    protected function addToVirtualAliasMap(EmailAlias $alias): void
    {
        $virtualAliasPath = storage_path('app/postfix/virtual_alias');
        
        if (!file_exists(dirname($virtualAliasPath))) {
            mkdir(dirname($virtualAliasPath), 0755, true);
        }

        $destinations = implode(', ', $alias->destination_addresses);
        $entry = "{$alias->alias_address} {$destinations}\n";

        // Check if alias already exists
        if (file_exists($virtualAliasPath)) {
            $content = file_get_contents($virtualAliasPath);
            if (strpos($content, $alias->alias_address) !== false) {
                // Remove old entry
                $this->removeFromVirtualAliasMap($alias);
            }
        }

        file_put_contents($virtualAliasPath, $entry, FILE_APPEND);

        // Reload Postfix
        $this->reloadPostfix();
    }

    /**
     * Remove alias from Postfix virtual alias map
     */
    protected function removeFromVirtualAliasMap(EmailAlias $alias): void
    {
        $virtualAliasPath = storage_path('app/postfix/virtual_alias');

        if (!file_exists($virtualAliasPath)) {
            return;
        }

        $content = file_get_contents($virtualAliasPath);
        $lines = explode("\n", $content);
        
        $filteredLines = array_filter($lines, function($line) use ($alias) {
            return strpos($line, $alias->alias_address) === false;
        });

        file_put_contents($virtualAliasPath, implode("\n", $filteredLines));

        // Reload Postfix
        $this->reloadPostfix();
    }

    /**
     * Reload Postfix
     */
    protected function reloadPostfix(): void
    {
        try {
            $process = new Process(['docker', 'exec', 'postfix', 'postfix', 'reload']);
            $process->run();
        } catch (Exception $e) {
            Log::warning("Failed to reload Postfix: " . $e->getMessage());
        }
    }

    /**
     * Get aliases for a domain
     */
    public function getDomainAliases(Domain $domain): array
    {
        return EmailAlias::where('domain_id', $domain->id)
            ->where('is_active', true)
            ->get()
            ->toArray();
    }
}
