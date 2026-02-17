<?php

namespace App\Services;

use App\Models\FirewallRule;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class FirewallService
{
    protected $detectionService;

    public function __construct(DeploymentDetectionService $detectionService)
    {
        $this->detectionService = $detectionService;
    }

    /**
     * Create firewall rule
     */
    public function createRule(array $data): FirewallRule
    {
        // Validate IP address
        if (!$this->isValidIpAddress($data['ip_address'])) {
            throw new Exception('Invalid IP address or CIDR notation');
        }

        // Create rule in database
        $rule = FirewallRule::create($data);

        // Apply rule to firewall
        if ($rule->is_active) {
            $this->applyRule($rule);
        }

        return $rule;
    }

    /**
     * Update firewall rule
     */
    public function updateRule(FirewallRule $rule, array $data): FirewallRule
    {
        $wasActive = $rule->is_active;
        
        // Update rule
        $rule->update($data);

        // Handle activation/deactivation
        if ($wasActive && !$rule->is_active) {
            $this->removeRule($rule);
        } elseif (!$wasActive && $rule->is_active) {
            $this->applyRule($rule);
        } elseif ($rule->is_active) {
            // Rule is active and was modified, reapply
            $this->removeRule($rule);
            $this->applyRule($rule);
        }

        return $rule;
    }

    /**
     * Delete firewall rule
     */
    public function deleteRule(FirewallRule $rule): bool
    {
        try {
            if ($rule->is_active) {
                $this->removeRule($rule);
            }

            $rule->delete();
            return true;
        } catch (Exception $e) {
            Log::error("Failed to delete firewall rule {$rule->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Apply firewall rule
     */
    protected function applyRule(FirewallRule $rule): void
    {
        try {
            if ($this->detectionService->isKubernetes()) {
                $this->applyRuleKubernetes($rule);
            } else {
                $this->applyRuleIptables($rule);
            }
        } catch (Exception $e) {
            Log::error("Failed to apply firewall rule {$rule->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Apply rule using iptables
     */
    protected function applyRuleIptables(FirewallRule $rule): void
    {
        $chain = 'INPUT';
        $action = strtoupper($rule->action === 'allow' ? 'ACCEPT' : 'DROP');
        $protocol = $rule->protocol !== 'all' ? "-p {$rule->protocol}" : '';
        
        $portParam = '';
        if ($rule->port) {
            $portParam = "--dport {$rule->port}";
        } elseif ($rule->port_range) {
            $portParam = "--dport {$rule->port_range}";
        }

        // Build iptables command
        $command = [
            'iptables',
            '-A', $chain,
            '-s', $rule->ip_address,
        ];

        if ($protocol) {
            $command[] = '-p';
            $command[] = $rule->protocol;
        }

        if ($portParam && $rule->protocol !== 'icmp') {
            $command[] = '--dport';
            if ($rule->port) {
                $command[] = (string)$rule->port;
            } else {
                $command[] = $rule->port_range;
            }
        }

        $command[] = '-j';
        $command[] = $action;
        $command[] = '-m';
        $command[] = 'comment';
        $command[] = '--comment';
        $command[] = "control-panel-rule-{$rule->id}";

        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new Exception("Failed to apply iptables rule: " . $process->getErrorOutput());
        }

        // Save iptables rules
        $this->saveIptablesRules();
    }

    /**
     * Apply rule in Kubernetes (NetworkPolicy)
     */
    protected function applyRuleKubernetes(FirewallRule $rule): void
    {
        $action = $rule->action === 'allow' ? 'Ingress' : 'Egress';
        $protocol = ucfirst($rule->protocol !== 'all' ? $rule->protocol : 'TCP');

        $policyYaml = $this->generateNetworkPolicy($rule);

        $tmpFile = tempnam(sys_get_temp_dir(), 'firewall-rule-');
        file_put_contents($tmpFile, $policyYaml);

        $process = new Process(['kubectl', 'apply', '-f', $tmpFile]);
        $process->run();
        
        unlink($tmpFile);

        if (!$process->isSuccessful()) {
            throw new Exception("Failed to apply Kubernetes NetworkPolicy: " . $process->getErrorOutput());
        }
    }

    /**
     * Generate Kubernetes NetworkPolicy YAML
     */
    protected function generateNetworkPolicy(FirewallRule $rule): string
    {
        $policyType = $rule->action === 'allow' ? 'Ingress' : 'Egress';
        $protocol = strtoupper($rule->protocol !== 'all' ? $rule->protocol : 'TCP');

        $portSection = '';
        if ($rule->port) {
            $portSection = "
      - protocol: {$protocol}
        port: {$rule->port}";
        }

        // Convert CIDR notation if needed
        $cidr = $this->toCidrNotation($rule->ip_address);

        return <<<YAML
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: firewall-rule-{$rule->id}
  namespace: control-panel
spec:
  podSelector:
    matchLabels:
      app: control-panel
  policyTypes:
  - {$policyType}
  {$policyType}:
  - from:
    - ipBlock:
        cidr: {$cidr}
    ports:{$portSection}
YAML;
    }

    /**
     * Remove firewall rule
     */
    protected function removeRule(FirewallRule $rule): void
    {
        try {
            if ($this->detectionService->isKubernetes()) {
                $this->removeRuleKubernetes($rule);
            } else {
                $this->removeRuleIptables($rule);
            }
        } catch (Exception $e) {
            Log::error("Failed to remove firewall rule {$rule->id}: " . $e->getMessage());
        }
    }

    /**
     * Remove rule using iptables
     */
    protected function removeRuleIptables(FirewallRule $rule): void
    {
        // Find and delete rule by comment
        $command = [
            'bash',
            '-c',
            "iptables -L INPUT --line-numbers -n | grep 'control-panel-rule-{$rule->id}' | awk '{print \$1}' | xargs -r -I {} iptables -D INPUT {}"
        ];

        $process = new Process($command);
        $process->run();

        // Save iptables rules
        $this->saveIptablesRules();
    }

    /**
     * Remove rule from Kubernetes
     */
    protected function removeRuleKubernetes(FirewallRule $rule): void
    {
        $process = new Process(['kubectl', 'delete', 'networkpolicy', "firewall-rule-{$rule->id}", '-n', 'control-panel', '--ignore-not-found']);
        $process->run();
    }

    /**
     * Save iptables rules
     */
    protected function saveIptablesRules(): void
    {
        // Save rules to persist across reboots
        $process = new Process(['iptables-save']);
        $process->run();

        if ($process->isSuccessful()) {
            file_put_contents('/etc/iptables/rules.v4', $process->getOutput());
        }
    }

    /**
     * Validate IP address
     */
    protected function isValidIpAddress(string $ip): bool
    {
        // Validate standard IP
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        // Validate CIDR notation
        if (preg_match('/^(\d{1,3}\.){3}\d{1,3}\/\d{1,2}$/', $ip)) {
            $parts = explode('/', $ip);
            $ipPart = $parts[0];
            $cidrPart = (int)$parts[1];

            if (filter_var($ipPart, FILTER_VALIDATE_IP) && $cidrPart >= 0 && $cidrPart <= 32) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert IP address to CIDR notation
     */
    protected function toCidrNotation(string $ip): string
    {
        if (strpos($ip, '/') !== false) {
            return $ip;
        }

        return "{$ip}/32";
    }

    /**
     * Get active firewall rules for a user
     */
    public function getActiveRules(User $user): array
    {
        return FirewallRule::where('user_id', $user->id)
            ->where('is_active', true)
            ->orderBy('priority')
            ->get()
            ->toArray();
    }

    /**
     * Flush all rules for a user
     */
    public function flushUserRules(User $user): bool
    {
        try {
            $rules = FirewallRule::where('user_id', $user->id)
                ->where('is_active', true)
                ->get();

            foreach ($rules as $rule) {
                $this->removeRule($rule);
                $rule->update(['is_active' => false]);
            }

            return true;
        } catch (Exception $e) {
            Log::error("Failed to flush firewall rules for user {$user->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Apply all active rules
     */
    public function applyAllRules(): void
    {
        $rules = FirewallRule::where('is_active', true)
            ->orderBy('priority')
            ->get();

        foreach ($rules as $rule) {
            try {
                $this->applyRule($rule);
            } catch (Exception $e) {
                Log::error("Failed to apply firewall rule {$rule->id}: " . $e->getMessage());
            }
        }
    }
}
