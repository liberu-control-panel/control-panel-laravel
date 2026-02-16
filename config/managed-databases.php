<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Managed Database Providers Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for managed database providers
    | such as AWS RDS, Azure Database, DigitalOcean, OVH, and Google Cloud SQL.
    |
    */

    /**
     * AWS RDS/Aurora Configuration
     */
    'aws' => [
        'enabled' => env('AWS_RDS_ENABLED', false),
        'access_key' => env('AWS_ACCESS_KEY_ID'),
        'secret_key' => env('AWS_SECRET_ACCESS_KEY'),
        'default_region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        
        // Default instance configuration
        'defaults' => [
            'instance_class' => env('AWS_RDS_DEFAULT_INSTANCE_CLASS', 'db.t3.micro'),
            'allocated_storage' => env('AWS_RDS_DEFAULT_STORAGE', 20),
            'storage_encrypted' => env('AWS_RDS_STORAGE_ENCRYPTED', true),
            'backup_retention' => env('AWS_RDS_BACKUP_RETENTION', 7),
            'multi_az' => env('AWS_RDS_MULTI_AZ', false),
            'publicly_accessible' => env('AWS_RDS_PUBLIC', false),
        ],
    ],

    /**
     * Azure Database Configuration
     */
    'azure' => [
        'enabled' => env('AZURE_DATABASE_ENABLED', false),
        'subscription_id' => env('AZURE_SUBSCRIPTION_ID'),
        'tenant_id' => env('AZURE_TENANT_ID'),
        'client_id' => env('AZURE_CLIENT_ID'),
        'client_secret' => env('AZURE_CLIENT_SECRET'),
        'resource_group' => env('AZURE_RESOURCE_GROUP'),
        'default_region' => env('AZURE_DEFAULT_REGION', 'eastus'),
        
        // Default instance configuration
        'defaults' => [
            'sku_name' => env('AZURE_DB_DEFAULT_SKU', 'B_Gen5_1'),
            'sku_tier' => env('AZURE_DB_DEFAULT_TIER', 'Basic'),
            'storage_mb' => env('AZURE_DB_DEFAULT_STORAGE', 5120),
            'backup_retention' => env('AZURE_DB_BACKUP_RETENTION', 7),
            'ssl_enforcement' => env('AZURE_DB_SSL_ENFORCEMENT', 'Enabled'),
        ],
    ],

    /**
     * DigitalOcean Managed Database Configuration
     */
    'digitalocean' => [
        'enabled' => env('DO_DATABASE_ENABLED', false),
        'api_token' => env('DO_API_TOKEN'),
        'default_region' => env('DO_DEFAULT_REGION', 'nyc3'),
        
        // Default instance configuration
        'defaults' => [
            'size' => env('DO_DB_DEFAULT_SIZE', 'db-s-1vcpu-1gb'),
            'num_nodes' => env('DO_DB_DEFAULT_NODES', 1),
        ],
    ],

    /**
     * OVH Managed Database Configuration
     */
    'ovh' => [
        'enabled' => env('OVH_DATABASE_ENABLED', false),
        'application_key' => env('OVH_APPLICATION_KEY'),
        'application_secret' => env('OVH_APPLICATION_SECRET'),
        'consumer_key' => env('OVH_CONSUMER_KEY'),
        'endpoint' => env('OVH_ENDPOINT', 'ovh-eu'),
        'service_name' => env('OVH_SERVICE_NAME'),
        'default_region' => env('OVH_DEFAULT_REGION', 'GRA'),
        
        // Default instance configuration
        'defaults' => [
            'plan' => env('OVH_DB_DEFAULT_PLAN', 'essential'),
        ],
    ],

    /**
     * Google Cloud SQL Configuration
     */
    'gcp' => [
        'enabled' => env('GCP_SQL_ENABLED', false),
        'project_id' => env('GCP_PROJECT_ID'),
        'credentials_path' => env('GCP_CREDENTIALS_PATH'),
        'default_region' => env('GCP_DEFAULT_REGION', 'us-central1'),
        
        // Default instance configuration
        'defaults' => [
            'tier' => env('GCP_SQL_DEFAULT_TIER', 'db-f1-micro'),
            'storage_auto_resize' => env('GCP_SQL_AUTO_RESIZE', true),
            'max_storage_gb' => env('GCP_SQL_MAX_STORAGE', 0),
            'backup_enabled' => env('GCP_SQL_BACKUP_ENABLED', true),
            'require_ssl' => env('GCP_SQL_REQUIRE_SSL', true),
        ],
    ],

    /**
     * Global Settings
     */
    'global' => [
        // Automatically test connection after creating managed database
        'auto_test_connection' => env('MANAGED_DB_AUTO_TEST', true),
        
        // Timeout for database operations (in seconds)
        'operation_timeout' => env('MANAGED_DB_TIMEOUT', 300),
        
        // Enable SSL by default for managed databases
        'enforce_ssl' => env('MANAGED_DB_ENFORCE_SSL', true),
        
        // Cache provider availability for this many seconds
        'cache_ttl' => env('MANAGED_DB_CACHE_TTL', 3600),
    ],
];
