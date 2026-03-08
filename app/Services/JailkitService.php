<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * JailkitService - manage jailkit chroot jails for SFTP/SSH users.
 *
 * Jailkit (https://olivier.sessink.nl/jailkit/) confines users to a chroot
 * environment so they cannot traverse the host filesystem.  This service
 * wraps the key jailkit commands:
 *
 *  - jk_init     – populate a jail with the chosen sections from jk_init.ini
 *  - jk_jailuser – move an existing system user into the jail
 *  - jk_update   – refresh jail binaries after package upgrades
 *  - jk_uchroot  – run a command as a jailed user (used internally)
 *
 * All commands are executed via sudo.  The control-panel's sudoers entry
 * must allow the web-server user to run /usr/sbin/jk_* as root.
 */
class JailkitService
{
    /** Default sections passed to jk_init when creating a new jail. */
    const DEFAULT_SECTIONS = [
        'basicshell',
        'editors',
        'extendedshell',
        'netutils',
        'sftp',
        'scp',
    ];

    protected StandaloneServiceHelper $helper;

    public function __construct(StandaloneServiceHelper $helper)
    {
        $this->helper = $helper;
    }

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /**
     * Return true when the jk_init binary is present on the host.
     */
    public function isInstalled(): bool
    {
        $result = $this->helper->executeCommand(['which', 'jk_init']);
        return $result['success'];
    }

    /**
     * Create (or refresh) a chroot jail at $jailPath.
     *
     * @param string   $jailPath  Absolute path for the jail root, e.g. /home/alice/jail
     * @param string[] $sections  Jailkit sections to include (from jk_init.ini)
     */
    public function initJail(string $jailPath, array $sections = self::DEFAULT_SECTIONS): bool
    {
        if (!$this->isInstalled()) {
            Log::warning('JailkitService: jailkit is not installed – skipping jail initialisation');
            return false;
        }

        $result = $this->helper->executeCommand(
            array_merge(['sudo', 'jk_init', '-v', '-j', $jailPath], $sections),
            120
        );

        if (!$result['success']) {
            Log::error("JailkitService: jk_init failed for {$jailPath}: " . $result['error']);
        }

        return $result['success'];
    }

    /**
     * Confine an existing system user inside the jail.
     *
     * After this call the user's shell and home directory are updated to
     * point into $jailPath.  The original home directory is preserved
     * as the home inside the jail.
     *
     * @param string $username Linux username to jail
     * @param string $jailPath Absolute path of the jail root
     * @param string $shell    Shell to give the user inside the jail (default: /bin/bash)
     */
    public function jailUser(string $username, string $jailPath, string $shell = '/bin/bash'): bool
    {
        if (!$this->isInstalled()) {
            Log::warning('JailkitService: jailkit is not installed – skipping jk_jailuser');
            return false;
        }

        $result = $this->helper->executeCommand(
            ['sudo', 'jk_jailuser', '-v', '-j', $jailPath, '-s', $shell, $username],
            60
        );

        if (!$result['success']) {
            Log::error("JailkitService: jk_jailuser failed for {$username}: " . $result['error']);
        }

        return $result['success'];
    }

    /**
     * Update the binaries inside a jail (call after host package upgrades).
     *
     * @param string $jailPath Absolute path of the jail root
     */
    public function updateJail(string $jailPath): bool
    {
        if (!$this->isInstalled()) {
            return false;
        }

        $result = $this->helper->executeCommand(
            ['sudo', 'jk_update', '-v', '-j', $jailPath],
            120
        );

        if (!$result['success']) {
            Log::error("JailkitService: jk_update failed for {$jailPath}: " . $result['error']);
        }

        return $result['success'];
    }

    /**
     * Remove a user from the jail by restoring their shell to /bin/bash and
     * moving their home directory back to /home/<username>.
     *
     * Note: this only reverses the passwd/shadow entries updated by jk_jailuser;
     * the jail directory itself is left in place (call removeJail separately).
     *
     * @param string $username  Linux username
     * @param string $homeDir   Restored home directory (default: /home/<username>)
     * @param string $shell     Restored login shell   (default: /bin/bash)
     */
    public function unjailUser(string $username, string $homeDir = '', string $shell = '/bin/bash'): bool
    {
        if ($homeDir === '') {
            $homeDir = "/home/{$username}";
        }

        $result = $this->helper->executeCommand(
            ['sudo', 'usermod', '-d', $homeDir, '-s', $shell, $username],
            30
        );

        if (!$result['success']) {
            Log::error("JailkitService: unjail (usermod) failed for {$username}: " . $result['error']);
        }

        return $result['success'];
    }

    /**
     * Delete the jail directory from the filesystem.
     *
     * @param string $jailPath Absolute path of the jail root
     */
    public function removeJail(string $jailPath): bool
    {
        // Guard against accidentally removing critical system paths.
        // rtrim('/','/')  would return '' so use the ?: fallback to preserve '/'.
        if (in_array(rtrim($jailPath, '/') ?: '/', ['/', '/home', '/etc', '/var', '/usr', '/bin', '/sbin', '/tmp'], true)) {
            Log::error("JailkitService: refusing to remove protected path: {$jailPath}");
            return false;
        }

        $result = $this->helper->executeCommand(
            ['sudo', 'rm', '-rf', $jailPath],
            60
        );

        if (!$result['success']) {
            Log::error("JailkitService: failed to remove jail at {$jailPath}: " . $result['error']);
        }

        return $result['success'];
    }

    /**
     * Convenience method: create a complete per-user SFTP jail under
     * /home/<username>/jail, initialise it, and confine the user in one step.
     *
     * In standalone mode this should be called right after the system user
     * is created and their home directory is set up.
     *
     * @param string   $username System username (e.g. cp-user-alice)
     * @param string[] $sections Jailkit sections to include
     * @return array{success: bool, jail_path: string, message: string}
     */
    public function setupUserJail(string $username, array $sections = self::DEFAULT_SECTIONS): array
    {
        $jailPath = "/home/{$username}/jail";

        if (!$this->isInstalled()) {
            return [
                'success'   => false,
                'jail_path' => $jailPath,
                'message'   => 'Jailkit is not installed on this server. Install it via your package manager (e.g. apt-get install jailkit on Debian/Ubuntu, dnf install jailkit on RHEL/AlmaLinux) or build from source at https://olivier.sessink.nl/jailkit/.',
            ];
        }

        // Step 1: initialise the jail
        if (!$this->initJail($jailPath, $sections)) {
            return [
                'success'   => false,
                'jail_path' => $jailPath,
                'message'   => "Failed to initialise jail at {$jailPath}",
            ];
        }

        // Step 2: confine the user
        if (!$this->jailUser($username, $jailPath)) {
            return [
                'success'   => false,
                'jail_path' => $jailPath,
                'message'   => "Failed to confine user {$username} in {$jailPath}",
            ];
        }

        Log::info("JailkitService: user {$username} confined to {$jailPath}");

        return [
            'success'   => true,
            'jail_path' => $jailPath,
            'message'   => "User {$username} successfully confined to {$jailPath}",
        ];
    }

    /**
     * Remove a per-user jail and restore the user to normal login.
     *
     * @param string $username System username
     * @return array{success: bool, message: string}
     */
    public function teardownUserJail(string $username): array
    {
        $jailPath = "/home/{$username}/jail";
        $homeDir  = "/home/{$username}";

        $unjailed = $this->unjailUser($username, $homeDir);
        $removed  = $this->removeJail($jailPath);

        if ($unjailed && $removed) {
            Log::info("JailkitService: jail for {$username} removed");
            return ['success' => true, 'message' => "Jail for {$username} removed"];
        }

        return [
            'success' => false,
            'message' => "Partial failure tearing down jail for {$username} – check logs",
        ];
    }
}
