# Secrets Directory

This directory contains sensitive credentials for Docker and Kubernetes deployments.

## Important Security Notes

⚠️ **NEVER commit actual secrets to version control!**

The `.gitignore` file in this directory is configured to ignore all `.txt` files containing secrets.

## Usage

### Docker Compose

When using Docker Compose, secrets are read from files in this directory:

```bash
# Create your secret files
echo "your-secure-db-password" > secrets/db_password.txt
echo "your-secure-root-password" > secrets/db_root_password.txt
echo "base64:your-app-key" > secrets/app_key.txt
```

### Kubernetes

For Kubernetes deployments, secrets should be created using `kubectl`:

```bash
# Create Kubernetes secrets
kubectl create secret generic control-panel-secrets \
  --from-literal=db_password='your-secure-db-password' \
  --from-literal=db_root_password='your-secure-root-password' \
  --from-literal=app_key='base64:your-app-key' \
  -n control-panel
```

## Required Secrets

- `db_password.txt` - Database user password
- `db_root_password.txt` - Database root password
- `app_key.txt` (optional) - Laravel application key

## Generating Secure Passwords

```bash
# Generate random passwords
openssl rand -base64 32 > secrets/db_password.txt
openssl rand -base64 32 > secrets/db_root_password.txt

# Generate Laravel APP_KEY
php artisan key:generate --show > secrets/app_key.txt
```

## Production Best Practices

1. Use strong, randomly generated passwords
2. Rotate secrets regularly
3. Use secret management tools (Vault, AWS Secrets Manager, etc.)
4. Limit file permissions: `chmod 600 secrets/*.txt`
5. Never log or display secret values
6. Use different secrets for each environment
