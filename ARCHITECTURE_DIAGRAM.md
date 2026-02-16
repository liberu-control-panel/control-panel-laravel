# Managed Database Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         Filament UI (DatabaseResource)                   │
│  ┌───────────────────────────────────────────────────────────────────┐  │
│  │  Connection Type: [Self-Hosted] [Managed Database]                │  │
│  │  Provider:        [AWS] [Azure] [DigitalOcean] [OVH] [GCP]       │  │
│  │  Host:            ___________________________________________      │  │
│  │  Port:            _____  Username: _________  Password: ___       │  │
│  │  SSL/TLS:         [✓] Enable     Region: ____________            │  │
│  └───────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                           DatabaseService                                │
│  ┌───────────────────────────────────────────────────────────────────┐  │
│  │  createDatabase(Domain $domain, array $data)                      │  │
│  │  deleteDatabase(Database $database)                               │  │
│  │  testManagedDatabaseConnection(Database $database)                │  │
│  └───────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────┘
                    │                                │
          Self-Hosted │                     Managed │
                    │                                │
                    ▼                                ▼
      ┌─────────────────────┐          ┌────────────────────────────────┐
      │ Container/Standalone │          │  ManagedDatabaseManager        │
      │   Database Service   │          │  ┌──────────────────────────┐  │
      └─────────────────────┘          │  │ getProvider()            │  │
                                       │  │ testConnection()         │  │
                                       │  │ createDatabase()         │  │
                                       │  │ deleteDatabase()         │  │
                                       │  │ getMetrics()            │  │
                                       │  └──────────────────────────┘  │
                                       └────────────────────────────────┘
                                                      │
                    ┌─────────────┬──────────────────┼──────────────┬─────────────┐
                    │             │                  │              │             │
                    ▼             ▼                  ▼              ▼             ▼
        ┌────────────────┐ ┌────────────────┐ ┌───────────────┐ ┌────────────┐ ┌────────────────┐
        │ AwsRdsProvider │ │ AzureDatabase  │ │ DigitalOcean  │ │    OVH     │ │ GoogleCloudSql │
        │                │ │    Provider    │ │   Provider    │ │  Provider  │ │    Provider    │
        ├────────────────┤ ├────────────────┤ ├───────────────┤ ├────────────┤ ├────────────────┤
        │ • RDS API      │ │ • Azure API    │ │ • DO API      │ │ • OVH API  │ │ • GCP API      │
        │ • Aurora       │ │ • MySQL        │ │ • MySQL       │ │ • MySQL    │ │ • Cloud SQL    │
        │ • MySQL        │ │ • PostgreSQL   │ │ • PostgreSQL  │ │ • Postgres │ │ • MySQL        │
        │ • PostgreSQL   │ │                │ │ • Redis       │ │ • Redis    │ │ • PostgreSQL   │
        └────────────────┘ └────────────────┘ └───────────────┘ └────────────┘ └────────────────┘
                    │             │                  │              │             │
                    └─────────────┴──────────────────┴──────────────┴─────────────┘
                                                      │
                                                      ▼
                                    ┌─────────────────────────────────┐
                                    │  BaseManagedDatabaseProvider    │
                                    │  ┌───────────────────────────┐  │
                                    │  │ testConnection()          │  │
                                    │  │ getConnectionDetails()    │  │
                                    │  │ mapEngineToDriver()       │  │
                                    │  │ validateConfig()          │  │
                                    │  │ getCredentials()          │  │
                                    │  │ logActivity()             │  │
                                    │  │ logError()                │  │
                                    │  └───────────────────────────┘  │
                                    └─────────────────────────────────┘
                                                      │
                                                      ▼
                                    ┌─────────────────────────────────┐
                                    │ ManagedDatabaseProvider         │
                                    │          Interface              │
                                    │  ┌───────────────────────────┐  │
                                    │  │ getName()                 │  │
                                    │  │ createDatabase()          │  │
                                    │  │ deleteDatabase()          │  │
                                    │  │ testConnection()          │  │
                                    │  │ databaseExists()          │  │
                                    │  │ getMetrics()              │  │
                                    │  │ scaleInstance()           │  │
                                    │  │ createBackup()            │  │
                                    │  │ restoreBackup()           │  │
                                    │  │ getAvailableInstances()   │  │
                                    │  │ getAvailableRegions()     │  │
                                    │  └───────────────────────────┘  │
                                    └─────────────────────────────────┘
                                                      │
                                                      ▼
                                    ┌─────────────────────────────────┐
                                    │      Database Model             │
                                    │  ┌───────────────────────────┐  │
                                    │  │ connection_type           │  │
                                    │  │ provider                  │  │
                                    │  │ external_host             │  │
                                    │  │ external_port             │  │
                                    │  │ external_username         │  │
                                    │  │ external_password (enc)   │  │
                                    │  │ use_ssl                   │  │
                                    │  │ instance_identifier       │  │
                                    │  │ region                    │  │
                                    │  └───────────────────────────┘  │
                                    └─────────────────────────────────┘
                                                      │
                                                      ▼
                                    ┌─────────────────────────────────┐
                                    │         Database Table          │
                                    │      (MySQL/PostgreSQL)         │
                                    └─────────────────────────────────┘
```

## Data Flow

### Creating a Managed Database Connection

1. User fills form in Filament UI (DatabaseResource)
2. Form data submitted to DatabaseService
3. DatabaseService creates Database model with managed fields
4. ManagedDatabaseManager tests connection via appropriate provider
5. Connection details stored encrypted in database
6. Success/failure returned to UI

### Using a Managed Database

1. Application requests database connection
2. DatabaseService retrieves Database model
3. If managed, retrieves external_host, external_port, credentials
4. Establishes SSL/TLS connection if enabled
5. Application uses connection normally

### Deleting a Managed Database

1. User clicks delete in UI
2. DatabaseService checks if managed
3. ManagedDatabaseManager delegates to provider
4. Provider optionally deletes cloud resource (if configured)
5. Database record deleted from local database

## Configuration Flow

```
.env file
   │
   ├─→ AWS_RDS_ENABLED=true
   ├─→ AWS_ACCESS_KEY_ID=xxx
   ├─→ AWS_SECRET_ACCESS_KEY=xxx
   │
   └─→ config/managed-databases.php
          │
          ├─→ 'aws' => [...credentials...]
          ├─→ 'azure' => [...credentials...]
          ├─→ 'digitalocean' => [...credentials...]
          ├─→ 'ovh' => [...credentials...]
          └─→ 'gcp' => [...credentials...]
                 │
                 └─→ ManagedDatabaseManager
                        │
                        └─→ Providers access via getCredentials()
```

## Security Layers

1. **Transport Security**: SSL/TLS for database connections
2. **Storage Security**: Laravel encryption for passwords
3. **API Security**: Provider API keys stored in environment
4. **Access Control**: Filament/Laravel authorization
5. **Input Validation**: Form validation and type checking

## Extension Points

To add a new provider:

1. Create new class implementing `ManagedDatabaseProviderInterface`
2. Extend `BaseManagedDatabaseProvider` for common functionality
3. Register in `ManagedDatabaseManager::registerProviders()`
4. Add configuration to `config/managed-databases.php`
5. Add environment variables to `.env.example`
6. Update `Database::getProviders()` constant array
7. Add provider documentation
