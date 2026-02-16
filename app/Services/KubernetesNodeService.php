<?php

namespace App\Services;

use App\Models\KubernetesNode;
use App\Models\Server;
use Illuminate\Support\Facades\Log;
use Exception;

class KubernetesNodeService
{
    protected SshConnectionService $sshService;

    public function __construct(SshConnectionService $sshService)
    {
        $this->sshService = $sshService;
    }

    /**
     * Sync nodes from Kubernetes cluster to database.
     */
    public function syncNodes(Server $server): bool
    {
        try {
            if (!$server->isKubernetes()) {
                throw new Exception("Server {$server->name} is not a Kubernetes server");
            }

            $nodes = $this->getNodesFromCluster($server);
            
            foreach ($nodes as $nodeData) {
                $this->updateOrCreateNode($server, $nodeData);
            }

            // Mark nodes that no longer exist as deleted
            $existingNodeNames = array_column($nodes, 'name');
            KubernetesNode::where('server_id', $server->id)
                ->whereNotIn('name', $existingNodeNames)
                ->delete();

            Log::info("Successfully synced {count($nodes)} nodes for server {$server->name}");
            return true;

        } catch (Exception $e) {
            Log::error("Failed to sync nodes for server {$server->name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get nodes from Kubernetes cluster.
     */
    protected function getNodesFromCluster(Server $server): array
    {
        $kubectlPath = config('kubernetes.kubectl_path', '/usr/local/bin/kubectl');
        $command = "{$kubectlPath} get nodes -o json";
        
        $result = $this->sshService->execute($server, $command);
        
        if (!$result['success']) {
            throw new Exception("Failed to get nodes: " . $result['output']);
        }

        $data = json_decode($result['output'], true);
        
        if (!isset($data['items'])) {
            throw new Exception("Invalid response from kubectl get nodes");
        }

        $nodes = [];
        foreach ($data['items'] as $item) {
            $nodes[] = $this->parseNodeData($item);
        }

        return $nodes;
    }

    /**
     * Parse node data from Kubernetes API response.
     */
    protected function parseNodeData(array $item): array
    {
        $metadata = $item['metadata'] ?? [];
        $status = $item['status'] ?? [];
        $spec = $item['spec'] ?? [];

        // Determine node status
        $nodeStatus = KubernetesNode::STATUS_UNKNOWN;
        $conditions = $status['conditions'] ?? [];
        foreach ($conditions as $condition) {
            if ($condition['type'] === 'Ready') {
                $nodeStatus = $condition['status'] === 'True' 
                    ? KubernetesNode::STATUS_READY 
                    : KubernetesNode::STATUS_NOT_READY;
                break;
            }
        }

        // Check if schedulable
        $schedulable = !isset($spec['unschedulable']) || !$spec['unschedulable'];
        if (!$schedulable) {
            $nodeStatus = KubernetesNode::STATUS_SCHEDULING_DISABLED;
        }

        return [
            'name' => $metadata['name'] ?? '',
            'uid' => $metadata['uid'] ?? null,
            'labels' => $metadata['labels'] ?? [],
            'annotations' => $metadata['annotations'] ?? [],
            'taints' => $spec['taints'] ?? [],
            'status' => $nodeStatus,
            'schedulable' => $schedulable,
            'addresses' => $status['addresses'] ?? [],
            'capacity' => $status['capacity'] ?? [],
            'allocatable' => $status['allocatable'] ?? [],
            'conditions' => $conditions,
            'kubernetes_version' => $status['nodeInfo']['kubeletVersion'] ?? null,
            'container_runtime' => $status['nodeInfo']['containerRuntimeVersion'] ?? null,
            'os_image' => $status['nodeInfo']['osImage'] ?? null,
            'kernel_version' => $status['nodeInfo']['kernelVersion'] ?? null,
            'architecture' => $status['nodeInfo']['architecture'] ?? 'amd64',
            'last_heartbeat_time' => $this->getLastHeartbeatTime($conditions),
        ];
    }

    /**
     * Get last heartbeat time from conditions.
     */
    protected function getLastHeartbeatTime(array $conditions): ?string
    {
        foreach ($conditions as $condition) {
            if ($condition['type'] === 'Ready') {
                return $condition['lastHeartbeatTime'] ?? $condition['lastTransitionTime'] ?? null;
            }
        }
        return null;
    }

    /**
     * Update or create node in database.
     */
    protected function updateOrCreateNode(Server $server, array $nodeData): KubernetesNode
    {
        return KubernetesNode::updateOrCreate(
            [
                'server_id' => $server->id,
                'name' => $nodeData['name'],
            ],
            $nodeData
        );
    }

    /**
     * Label a node.
     */
    public function labelNode(KubernetesNode $node, string $key, string $value): bool
    {
        try {
            $server = $node->server;
            $kubectlPath = config('kubernetes.kubectl_path', '/usr/local/bin/kubectl');
            $command = "{$kubectlPath} label node {$node->name} {$key}={$value} --overwrite";
            
            $result = $this->sshService->execute($server, $command);
            
            if (!$result['success']) {
                throw new Exception("Failed to label node: " . $result['output']);
            }

            // Update local copy
            $labels = $node->labels ?? [];
            $labels[$key] = $value;
            $node->update(['labels' => $labels]);

            Log::info("Successfully labeled node {$node->name} with {$key}={$value}");
            return true;

        } catch (Exception $e) {
            Log::error("Failed to label node {$node->name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove a label from a node.
     */
    public function unlabelNode(KubernetesNode $node, string $key): bool
    {
        try {
            $server = $node->server;
            $kubectlPath = config('kubernetes.kubectl_path', '/usr/local/bin/kubectl');
            $command = "{$kubectlPath} label node {$node->name} {$key}-";
            
            $result = $this->sshService->execute($server, $command);
            
            if (!$result['success']) {
                throw new Exception("Failed to remove label from node: " . $result['output']);
            }

            // Update local copy
            $labels = $node->labels ?? [];
            unset($labels[$key]);
            $node->update(['labels' => $labels]);

            Log::info("Successfully removed label {$key} from node {$node->name}");
            return true;

        } catch (Exception $e) {
            Log::error("Failed to remove label from node {$node->name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cordon a node (mark as unschedulable).
     */
    public function cordonNode(KubernetesNode $node): bool
    {
        try {
            $server = $node->server;
            $kubectlPath = config('kubernetes.kubectl_path', '/usr/local/bin/kubectl');
            $command = "{$kubectlPath} cordon {$node->name}";
            
            $result = $this->sshService->execute($server, $command);
            
            if (!$result['success']) {
                throw new Exception("Failed to cordon node: " . $result['output']);
            }

            $node->update([
                'schedulable' => false,
                'status' => KubernetesNode::STATUS_SCHEDULING_DISABLED,
            ]);

            Log::info("Successfully cordoned node {$node->name}");
            return true;

        } catch (Exception $e) {
            Log::error("Failed to cordon node {$node->name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Uncordon a node (mark as schedulable).
     */
    public function uncordonNode(KubernetesNode $node): bool
    {
        try {
            $server = $node->server;
            $kubectlPath = config('kubernetes.kubectl_path', '/usr/local/bin/kubectl');
            $command = "{$kubectlPath} uncordon {$node->name}";
            
            $result = $this->sshService->execute($server, $command);
            
            if (!$result['success']) {
                throw new Exception("Failed to uncordon node: " . $result['output']);
            }

            $node->update(['schedulable' => true]);

            Log::info("Successfully uncordoned node {$node->name}");
            return true;

        } catch (Exception $e) {
            Log::error("Failed to uncordon node {$node->name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Drain a node (evict all pods).
     */
    public function drainNode(KubernetesNode $node, array $options = []): bool
    {
        try {
            $server = $node->server;
            $kubectlPath = config('kubernetes.kubectl_path', '/usr/local/bin/kubectl');
            
            $flags = [
                '--ignore-daemonsets',
                '--delete-emptydir-data',
            ];

            if ($options['force'] ?? false) {
                $flags[] = '--force';
            }

            if (isset($options['grace_period'])) {
                $flags[] = "--grace-period={$options['grace_period']}";
            }

            if (isset($options['timeout'])) {
                $flags[] = "--timeout={$options['timeout']}";
            }

            $command = "{$kubectlPath} drain {$node->name} " . implode(' ', $flags);
            
            $result = $this->sshService->execute($server, $command);
            
            if (!$result['success']) {
                throw new Exception("Failed to drain node: " . $result['output']);
            }

            $node->update([
                'schedulable' => false,
                'status' => KubernetesNode::STATUS_SCHEDULING_DISABLED,
            ]);

            Log::info("Successfully drained node {$node->name}");
            return true;

        } catch (Exception $e) {
            Log::error("Failed to drain node {$node->name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get node details.
     */
    public function getNodeDetails(KubernetesNode $node): ?array
    {
        try {
            $server = $node->server;
            $kubectlPath = config('kubernetes.kubectl_path', '/usr/local/bin/kubectl');
            $command = "{$kubectlPath} describe node {$node->name}";
            
            $result = $this->sshService->execute($server, $command);
            
            if (!$result['success']) {
                throw new Exception("Failed to get node details: " . $result['output']);
            }

            return [
                'description' => $result['output'],
            ];

        } catch (Exception $e) {
            Log::error("Failed to get node details for {$node->name}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get pods running on a node.
     */
    public function getNodePods(KubernetesNode $node): array
    {
        try {
            $server = $node->server;
            $kubectlPath = config('kubernetes.kubectl_path', '/usr/local/bin/kubectl');
            $command = "{$kubectlPath} get pods --all-namespaces --field-selector spec.nodeName={$node->name} -o json";
            
            $result = $this->sshService->execute($server, $command);
            
            if (!$result['success']) {
                throw new Exception("Failed to get node pods: " . $result['output']);
            }

            $data = json_decode($result['output'], true);
            return $data['items'] ?? [];

        } catch (Exception $e) {
            Log::error("Failed to get pods for node {$node->name}: " . $e->getMessage());
            return [];
        }
    }
}
