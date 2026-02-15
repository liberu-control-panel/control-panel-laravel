# DNS Cluster Helm Chart

This Helm chart deploys a PowerDNS-based DNS cluster for the Liberu Control Panel.

## Components

- **PowerDNS**: Authoritative DNS server with MySQL backend
- **PowerDNS Admin**: Web interface for DNS management (optional)

## Installation

```bash
helm install dns-cluster ./helm/dns-cluster \
  --namespace control-panel \
  --set powerdns.mysql.password=<secure-password> \
  --set powerdns.api.key=<secure-api-key>
```

## Configuration

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
  mysql:
    host: mariadb
    database: powerdns
    username: powerdns
    password: <secure-password>
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

PowerDNS requires a MySQL database. Ensure it's created:

```sql
CREATE DATABASE powerdns;
CREATE USER 'powerdns'@'%' IDENTIFIED BY 'secure-password';
GRANT ALL PRIVILEGES ON powerdns.* TO 'powerdns'@'%';
FLUSH PRIVILEGES;
```

The PowerDNS schema will be initialized automatically on first run.

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
