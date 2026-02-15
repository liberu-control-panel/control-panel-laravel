# Mail Services Helm Chart

This Helm chart deploys Postfix and Dovecot mail services for the Liberu Control Panel.

## Components

- **Postfix**: SMTP server for sending emails
- **Dovecot**: IMAP/POP3 server for receiving emails

## Installation

```bash
helm install mail-services ./helm/mail-services \
  --namespace control-panel \
  --set postfix.config.domain=yourdomain.com \
  --set postfix.config.hostname=mail.yourdomain.com \
  --set dovecot.config.hostname=mail.yourdomain.com
```

## Configuration

### Postfix Configuration

```yaml
postfix:
  enabled: true
  replicaCount: 2
  config:
    hostname: mail.example.com
    domain: example.com
    # Optional relay configuration
    relayHost: smtp.sendgrid.net
    relayPort: 587
    relayUser: apikey
    relayPassword: <your-api-key>
```

### Dovecot Configuration

```yaml
dovecot:
  enabled: true
  replicaCount: 2
  config:
    hostname: mail.example.com
    protocols: imap pop3 lmtp
  persistence:
    enabled: true
    size: 20Gi
```

## Services Exposed

### Postfix
- Port 587: SMTP submission (TLS)

### Dovecot
- Port 143: IMAP
- Port 993: IMAPS (TLS)
- Port 110: POP3
- Port 995: POP3S (TLS)
- Port 24: LMTP (internal)

## TLS/SSL Configuration

The chart automatically configures TLS using cert-manager:

```yaml
tls:
  enabled: true
  issuer: letsencrypt-prod
```

## Usage Examples

### With External Relay

```bash
helm install mail-services ./helm/mail-services \
  --set postfix.config.relayHost=smtp.gmail.com \
  --set postfix.config.relayPort=587 \
  --set postfix.config.relayUser=your-email@gmail.com \
  --set postfix.config.relayPassword=your-app-password
```

### High Availability

```bash
helm install mail-services ./helm/mail-services \
  --set postfix.replicaCount=3 \
  --set dovecot.replicaCount=3 \
  --set dovecot.persistence.size=50Gi
```

## Monitoring

Both Postfix and Dovecot include health checks:
- Liveness probes on ports 587 (Postfix) and 143 (Dovecot)
- Readiness probes to ensure services are ready

## Persistence

Dovecot uses StatefulSet with persistent volumes for mail storage:
- Each pod gets its own PVC
- Storage class: configurable (default: standard)
- Size: configurable (default: 20Gi)

## Uninstall

```bash
helm uninstall mail-services --namespace control-panel
```

Note: PVCs are not automatically deleted. Delete manually if needed:
```bash
kubectl delete pvc -n control-panel -l app=dovecot
```
