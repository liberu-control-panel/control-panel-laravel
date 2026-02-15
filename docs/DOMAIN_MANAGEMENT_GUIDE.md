# Domain Management User Guide

## Overview

The improved domain management system provides a streamlined interface for managing domain names, DNS records, and SSL certificates with enhanced validation and user feedback.

## Features

### Domain Management

#### Creating a New Domain

1. Navigate to **Domains** in the main menu
2. Click **Create Domain**
3. Fill in the required information:

**Domain Information Section:**
- **Domain Name**: Enter your domain (e.g., `example.com`)
  - Auto-validates domain format
  - Checks for uniqueness
  - Auto-fills Virtual Host and SSL settings
- **Server**: Select the hosting server
  - Can create a new server on the fly
- **Registration Date**: When the domain was registered
- **Expiration Date**: When the domain expires
  - System will warn you 30 days before expiration

**SSL Configuration Section:**
- **Let's Encrypt Host**: Domain for SSL certificate (auto-filled)
- **Let's Encrypt Email**: Email for certificate notifications

**Server Access Credentials Section:**
- **SFTP Username/Password**: For file access
- **SSH Username/Password**: For shell access
- Passwords are revealable for easy copying
- Minimum 8 characters recommended

#### Managing Domains

**Domain List Features:**
- **Status Badges**: Visual indicators for domain status
  - 🟢 Active: Domain is active and valid
  - 🟡 Expiring Soon: Less than 30 days until expiration
  - 🔴 Expired: Domain has expired
- **DNS Record Count**: Shows number of DNS records per domain
- **Quick Actions**: Manage DNS directly from domain list
- **Filters**: Filter by server, expiring domains, or expired domains
- **Search**: Search by domain name
- **Copyable Fields**: Click to copy domain names

### DNS Record Management

#### Supported Record Types

1. **A Record** - IPv4 Address
   - Points domain to an IPv4 address
   - Required for website hosting
   - Example: `192.0.2.1`

2. **AAAA Record** - IPv6 Address
   - Points domain to an IPv6 address
   - Optional but recommended for modern connectivity
   - Example: `2001:0db8::1`

3. **CNAME Record** - Canonical Name
   - Creates alias to another domain
   - Cannot be used on root domain (@)
   - Useful for subdomains
   - Example: `www` → `example.com`

4. **MX Record** - Mail Exchange
   - Directs email to mail servers
   - Requires priority value (lower = higher priority)
   - Example: Priority 10, `mail.example.com`

5. **TXT Record** - Text Record
   - Stores text information
   - Used for SPF, DKIM, domain verification
   - Maximum 512 characters
   - Example: `v=spf1 include:_spf.example.com ~all`

6. **NS Record** - Name Server
   - Delegates subdomain to other nameservers
   - Advanced usage
   - Example: `ns1.example.com`

7. **PTR Record** - Pointer Record
   - Reverse DNS lookup
   - Maps IP to domain name
   - Example: `example.com`

8. **SRV Record** - Service Record
   - Specifies location of services
   - Advanced usage
   - Example: `_service._proto.example.com`

#### Creating DNS Records

1. Navigate to **DNS Records** or click **Manage DNS** from a domain
2. Click **Create DNS Record**
3. Fill in the form:

**DNS Record Details:**
- **Domain**: Select the domain
- **Record Type**: Choose from dropdown with descriptions

**Record Configuration:**
- **Record Name**: 
  - Use `@` for root domain
  - Enter subdomain name (e.g., `www`, `mail`)
  - Validates format automatically
- **Record Value**: 
  - Context-sensitive placeholder based on record type
  - Automatic validation for IPs, hostnames
  - Helpful examples shown
- **TTL (Time To Live)**:
  - Recommended: 3600 seconds (1 hour)
  - Range: 60 - 86400 seconds
  - Lower values propagate changes faster
- **Priority** (MX records only):
  - Lower values = higher priority
  - Typical values: 10, 20, 30

**Information Section:**
- Expandable section with tips for each record type
- Best practices and usage guidelines

#### Managing DNS Records

**DNS Records Table Features:**
- **Color-coded Type Badges**: Easy visual identification
- **Copyable Values**: Click to copy record values
- **Filters**: Filter by domain or record type
- **Bulk Actions**: 
  - Delete multiple records
  - Update TTL for multiple records
- **Auto-refresh**: Table updates every 30 seconds
- **Smart Display**: Shows relevant fields based on record type

### Validation and Error Handling

#### Domain Name Validation
- Must be valid domain format (e.g., `example.com`)
- No protocols (http://, https://)
- Each label 1-63 characters
- Total length max 253 characters
- TLD must be 2+ characters

#### DNS Record Validation
- **A Records**: Must be valid IPv4 address
- **AAAA Records**: Must be valid IPv6 address
- **CNAME/NS/MX**: Must be valid hostname
- **TXT Records**: ASCII characters, max 512 characters
- **TTL**: 60-86400 seconds
- **Priority**: 0-65535 (MX records)

#### Error Messages
- Clear, actionable error messages
- Field-specific validation feedback
- Helpful suggestions for corrections

## API Integration

### Available Endpoints

All endpoints require authentication via Sanctum token.

#### List DNS Records
```
GET /api/dns
```
Query Parameters:
- `domain_id`: Filter by domain
- `record_type`: Filter by record type
- `per_page`: Results per page (default: 15)

#### Create DNS Record
```
POST /api/dns
```
Body:
```json
{
  "domain_id": 1,
  "record_type": "A",
  "name": "@",
  "value": "192.0.2.1",
  "ttl": 3600
}
```

#### Update DNS Record
```
PUT /api/dns/{id}
```

#### Delete DNS Record
```
DELETE /api/dns/{id}
```

#### Bulk Create Records
```
POST /api/dns/bulk
```
Body:
```json
{
  "domain_id": 1,
  "records": [
    {
      "record_type": "A",
      "name": "@",
      "value": "192.0.2.1",
      "ttl": 3600
    },
    {
      "record_type": "MX",
      "name": "@",
      "value": "mail.example.com",
      "priority": 10,
      "ttl": 3600
    }
  ]
}
```

#### Validate DNS Record
```
POST /api/dns/validate
```
Validates a DNS record before creation.

#### Test DNS Resolution
```
GET /api/domains/{domain}/dns/test?record_type=A
```
Tests DNS resolution for a domain.

#### Check DNS Propagation
```
GET /api/domains/{domain}/dns/propagation
```
Checks DNS propagation across multiple nameservers.

### Response Format

Success Response:
```json
{
  "success": true,
  "message": "DNS record created successfully",
  "data": {
    "id": 1,
    "domain_id": 1,
    "record_type": "A",
    "name": "@",
    "value": "192.0.2.1",
    "ttl": 3600,
    "created_at": "2026-02-15T12:00:00Z"
  }
}
```

Error Response:
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "value": ["The value must be a valid IPv4 address"]
  }
}
```

## Best Practices

### Domain Management
1. Set expiration date correctly to receive timely renewal reminders
2. Use strong, unique passwords for SFTP/SSH access
3. Keep Let's Encrypt email updated for SSL notifications
4. Regularly review and update domain records

### DNS Record Management
1. Start with default TTL (3600 seconds)
2. Lower TTL before making changes for faster propagation
3. Use @ for root domain records
4. Always test DNS changes after creation
5. Keep MX record priorities organized (10, 20, 30)
6. Document TXT records (SPF, DKIM) for email deliverability
7. Use bulk import for migrating multiple records
8. Regularly check DNS propagation status

### Security
1. Don't share SFTP/SSH credentials
2. Rotate passwords regularly
3. Use unique passwords per domain
4. Enable SSL/TLS via Let's Encrypt
5. Monitor DNS changes for unauthorized modifications

## Troubleshooting

### Domain Won't Save
- Check domain name format
- Verify domain isn't already registered
- Ensure all required fields are filled
- Check password length (minimum 8 characters)

### DNS Record Validation Fails
- Verify record value matches type (e.g., valid IP for A record)
- Check TTL is within range (60-86400)
- Ensure MX records have priority set
- Validate hostname format for CNAME/NS/MX

### DNS Changes Not Propagating
1. Check DNS propagation status
2. Wait for TTL to expire
3. Verify BIND service is running
4. Check DNS service logs
5. Test resolution from different nameservers

### API Errors
- Verify authentication token is valid
- Check request format matches documentation
- Review error messages for specific validation issues
- Ensure proper permissions for domain access

## Support

For additional help:
- Review error messages carefully
- Check system logs for detailed information
- Contact support with domain name and error details
- Include screenshots for UI-related issues
