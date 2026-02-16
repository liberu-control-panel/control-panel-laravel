# Managed Database Support

This document describes the optional managed database support feature that allows you to connect to cloud-hosted databases from providers like AWS RDS, Azure Database, DigitalOcean, OVH, and Google Cloud SQL.

## Overview

The control panel now supports two types of database connections:

1. **Self-Hosted**: Traditional databases running in Docker containers or on the local system
2. **Managed**: Cloud-hosted databases from supported providers

## Supported Providers

- **AWS RDS/Aurora** - Amazon's managed relational database service
- **Azure Database** - Microsoft Azure's managed database service for MySQL/PostgreSQL
- **DigitalOcean Managed Database** - DigitalOcean's database clusters
- **OVH Managed Database** - OVH's cloud database service
- **Google Cloud SQL** - Google Cloud's fully-managed database service

## Supported Database Engines

All providers support:
- MySQL
- PostgreSQL
- MariaDB (via MySQL compatibility)

DigitalOcean also supports:
- Redis

## Configuration

### Environment Variables

Add the following variables to your `.env` file to enable managed database providers:

#### AWS RDS/Aurora

```env
AWS_RDS_ENABLED=true
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_RDS_DEFAULT_INSTANCE_CLASS=db.t3.micro
AWS_RDS_DEFAULT_STORAGE=20
AWS_RDS_STORAGE_ENCRYPTED=true
AWS_RDS_BACKUP_RETENTION=7
AWS_RDS_MULTI_AZ=false
AWS_RDS_PUBLIC=false
```

#### Azure Database

```env
AZURE_DATABASE_ENABLED=true
AZURE_SUBSCRIPTION_ID=your_subscription_id
AZURE_TENANT_ID=your_tenant_id
AZURE_CLIENT_ID=your_client_id
AZURE_CLIENT_SECRET=your_client_secret
AZURE_RESOURCE_GROUP=your_resource_group
AZURE_DEFAULT_REGION=eastus
AZURE_DB_DEFAULT_SKU=B_Gen5_1
```

#### DigitalOcean

```env
DO_DATABASE_ENABLED=true
DO_API_TOKEN=your_api_token
DO_DEFAULT_REGION=nyc3
DO_DB_DEFAULT_SIZE=db-s-1vcpu-1gb
DO_DB_DEFAULT_NODES=1
```

#### OVH

```env
OVH_DATABASE_ENABLED=true
OVH_APPLICATION_KEY=your_app_key
OVH_APPLICATION_SECRET=your_app_secret
OVH_CONSUMER_KEY=your_consumer_key
OVH_ENDPOINT=ovh-eu
OVH_SERVICE_NAME=your_service_name
OVH_DEFAULT_REGION=GRA
```

#### Google Cloud SQL

```env
GCP_SQL_ENABLED=true
GCP_PROJECT_ID=your_project_id
GCP_CREDENTIALS_PATH=/path/to/credentials.json
GCP_DEFAULT_REGION=us-central1
GCP_SQL_DEFAULT_TIER=db-f1-micro
```

### Configuration File

The main configuration is in `config/managed-databases.php`. You can customize default settings for each provider there.

## Usage

### Creating a Managed Database Connection

1. Navigate to **Hosting > Databases** in the control panel
2. Click **Create**
3. Fill in the database information:
   - **Connection Type**: Select "Managed Database"
   - **Cloud Provider**: Choose your provider (AWS, Azure, DigitalOcean, OVH, or GCP)
   - **Database Engine**: Select MySQL, PostgreSQL, or MariaDB
   - **Database Host**: Enter the endpoint of your managed database
   - **Port**: Enter the port number (usually 3306 for MySQL, 5432 for PostgreSQL)
   - **Username**: Database username
   - **Password**: Database password
   - **Use SSL/TLS**: Enable for secure connections (recommended)
   - **Instance Identifier**: (Optional) The cloud provider's instance ID
   - **Region**: (Optional) The cloud provider's region

4. Click **Create** to save the connection

### Using Managed Databases

Once configured, managed databases work exactly like self-hosted databases:

- Database users can be created and managed
- Applications can connect using the stored credentials
- Connection details are securely encrypted
- You can view database metrics and statistics (when supported by provider)

### Security Considerations

- **SSL/TLS**: Always enable SSL/TLS connections for managed databases
- **Password Encryption**: Database passwords are automatically encrypted using Laravel's encryption
- **Firewall Rules**: Ensure your cloud provider's firewall allows connections from your control panel server
- **IP Whitelisting**: Configure your managed database to only accept connections from known IPs

## Architecture

### Components

1. **ManagedDatabaseProviderInterface**: Base interface that all providers implement
2. **BaseManagedDatabaseProvider**: Abstract base class with common functionality
3. **Provider Classes**: Specific implementations for each cloud provider
   - `AwsRdsProvider`
   - `AzureDatabaseProvider`
   - `DigitalOceanDatabaseProvider`
   - `OvhDatabaseProvider`
   - `GoogleCloudSqlProvider`
4. **ManagedDatabaseManager**: Coordinates all providers and delegates operations
5. **DatabaseService**: Main service integrating both self-hosted and managed databases

### Database Model

The `Database` model includes the following fields for managed databases:

- `connection_type`: 'self-hosted' or 'managed'
- `provider`: Cloud provider identifier
- `external_host`: Database endpoint
- `external_port`: Database port
- `external_username`: Database username
- `external_password`: Encrypted database password
- `use_ssl`: SSL/TLS flag
- `ssl_ca`: SSL CA certificate (optional)
- `instance_identifier`: Provider's instance ID
- `region`: Provider's region

## API Integration

The providers are designed to integrate with cloud provider APIs. Currently, they include placeholder implementations that show the structure. To enable full functionality:

1. Install the appropriate SDK for your provider
2. Update the provider class to use the SDK
3. Configure authentication credentials

### AWS RDS Example

```bash
composer require aws/aws-sdk-php
```

Then update `AwsRdsProvider` to use the AWS SDK:

```php
use Aws\Rds\RdsClient;

$rdsClient = new RdsClient([
    'version' => 'latest',
    'region' => $config['region'],
    'credentials' => [
        'key' => $credentials['access_key'],
        'secret' => $credentials['secret_key'],
    ],
]);
```

## Testing

Run the managed database tests:

```bash
php artisan test --filter=ManagedDatabase
```

## Troubleshooting

### Connection Issues

1. **Check Firewall**: Ensure your server's IP is whitelisted in the cloud provider's security groups
2. **Verify Credentials**: Test credentials using the provider's CLI or console
3. **SSL Certificate**: Some providers require specific SSL certificates
4. **Port Access**: Ensure the database port is accessible from your server

### Provider-Specific Issues

#### AWS RDS
- Verify VPC security groups allow inbound traffic
- Check if the RDS instance is publicly accessible (if needed)
- Ensure IAM credentials have appropriate permissions

#### Azure Database
- Check firewall rules in Azure portal
- Verify SSL enforcement settings match your configuration
- Ensure service endpoint is correctly configured

#### DigitalOcean
- Verify trusted sources in database cluster settings
- Check that your droplet/server IP is added to the trusted sources
- API token must have read/write permissions

#### OVH
- Verify API credentials and permissions
- Check authorized IPs in OVH manager
- Ensure the correct endpoint is configured

#### Google Cloud SQL
- Check Cloud SQL Admin API is enabled
- Verify service account has necessary permissions
- Add authorized network in Cloud SQL instance settings

## Migration from Self-Hosted

To migrate from a self-hosted database to a managed one:

1. Export your current database using the backup feature
2. Create a new managed database connection
3. Import the backup to your managed database using the provider's tools
4. Update the database connection in the control panel
5. Test the connection
6. Update your applications to use the new connection

## Best Practices

1. **Use SSL/TLS**: Always enable encrypted connections
2. **Regular Backups**: Configure automated backups with your provider
3. **Monitor Metrics**: Keep track of database performance and usage
4. **Instance Sizing**: Choose appropriate instance sizes for your workload
5. **Multi-AZ**: Enable multi-availability zone for production databases (AWS, Azure)
6. **Read Replicas**: Consider read replicas for high-traffic applications
7. **Connection Pooling**: Use connection pooling in your applications

## Future Enhancements

Planned features:
- Automatic database provisioning from the control panel
- Integration with provider APIs for metrics and monitoring
- Automated scaling based on usage
- Database cloning and point-in-time recovery
- Support for additional providers (IBM Cloud, Oracle Cloud, etc.)
- Support for NoSQL databases (DynamoDB, CosmosDB, etc.)
