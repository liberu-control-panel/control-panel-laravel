# Database Backend Migration Guide

This guide explains how to switch between MariaDB/MySQL and PostgreSQL database backends in the Liberu Control Panel.

## Default Configuration

**MariaDB is the default and recommended database backend** for the Liberu Control Panel. It provides:
- Proven stability and performance
- Wide community support and documentation
- Simpler configuration and maintenance
- Better compatibility with PowerDNS

## Switching from MariaDB to PostgreSQL

If you need to switch to PostgreSQL, follow these steps:

### Docker Compose Deployment

1. **Stop the current services:**
   ```bash
   docker compose down
   ```

2. **Backup your existing MariaDB data:**
   ```bash
   # Create a backup directory
   mkdir -p backups/$(date +%Y%m%d)
   
   # Export the database
   docker compose up -d mysql
   docker compose exec mysql mysqldump -u root -p myapp > backups/$(date +%Y%m%d)/myapp_backup.sql
   docker compose down
   ```

3. **Update your `.env` file:**
   ```bash
   # Change from:
   DB_CONNECTION=mysql
   DB_HOST=mysql
   DB_PORT=3306
   
   # To:
   DB_CONNECTION=pgsql
   DB_HOST=postgresql
   DB_PORT=5432
   ```

4. **Start services with PostgreSQL profile:**
   ```bash
   docker compose --profile postgresql up -d
   ```

5. **Run migrations:**
   ```bash
   docker compose exec control-panel php artisan migrate:fresh --seed
   ```

6. **Restore your data** (optional, requires manual data conversion):
   Note: Direct SQL import from MySQL to PostgreSQL requires conversion. Consider using a migration tool or rebuilding your data.

### Kubernetes/Helm Deployment

1. **Backup your existing data:**
   ```bash
   # For MariaDB
   kubectl exec -n control-panel dns-cluster-mysql-0 -- \
     mysqldump -u root -p powerdns > powerdns_backup.sql
   ```

2. **Update your Helm values:**
   ```yaml
   # values.yaml or custom-values.yaml
   databaseBackend: postgresql
   
   powerdns:
     postgresql:
       host: postgresql  # Or your external PostgreSQL host
       port: 5432
       database: powerdns
       username: powerdns
       password: "your-secure-password"
   ```

3. **Upgrade the Helm release:**
   ```bash
   helm upgrade dns-cluster ./helm/dns-cluster \
     -f custom-values.yaml \
     --namespace control-panel
   ```

4. **Verify the deployment:**
   ```bash
   kubectl get pods -n control-panel
   kubectl logs -n control-panel dns-cluster-powerdns-0
   ```

## Switching from PostgreSQL to MariaDB

If you're currently using PostgreSQL and want to switch to MariaDB:

### Docker Compose Deployment

1. **Stop the current services:**
   ```bash
   docker compose --profile postgresql down
   ```

2. **Backup your PostgreSQL data:**
   ```bash
   mkdir -p backups/$(date +%Y%m%d)
   docker compose --profile postgresql up -d postgresql
   docker compose exec postgresql pg_dump -U myapp myapp > backups/$(date +%Y%m%d)/myapp_backup.sql
   docker compose --profile postgresql down
   ```

3. **Update your `.env` file:**
   ```bash
   # Change from:
   DB_CONNECTION=pgsql
   DB_HOST=postgresql
   DB_PORT=5432
   
   # To:
   DB_CONNECTION=mysql
   DB_HOST=mysql
   DB_PORT=3306
   ```

4. **Start services (MariaDB is default, no profile needed):**
   ```bash
   docker compose up -d
   ```

5. **Run migrations:**
   ```bash
   docker compose exec control-panel php artisan migrate:fresh --seed
   ```

### Kubernetes/Helm Deployment

1. **Backup your PostgreSQL data:**
   ```bash
   kubectl exec -n control-panel dns-cluster-postgresql-0 -- \
     pg_dump -U powerdns powerdns > powerdns_backup.sql
   ```

2. **Update your Helm values (or remove the override to use default):**
   ```yaml
   # values.yaml or custom-values.yaml
   databaseBackend: mysql  # This is the default, can be omitted
   
   powerdns:
     mysql:
       host: mariadb  # Or your external MariaDB host
       port: 3306
       database: powerdns
       username: powerdns
       password: "your-secure-password"
   ```

3. **Upgrade the Helm release:**
   ```bash
   helm upgrade dns-cluster ./helm/dns-cluster \
     -f custom-values.yaml \
     --namespace control-panel
   ```

## Performance Considerations

### MariaDB (Recommended)
- **Best for:** Most DNS workloads, production environments
- **Advantages:** Better PowerDNS integration, proven performance, simpler setup
- **Resource usage:** Lower memory footprint for typical DNS workloads

### PostgreSQL
- **Best for:** Users with existing PostgreSQL expertise, specific PostgreSQL requirements
- **Advantages:** Advanced features, JSONB support, better concurrency in some scenarios
- **Resource usage:** Slightly higher memory usage

## Troubleshooting

### Connection Issues

If you're experiencing connection issues after switching:

1. **Verify the database is running:**
   ```bash
   # Docker Compose
   docker compose ps
   
   # Kubernetes
   kubectl get pods -n control-panel
   ```

2. **Check the connection settings:**
   ```bash
   # Docker Compose
   docker compose exec control-panel php artisan tinker
   >>> DB::connection()->getPdo();
   
   # Kubernetes
   kubectl exec -n control-panel deployment/control-panel-app -- php artisan tinker
   ```

3. **Review logs:**
   ```bash
   # Docker Compose
   docker compose logs control-panel
   
   # Kubernetes
   kubectl logs -n control-panel deployment/control-panel-app
   ```

### Data Migration Tools

For complex data migrations between MySQL and PostgreSQL:

- **pgLoader**: Automated MySQL to PostgreSQL migration
  ```bash
  pgloader mysql://user:pass@localhost/myapp postgresql://user:pass@localhost/myapp
  ```

- **Manual approach**: Export as CSV, transform, import
- **Application-level**: Use Laravel seeders to rebuild data

## Best Practices

1. **Always backup data** before switching database backends
2. **Test in a development environment** before production migration
3. **Plan for downtime** during the migration
4. **Verify data integrity** after migration
5. **Monitor performance** after switching to ensure optimal configuration

## Getting Help

If you encounter issues during migration:

1. Check the [GitHub Issues](https://github.com/liberu-control-panel/control-panel-laravel/issues)
2. Review the [Documentation](https://github.com/liberu-control-panel/control-panel-laravel/docs)
3. Join the community discussions
