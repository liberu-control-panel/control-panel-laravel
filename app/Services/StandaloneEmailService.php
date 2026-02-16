<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\EmailAccount;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class StandaloneEmailService
{
    protected StandaloneServiceHelper $helper;
    protected DeploymentDetectionService $detectionService;

    public function __construct(
        StandaloneServiceHelper $helper,
        DeploymentDetectionService $detectionService
    ) {
        $this->helper = $helper;
        $this->detectionService = $detectionService;
    }

    /**
     * Check if we should use standalone mode
     */
    public function shouldUseStandaloneMode(): bool
    {
        return $this->detectionService->isStandalone();
    }

    /**
     * Create email account with Postfix/Dovecot
     */
    public function createEmailAccount(Domain $domain, array $data): array
    {
        try {
            // Create the email account record
            $emailAccount = EmailAccount::create([
                'user_id' => $domain->user_id,
                'domain_id' => $domain->id,
                'email_address' => $data['email_address'],
                'password' => Hash::make($data['password']),
                'quota' => $data['quota'] ?? 1024, // MB
                'forwarding_rules' => $data['forwarding_rules'] ?? []
            ]);

            // Create mailbox in Postfix/Dovecot
            $this->createMailbox($emailAccount, $data['password']);

            // Add virtual mailbox entry
            $this->addVirtualMailbox($emailAccount);

            // Add virtual alias
            $this->addVirtualAlias($emailAccount);

            // Reload mail services
            $this->reloadMailServices();

            return [
                'success' => true,
                'message' => 'Email account created successfully',
                'email_account' => $emailAccount,
            ];
        } catch (Exception $e) {
            Log::error('Failed to create email account: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to create email account: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Delete email account
     */
    public function deleteEmailAccount(EmailAccount $emailAccount): array
    {
        try {
            // Remove from virtual mailbox
            $this->removeVirtualMailbox($emailAccount);

            // Remove from virtual alias
            $this->removeVirtualAlias($emailAccount);

            // Delete mailbox directory
            $this->deleteMailbox($emailAccount);

            // Reload mail services
            $this->reloadMailServices();

            // Delete the record
            $emailAccount->delete();

            return [
                'success' => true,
                'message' => 'Email account deleted successfully',
            ];
        } catch (Exception $e) {
            Log::error('Failed to delete email account: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to delete email account: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Update email account password
     */
    public function updatePassword(EmailAccount $emailAccount, string $password): array
    {
        try {
            // Update password in database
            $emailAccount->update([
                'password' => Hash::make($password)
            ]);

            // Update Dovecot password
            $this->updateDovecotPassword($emailAccount, $password);

            return [
                'success' => true,
                'message' => 'Email password updated successfully',
            ];
        } catch (Exception $e) {
            Log::error('Failed to update email password: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to update email password: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Create mailbox directory
     */
    protected function createMailbox(EmailAccount $emailAccount): void
    {
        $mailboxPath = "/var/mail/vhosts/{$emailAccount->domain->domain_name}/{$emailAccount->email_address}";
        
        // Create directory
        $this->helper->executeCommand([
            'sudo', 'mkdir', '-p', $mailboxPath
        ]);

        // Set ownership to mail user
        $this->helper->executeCommand([
            'sudo', 'chown', '-R', 'vmail:vmail', $mailboxPath
        ]);

        // Set permissions
        $this->helper->executeCommand([
            'sudo', 'chmod', '-R', '700', $mailboxPath
        ]);
    }

    /**
     * Delete mailbox directory
     */
    protected function deleteMailbox(EmailAccount $emailAccount): void
    {
        $mailboxPath = "/var/mail/vhosts/{$emailAccount->domain->domain_name}/{$emailAccount->email_address}";
        
        $this->helper->executeCommand([
            'sudo', 'rm', '-rf', $mailboxPath
        ]);
    }

    /**
     * Add virtual mailbox entry
     */
    protected function addVirtualMailbox(EmailAccount $emailAccount): void
    {
        $virtualMailboxFile = '/etc/postfix/virtual_mailbox';
        $entry = "{$emailAccount->email_address} {$emailAccount->domain->domain_name}/{$emailAccount->email_address}/";

        // Create backup
        $this->helper->executeCommand([
            'sudo', 'cp', $virtualMailboxFile, "{$virtualMailboxFile}.bak"
        ]);

        // Append entry
        $this->helper->executeCommand([
            'sudo', 'bash', '-c',
            "echo '{$entry}' >> {$virtualMailboxFile}"
        ]);

        // Update postmap
        $this->helper->executeCommand([
            'sudo', 'postmap', $virtualMailboxFile
        ]);
    }

    /**
     * Remove virtual mailbox entry
     */
    protected function removeVirtualMailbox(EmailAccount $emailAccount): void
    {
        $virtualMailboxFile = '/etc/postfix/virtual_mailbox';
        $email = $emailAccount->email_address;

        // Remove line containing the email
        $this->helper->executeCommand([
            'sudo', 'sed', '-i',
            "/^{$email} /d",
            $virtualMailboxFile
        ]);

        // Update postmap
        $this->helper->executeCommand([
            'sudo', 'postmap', $virtualMailboxFile
        ]);
    }

    /**
     * Add virtual alias entry
     */
    protected function addVirtualAlias(EmailAccount $emailAccount): void
    {
        $virtualAliasFile = '/etc/postfix/virtual_alias';
        $entry = "{$emailAccount->email_address} {$emailAccount->email_address}";

        // Create backup
        $this->helper->executeCommand([
            'sudo', 'cp', $virtualAliasFile, "{$virtualAliasFile}.bak"
        ]);

        // Append entry
        $this->helper->executeCommand([
            'sudo', 'bash', '-c',
            "echo '{$entry}' >> {$virtualAliasFile}"
        ]);

        // Update postmap
        $this->helper->executeCommand([
            'sudo', 'postmap', $virtualAliasFile
        ]);
    }

    /**
     * Remove virtual alias entry
     */
    protected function removeVirtualAlias(EmailAccount $emailAccount): void
    {
        $virtualAliasFile = '/etc/postfix/virtual_alias';
        $email = $emailAccount->email_address;

        // Remove line containing the email
        $this->helper->executeCommand([
            'sudo', 'sed', '-i',
            "/^{$email} /d",
            $virtualAliasFile
        ]);

        // Update postmap
        $this->helper->executeCommand([
            'sudo', 'postmap', $virtualAliasFile
        ]);
    }

    /**
     * Update Dovecot password for email account
     */
    protected function updateDovecotPassword(EmailAccount $emailAccount, string $password): void
    {
        // Generate password hash for Dovecot
        $result = $this->helper->executeCommand([
            'doveadm', 'pw', '-s', 'SHA512-CRYPT', '-p', $password
        ]);

        if ($result['success']) {
            $hashedPassword = trim($result['output']);
            
            // Update dovecot password file
            $this->updateDovecotPasswordFile($emailAccount->email_address, $hashedPassword);
        }
    }

    /**
     * Update Dovecot password file
     */
    protected function updateDovecotPasswordFile(string $email, string $hashedPassword): void
    {
        $passwordFile = '/etc/dovecot/passwd';
        
        // Format: email:password_hash:uid:gid::home::/bin/false::
        $uid = 5000; // vmail user id
        $gid = 5000; // vmail group id
        $entry = "{$email}:{$hashedPassword}:{$uid}:{$gid}:::/var/mail/vhosts::/bin/false::";

        // Remove old entry if exists
        $this->helper->executeCommand([
            'sudo', 'sed', '-i',
            "/^{$email}:/d",
            $passwordFile
        ]);

        // Add new entry
        $this->helper->executeCommand([
            'sudo', 'bash', '-c',
            "echo '{$entry}' >> {$passwordFile}"
        ]);

        // Set proper permissions
        $this->helper->executeCommand([
            'sudo', 'chmod', '600', $passwordFile
        ]);
    }

    /**
     * Reload mail services
     */
    protected function reloadMailServices(): void
    {
        // Reload Postfix
        if ($this->helper->isSystemdServiceRunning('postfix')) {
            $this->helper->reloadSystemdService('postfix');
        }

        // Reload Dovecot
        if ($this->helper->isSystemdServiceRunning('dovecot')) {
            $this->helper->reloadSystemdService('dovecot');
        }
    }

    /**
     * Check if Postfix is installed
     */
    public function isPostfixInstalled(): bool
    {
        return $this->helper->isServiceInstalled('postfix');
    }

    /**
     * Check if Dovecot is installed
     */
    public function isDovecotInstalled(): bool
    {
        return $this->helper->isServiceInstalled('dovecot');
    }

    /**
     * Check if mail services are properly configured
     */
    public function areMailServicesReady(): bool
    {
        return $this->isPostfixInstalled() 
            && $this->isDovecotInstalled()
            && $this->helper->isSystemdServiceRunning('postfix')
            && $this->helper->isSystemdServiceRunning('dovecot');
    }
}
