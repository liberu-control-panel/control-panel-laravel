<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Exception;

/**
 * JailkitService – sets up a jailkit chroot jail for a system user.
 *
 * Jailkit (https://olivier.sessink.nl/jailkit/) creates a limited chroot
 * environment for a user so that they can only access a predefined subset
 * of the filesystem.  Each user's jail lives inside their own home directory:
 *   /home/<username>/   ← the chroot root (owned root:root, mode 0755)
 *   /home/<username>/public_html  ← the nginx document root (owned by the user)
 *
 * The control-panel service account must have the following sudoers entries:
 *   <cp-user>  ALL=NOPASSWD: /usr/sbin/jk_init  *
 *   <cp-user>  ALL=NOPASSWD: /usr/sbin/jk_jailuser  *
 */
class JailkitService
{
    protected StandaloneServiceHelper $helper;

    public function __construct(StandaloneServiceHelper $helper)
    {
        $this->helper = $helper;
    }

    /**
     * Return true when jailkit is installed on the current system.
     */
    public function isInstalled(): bool
    {
        $result = $this->helper->executeCommand(['which', 'jk_init']);
        return $result['success'] && trim($result['output']) !== '';
    }

    /**
     * Initialise a jailkit jail inside the given jail root directory.
     *
     * Typical sections: "basicshell", "extendedshell", "limitedshell",
     * "jk_lsh", "sftp", "scp", "rsync", "editors", "perlscripts", "javajdk".
     *
     * @param string   $jailRoot  Absolute path that will become the chroot root
     * @param string[] $sections  jk_init sections to copy into the jail
     */
    public function initJail(string $jailRoot, array $sections = ['basicshell', 'jk_lsh', 'sftp', 'scp']): bool
    {
        try {
            // The jail root must be owned by root; create it if needed.
            $this->helper->executeCommand(['sudo', 'mkdir', '-p', $jailRoot]);
            $this->helper->executeCommand(['sudo', 'chown', 'root:root', $jailRoot]);
            $this->helper->executeCommand(['sudo', 'chmod', '0755', $jailRoot]);

            $cmd = array_merge(['sudo', 'jk_init', '-v', '-j', $jailRoot], $sections);
            $result = $this->helper->executeCommand($cmd, 120);

            if (!$result['success']) {
                Log::error("jk_init failed for jail root {$jailRoot}: " . $result['error']);
                return false;
            }

            return true;
        } catch (Exception $e) {
            Log::error("JailkitService::initJail failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Jail an existing system user into the given chroot root.
     *
     * After this call the user's shell in /etc/passwd is replaced with a
     * jailkit wrapper and their home directory is relocated inside the jail.
     *
     * @param string $username  Linux system username
     * @param string $jailRoot  Absolute path used as the chroot root
     */
    public function jailUser(string $username, string $jailRoot): bool
    {
        try {
            $cmd = ['sudo', 'jk_jailuser', '-m', '-j', $jailRoot, '-s', '/usr/sbin/jk_lsh', $username];
            $result = $this->helper->executeCommand($cmd, 60);

            if (!$result['success']) {
                Log::error("jk_jailuser failed for user {$username}: " . $result['error']);
                return false;
            }

            return true;
        } catch (Exception $e) {
            Log::error("JailkitService::jailUser failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a full per-user jail under /home/<username>.
     *
     * Layout after this call:
     *   /home/<username>/              chroot root  (root:root 0755)
     *   /home/<username>/public_html   document root (username:username 0750)
     *
     * @param string   $username  Linux system username
     * @param string[] $sections  jk_init sections
     * @return bool
     */
    public function setupUserJail(string $username, array $sections = ['basicshell', 'jk_lsh', 'sftp', 'scp']): bool
    {
        $jailRoot = "/home/{$username}";
        $publicHtml = "{$jailRoot}/public_html";

        // 1. Initialise the jail structure inside /home/<username>
        if (!$this->initJail($jailRoot, $sections)) {
            return false;
        }

        // 2. Create the document root directory owned by the user
        $this->helper->executeCommand(['sudo', 'mkdir', '-p', $publicHtml]);
        $this->helper->executeCommand(['sudo', 'chown', '-R', "{$username}:{$username}", $publicHtml]);
        $this->helper->executeCommand(['sudo', 'chmod', '0750', $publicHtml]);

        // 3. Jail the user into their home directory
        return $this->jailUser($username, $jailRoot);
    }

    /**
     * Remove the jailkit configuration for a user.
     *
     * This does NOT delete the home directory; call removeUserHomeDirectory()
     * separately if the data should also be wiped.
     *
     * @param string $username  Linux system username
     */
    public function removeUserJail(string $username): bool
    {
        try {
            // Restore a normal nologin shell so the account can still be deleted cleanly
            $noLoginShell = file_exists('/usr/sbin/nologin') ? '/usr/sbin/nologin' : '/sbin/nologin';
            $this->helper->executeCommand(['sudo', 'usermod', '-s', $noLoginShell, $username]);

            return true;
        } catch (Exception $e) {
            Log::error("JailkitService::removeUserJail failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Recursively remove a user's home directory from the filesystem.
     *
     * USE WITH CARE – this permanently deletes all user data.
     *
     * @param string $username  Linux system username
     */
    public function removeUserHomeDirectory(string $username): bool
    {
        try {
            $homeDir = "/home/{$username}";
            $result = $this->helper->executeCommand(['sudo', 'rm', '-rf', $homeDir]);
            return $result['success'];
        } catch (Exception $e) {
            Log::error("JailkitService::removeUserHomeDirectory failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Return the document root path for a user inside their jail.
     *
     * @param string $username  Linux system username
     */
    public function getDocumentRoot(string $username): string
    {
        return "/home/{$username}/public_html";
    }

    /**
     * Return the jail root (chroot root) for a user.
     *
     * @param string $username  Linux system username
     */
    public function getJailRoot(string $username): string
    {
        return "/home/{$username}";
    }
}
