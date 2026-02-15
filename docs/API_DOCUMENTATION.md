# Control Panel API Documentation

## Overview

The Control Panel API provides programmatic access to manage your hosting resources including virtual hosts, databases, email accounts, and DNS records.

## Authentication

All API endpoints require authentication using Laravel Sanctum. You can create API tokens from the control panel or via the API.

### Creating an API Token

**Endpoint:** `POST /api/tokens`

**Request Body:**
```json
{
  "name": "My API Token",
  "abilities": ["*"]
}
```

**Response:**
```json
{
  "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
  "name": "My API Token"
}
```

### Using the Token

Include the token in the `Authorization` header:

```
Authorization: Bearer 1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

## Rate Limiting

API requests are limited to **60 requests per minute** per user or IP address.

## Endpoints

### User Management

#### Get Current User
```
GET /api/me
```

Returns the authenticated user's profile information.

#### Update Profile
```
PUT /api/me
```

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "username": "johndoe"
}
```

#### Get Statistics
```
GET /api/statistics
```

Returns resource usage statistics for the current user.

**Response:**
```json
{
  "virtual_hosts": 5,
  "databases": 3,
  "domains": 2,
  "email_accounts": 10,
  "dns_records": 25
}
```

---

### Virtual Host Management

#### List Virtual Hosts
```
GET /api/virtual-hosts?per_page=15
```

#### Get Virtual Host
```
GET /api/virtual-hosts/{id}
```

#### Create Virtual Host
```
POST /api/virtual-hosts
```

**Request Body:**
```json
{
  "hostname": "example.com",
  "domain_id": 1,
  "server_id": 1,
  "document_root": "/var/www/html",
  "php_version": "8.3",
  "ssl_enabled": true,
  "letsencrypt_enabled": true,
  "port": 80
}
```

**Response:**
```json
{
  "message": "Virtual host created successfully",
  "virtual_host": {
    "id": 1,
    "hostname": "example.com",
    "status": "active",
    "ssl_enabled": true,
    "letsencrypt_enabled": true,
    ...
  }
}
```

#### Update Virtual Host
```
PUT /api/virtual-hosts/{id}
```

#### Delete Virtual Host
```
DELETE /api/virtual-hosts/{id}
```

---

### Database Management

#### List Databases
```
GET /api/databases?per_page=15
```

#### Get Database
```
GET /api/databases/{id}
```

#### Create Database
```
POST /api/databases
```

**Request Body:**
```json
{
  "name": "myapp_db",
  "domain_id": 1,
  "engine": "mariadb",
  "charset": "utf8mb4",
  "collation": "utf8mb4_unicode_ci"
}
```

**Response:**
```json
{
  "message": "Database created successfully",
  "database": {
    "id": 1,
    "name": "username_myapp_db",
    "engine": "mariadb",
    ...
  },
  "credentials": {
    "database": "username_myapp_db",
    "username": "username_myapp_db",
    "password": "auto-generated-password",
    "host": "localhost"
  }
}
```

**Note:** The database name will be automatically prefixed with your username. A database user with the same name will be created automatically with full privileges.

#### Delete Database
```
DELETE /api/databases/{id}
```

---

### Email Management

#### List Email Accounts
```
GET /api/emails?per_page=15
```

#### Get Email Account
```
GET /api/emails/{id}
```

#### Create Email Account
```
POST /api/emails
```

**Request Body:**
```json
{
  "domain_id": 1,
  "email_address": "user@example.com",
  "password": "secure-password",
  "quota": 1024,
  "forwarding_rules": []
}
```

#### Update Email Account
```
PUT /api/emails/{id}
```

**Request Body:**
```json
{
  "password": "new-password",
  "quota": 2048
}
```

#### Delete Email Account
```
DELETE /api/emails/{id}
```

---

### DNS Management

#### List DNS Records
```
GET /api/dns?per_page=15
```

#### Get DNS Record
```
GET /api/dns/{id}
```

#### Create DNS Record
```
POST /api/dns
```

**Request Body:**
```json
{
  "domain_id": 1,
  "record_type": "A",
  "name": "@",
  "value": "192.168.1.1",
  "ttl": 3600,
  "priority": null
}
```

**Supported Record Types:**
- A (IPv4 address)
- AAAA (IPv6 address)
- CNAME (Canonical name)
- MX (Mail exchange)
- TXT (Text record)
- NS (Name server)
- SRV (Service)
- CAA (Certification Authority Authorization)

#### Update DNS Record
```
PUT /api/dns/{id}
```

#### Delete DNS Record
```
DELETE /api/dns/{id}
```

#### Bulk Create DNS Records
```
POST /api/dns/bulk
```

**Request Body:**
```json
{
  "domain_id": 1,
  "records": [
    {
      "record_type": "A",
      "name": "@",
      "value": "192.168.1.1",
      "ttl": 3600
    },
    {
      "record_type": "MX",
      "name": "@",
      "value": "mail.example.com",
      "ttl": 3600,
      "priority": 10
    }
  ]
}
```

---

## Error Responses

### Validation Error (422)
```json
{
  "errors": {
    "hostname": [
      "The hostname field is required."
    ]
  }
}
```

### Unauthorized (403)
```json
{
  "error": "Unauthorized"
}
```

### Server Error (500)
```json
{
  "error": "Failed to create virtual host: Connection timeout"
}
```

---

## Examples

### cURL Example
```bash
# Create a virtual host
curl -X POST https://your-control-panel.com/api/virtual-hosts \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "hostname": "example.com",
    "php_version": "8.3",
    "ssl_enabled": true,
    "letsencrypt_enabled": true
  }'
```

### PHP Example
```php
<?php

$client = new \GuzzleHttp\Client([
    'base_uri' => 'https://your-control-panel.com/api/',
    'headers' => [
        'Authorization' => 'Bearer YOUR_TOKEN',
        'Accept' => 'application/json',
    ]
]);

// Create a database
$response = $client->post('databases', [
    'json' => [
        'name' => 'myapp_db',
        'engine' => 'mariadb',
    ]
]);

$data = json_decode($response->getBody(), true);
echo "Database: " . $data['database']['name'] . "\n";
echo "Password: " . $data['credentials']['password'] . "\n";
```

### Python Example
```python
import requests

API_URL = "https://your-control-panel.com/api"
TOKEN = "YOUR_TOKEN"

headers = {
    "Authorization": f"Bearer {TOKEN}",
    "Content-Type": "application/json"
}

# Create DNS record
response = requests.post(
    f"{API_URL}/dns",
    headers=headers,
    json={
        "domain_id": 1,
        "record_type": "A",
        "name": "@",
        "value": "192.168.1.1",
        "ttl": 3600
    }
)

print(response.json())
```

---

## Support

For API support and questions, please contact support@liberu.co.uk or visit https://liberu.co.uk
