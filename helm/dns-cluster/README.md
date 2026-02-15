# DNS Cluster Helm Chart

This Helm chart deploys a PowerDNS-based DNS cluster for the Liberu Control Panel with support for both MySQL/MariaDB and PostgreSQL backends.

**Default Database Backend: MariaDB** - MariaDB is the default and recommended database backend for PowerDNS. PostgreSQL is available as an alternative option for users who prefer it.

## Components

- **PowerDNS**: Authoritative DNS server with configurable database backend
- **Database**: MariaDB (default) or PostgreSQL (optional, automatically deployed)
- **PowerDNS Admin**: Web interface for DNS management (optional)

## Database Backend Selection

The chart supports two database backends, with **MariaDB as the default**:

### MariaDB/MySQL (Default - Recommended)
```yaml
databaseBackend: mysql  # This is the default value
```

MariaDB is the **recommended and default** backend for PowerDNS due to its:
- Proven stability with PowerDNS
- Better performance for DNS workloads
- Simpler configuration and maintenance
- Wide community support

### PostgreSQL (Optional Alternative)
```yaml
databaseBackend: postgresql
```

PostgreSQL is available as an alternative for users who:
- Have existing PostgreSQL infrastructure
- Prefer PostgreSQL for organizational reasons
- Need specific PostgreSQL features

The database backend can be selected via the `databaseBackend` value. The chart will automatically:
- Deploy the appropriate database StatefulSet
- Configure PowerDNS with the correct driver (gmysql or gpgsql)
- Initialize the database schema

## Installation

### Quick Start (MariaDB - Default)

The simplest installation uses the default MariaDB backend:

```bash
helm install dns-cluster ./helm/dns-cluster \
  --namespace control-panel \
  --set powerdns.mysql.password=<secure-password> \
  --set powerdns.api.key=<secure-api-key>
```

This will deploy PowerDNS with MariaDB as the database backend.

### Alternative: PostgreSQL Backend

To use PostgreSQL instead of the default MariaDB:

```bash
helm install dns-cluster ./helm/dns-cluster \
  --namespace control-panel \
  --set databaseBackend=postgresql \
  --set powerdns.postgresql.password=<secure-password> \
  --set powerdns.api.key=<secure-api-key>
```

## Configuration

### Database Backend Configuration

**Note:** If you don't specify `databaseBackend`, MariaDB is used by default.

#### MariaDB/MySQL Configuration (Default)
```yaml
# databaseBackend: mysql  # This is the default, can be omitted
powerdns:
  mysql:
    host: mariadb  # Or use the auto-deployed: dns-cluster-mysql
    port: 3306
    database: powerdns
    username: powerdns
    password: <secure-password>
```

#### PostgreSQL Configuration (Optional)
```yaml
databaseBackend: postgresql  # Must be explicitly set for PostgreSQL
powerdns:
  postgresql:
    host: postgresql  # Or use the auto-deployed: dns-cluster-postgresql
    port: 5432
    database: powerdns
    username: powerdns
    password: <secure-password>
```

### PowerDNS Configuration

```yaml
powerdns:
  enabled: true
  replicaCount: 3
  api:
    enabled: true
    key: change-this-api-key
  config:
    allowAxfrIps: "0.0.0.0/0"
    masterServer: "yes"
    slaveServer: "yes"
    # Performance tuning
    maxConnections: 100
    queryCacheEnabled: true
    queryCacheTtl: 20
```

### PowerDNS Admin (Optional)

```yaml
powerdnsAdmin:
  enabled: true
  replicaCount: 2
  ingress:
    enabled: true
    hosts:
      - host: dns-admin.example.com
```

## Services Exposed

### PowerDNS
- Port 53 (TCP/UDP): DNS queries
- Port 8081: REST API (internal)

### Load Balancer

The service is exposed via LoadBalancer for external DNS queries:

```bash
kubectl get svc -n control-panel dns-cluster-powerdns
```

## Database Setup

### Using Auto-Deployed Database

The chart automatically deploys a database StatefulSet based on your `databaseBackend` selection. No manual database setup is required.

### Using External Database

If you prefer to use an external database:

#### For MySQL/MariaDB
```sql
CREATE DATABASE powerdns;
CREATE USER 'powerdns'@'%' IDENTIFIED BY 'secure-password';
GRANT ALL PRIVILEGES ON powerdns.* TO 'powerdns'@'%';
FLUSH PRIVILEGES;
```

Then set the host to your external database:
```yaml
powerdns:
  mysql:
    host: external-mariadb.example.com
    port: 3306
```

#### For PostgreSQL
```sql
CREATE DATABASE powerdns;
CREATE USER powerdns WITH PASSWORD 'secure-password';
GRANT ALL PRIVILEGES ON DATABASE powerdns TO powerdns;
```

Then set the host to your external database:
```yaml
powerdns:
  postgresql:
    host: external-postgres.example.com
    port: 5432
```

The PowerDNS schema will be initialized automatically on first run for both backends.

## API Usage

Access the PowerDNS API:

```bash
# Port-forward the API
kubectl port-forward -n control-panel svc/dns-cluster-powerdns 8081:8081

# Get zones
curl -H "X-API-Key: your-api-key" http://localhost:8081/api/v1/servers/localhost/zones
```

## Secondary Nameservers

Configure secondary nameservers in values:

```yaml
secondaryNameservers:
  - ns1.example.com
  - ns2.example.com
  - ns3.example.com
```

## DNSSEC (Optional)

Enable DNSSEC:

```yaml
dnssec:
  enabled: true
```

## High Availability

For production deployments:

```bash
helm install dns-cluster ./helm/dns-cluster \
  --set powerdns.replicaCount=5 \
  --set powerdnsAdmin.enabled=true \
  --set powerdns.service.type=LoadBalancer
```

## Monitoring

PowerDNS includes:
- Liveness probes on port 53
- Readiness probes on port 53
- Optional metrics endpoint

## Testing

Test DNS resolution:

```bash
# Get LoadBalancer IP
LB_IP=$(kubectl get svc -n control-panel dns-cluster-powerdns -o jsonpath='{.status.loadBalancer.ingress[0].ip}')

# Query DNS
dig @$LB_IP example.com
```

## Uninstall

```bash
helm uninstall dns-cluster --namespace control-panel
```
