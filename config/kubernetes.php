<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Kubernetes Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Kubernetes cluster management
    |
    */

    'enabled' => env('KUBERNETES_ENABLED', true),

    'kubectl_path' => env('KUBECTL_PATH', '/usr/local/bin/kubectl'),

    'namespace_prefix' => env('KUBERNETES_NAMESPACE_PREFIX', 'hosting-'),

    'default_resources' => [
        'requests' => [
            'memory' => env('KUBERNETES_DEFAULT_MEMORY_REQUEST', '128Mi'),
            'cpu' => env('KUBERNETES_DEFAULT_CPU_REQUEST', '100m'),
        ],
        'limits' => [
            'memory' => env('KUBERNETES_DEFAULT_MEMORY_LIMIT', '512Mi'),
            'cpu' => env('KUBERNETES_DEFAULT_CPU_LIMIT', '500m'),
        ],
    ],

    'ingress' => [
        'class' => env('KUBERNETES_INGRESS_CLASS', 'nginx'),
        'cert_manager_issuer' => env('KUBERNETES_CERT_ISSUER', 'letsencrypt-prod'),
    ],

    'storage' => [
        'class' => env('KUBERNETES_STORAGE_CLASS', 'standard'),
        'default_size' => env('KUBERNETES_DEFAULT_STORAGE_SIZE', '10Gi'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Service Templates
    |--------------------------------------------------------------------------
    |
    | Default container images for common web hosting services
    |
    */

    'images' => [
        'nginx' => env('KUBERNETES_IMAGE_NGINX', 'nginx:alpine'),
        'php_fpm' => env('KUBERNETES_IMAGE_PHP_FPM', 'php:8.2-fpm-alpine'),
        'mysql' => env('KUBERNETES_IMAGE_MYSQL', 'mysql:8.0'),
        'postgresql' => env('KUBERNETES_IMAGE_POSTGRESQL', 'postgres:15-alpine'),
        'redis' => env('KUBERNETES_IMAGE_REDIS', 'redis:alpine'),
        'memcached' => env('KUBERNETES_IMAGE_MEMCACHED', 'memcached:alpine'),
        'ftp' => env('KUBERNETES_IMAGE_FTP', 'stilliard/pure-ftpd:hardened'),
        'filemanager' => env('KUBERNETES_IMAGE_FILEMANAGER', 'filebrowser/filebrowser:latest'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    */

    'security' => [
        'enable_pod_security_policies' => env('KUBERNETES_ENABLE_PSP', true),
        'enable_network_policies' => env('KUBERNETES_ENABLE_NETWORK_POLICIES', true),
        'run_as_non_root' => env('KUBERNETES_RUN_AS_NON_ROOT', true),
        'read_only_root_filesystem' => env('KUBERNETES_READONLY_ROOTFS', false),
    ],
];
