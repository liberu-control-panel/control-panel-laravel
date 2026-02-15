# Quick Start Guide - New Features

## 🚀 Getting Started

This guide helps you quickly get started with the new features added to the Liberu Control Panel.

---

## 1️⃣ Virtual Host Management

### Via GUI (Filament)

1. **Access:** Navigate to **Hosting → Virtual Hosts**
2. **Create:**
   - Click "Create"
   - Enter hostname (e.g., `mysite.com`)
   - Select PHP version (8.1-8.5)
   - Enable SSL/Let's Encrypt
   - Click "Save"
3. **Result:** NGINX virtual host created with automatic SSL certificate

### Via API

```bash
curl -X POST https://panel.example.com/api/virtual-hosts \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "hostname": "mysite.com",
    "php_version": "8.3",
    "ssl_enabled": true,
    "letsencrypt_enabled": true
  }'
```

---

## 2️⃣ Database Auto-Provisioning

### Via GUI (Filament)

1. **Access:** Navigate to **Hosting → Databases**
2. **Create:**
   - Click "Create"
   - Enter database name (e.g., `myapp`)
   - Select engine (MySQL/MariaDB/PostgreSQL)
   - Click "Save"
3. **Result:** 
   - Database created: `username_myapp`
   - User created: `username_myapp`
   - Full privileges granted automatically
   - Password displayed (save it!)

### Via API

```bash
curl -X POST https://panel.example.com/api/databases \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "myapp",
    "engine": "mariadb"
  }'

# Response includes auto-generated credentials:
# {
#   "database": "username_myapp",
#   "username": "username_myapp", 
#   "password": "auto-generated-secure-password",
#   "host": "localhost"
# }
```

---

## 3️⃣ API Access

### Create API Token

**Via GUI:**
1. Navigate to **Profile → API Tokens**
2. Click "Create Token"
3. Enter name and abilities
4. Copy the token (shown only once!)

**Via API:**
```bash
curl -X POST https://panel.example.com/api/tokens \
  -H "Authorization: Bearer EXISTING_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "My Application Token",
    "abilities": ["*"]
  }'
```

### Using the API

All API requests require authentication:

```bash
# Get your profile
curl https://panel.example.com/api/me \
  -H "Authorization: Bearer YOUR_TOKEN"

# Get statistics
curl https://panel.example.com/api/statistics \
  -H "Authorization: Bearer YOUR_TOKEN"

# List virtual hosts
curl https://panel.example.com/api/virtual-hosts \
  -H "Authorization: Bearer YOUR_TOKEN"

# List databases
curl https://panel.example.com/api/databases \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## 4️⃣ Email Management

### Create Email Account

**Via API:**
```bash
curl -X POST https://panel.example.com/api/emails \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "domain_id": 1,
    "email_address": "user@example.com",
    "password": "secure-password",
    "quota": 1024
  }'
```

---

## 5️⃣ DNS Management

### Create DNS Record

**Via API:**
```bash
curl -X POST https://panel.example.com/api/dns \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "domain_id": 1,
    "record_type": "A",
    "name": "@",
    "value": "192.168.1.1",
    "ttl": 3600
  }'
```

### Bulk Create DNS Records

```bash
curl -X POST https://panel.example.com/api/dns/bulk \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
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
  }'
```

---

## 6️⃣ Kubernetes Helm Charts

### Access Helm Management

1. **Login as Admin**
2. **Navigate to:** Admin Panel → Kubernetes → Helm Charts
3. **Install Chart:**
   - Click "Install Chart"
   - Select chart type
   - Configure values
   - Click "Install"

---

## 📚 Complete Documentation

- **API Reference:** `docs/API_DOCUMENTATION.md`
- **Implementation Details:** `docs/IMPLEMENTATION_SUMMARY.md`
- **Completion Report:** `COMPLETION_REPORT.md`

---

## 🆘 Support

- **Issues:** https://github.com/liberu-control-panel/control-panel-laravel/issues
- **Website:** https://liberu.co.uk
- **Email:** support@liberu.co.uk

---

## 🔐 Security Notes

- API tokens are sensitive - store them securely
- Rate limit: 60 requests per minute
- All API endpoints require authentication
- Virtual hosts automatically get Let's Encrypt SSL certificates
- Database passwords are auto-generated and secure

---

## ✅ Quick Checklist for New Users

- [ ] Create your first API token
- [ ] Create a virtual host for your website
- [ ] Set up a database (credentials auto-generated)
- [ ] Configure DNS records
- [ ] Create email accounts
- [ ] Review API documentation for automation

---

**Enjoy your enhanced control panel! 🎉**
