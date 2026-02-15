# Multi-Site Management

This document describes the Multi-Site Management feature that allows administrators to efficiently manage multiple websites from a single control panel dashboard.

## Overview

The Multi-Site Management feature provides comprehensive tools for:
- Creating and managing multiple websites
- Monitoring website performance and uptime
- Tracking resource usage across sites
- Managing SSL certificates
- Configuring platform-specific settings

## Features

### Website Management

- **CRUD Operations**: Create, read, update, and delete website configurations
- **Platform Support**: WordPress, Laravel, Static HTML, Node.js, and custom platforms
- **PHP Version Management**: Support for PHP 8.1, 8.2, 8.3, and 8.4
- **Database Integration**: MySQL, MariaDB, PostgreSQL, and SQLite support
- **SSL/TLS Configuration**: Automated SSL certificate management with Let's Encrypt

### Performance Monitoring

The system tracks the following metrics for each website:

- **Uptime Percentage**: Real-time uptime monitoring with historical data
- **Response Time**: Average response time in milliseconds
- **Visitor Statistics**: Monthly visitor counts
- **Bandwidth Usage**: Monthly bandwidth consumption tracking
- **Disk Usage**: Storage space utilization
- **Status Checks**: Regular health checks with status codes

### Dashboard Widgets

The website management dashboard includes:
- Total websites count
- Active websites indicator
- Average uptime across all sites
- Total monthly visitors
- Performance trends and alerts

## Database Schema

### Websites Table

```sql
websites
├── id
├── user_id (foreign key to users)
├── server_id (foreign key to servers, nullable)
├── name
├── domain (unique)
├── description
├── platform (wordpress|laravel|static|nodejs|custom)
├── php_version
├── database_type
├── document_root
├── status (active|inactive|pending|maintenance|error)
├── ssl_enabled
├── auto_ssl
├── uptime_percentage (decimal)
├── last_checked_at
├── average_response_time (integer, ms)
├── monthly_bandwidth (bigint, bytes)
├── monthly_visitors (integer)
├── disk_usage_mb (decimal)
├── created_at
└── updated_at
```

### Website Performance Metrics Table

```sql
website_performance_metrics
├── id
├── website_id (foreign key to websites)
├── response_time_ms
├── status_code
├── uptime_status (boolean)
├── cpu_usage (decimal, nullable)
├── memory_usage (decimal, nullable)
├── disk_usage (decimal, nullable)
├── bandwidth_used (bigint, bytes)
├── visitors_count
├── checked_at
├── created_at
└── updated_at
```

## API Endpoints

### List Websites

```
GET /api/websites
```

**Query Parameters:**
- `per_page` (optional): Number of results per page (default: 15)

**Response:**
```json
{
  "current_page": 1,
  "data": [
    {
      "id": 1,
      "name": "My Website",
      "domain": "example.com",
      "platform": "wordpress",
      "status": "active",
      "uptime_percentage": 99.95,
      "average_response_time": 245,
      "monthly_visitors": 15000,
      "ssl_enabled": true,
      "created_at": "2026-02-15T10:00:00.000000Z"
    }
  ],
  "total": 10,
  "per_page": 15
}
```

### Get Website Details

```
GET /api/websites/{id}
```

**Response:**
```json
{
  "id": 1,
  "name": "My Website",
  "domain": "example.com",
  "description": "My awesome website",
  "platform": "wordpress",
  "php_version": "8.3",
  "database_type": "mysql",
  "document_root": "/var/www/html",
  "status": "active",
  "ssl_enabled": true,
  "auto_ssl": true,
  "uptime_percentage": 99.95,
  "average_response_time": 245,
  "monthly_visitors": 15000,
  "monthly_bandwidth": 1073741824,
  "disk_usage_mb": 512.50,
  "last_checked_at": "2026-02-15T19:55:00.000000Z",
  "server": {
    "id": 1,
    "name": "web-server-1"
  },
  "performance_metrics": [
    {
      "id": 1,
      "response_time_ms": 245,
      "status_code": 200,
      "uptime_status": true,
      "checked_at": "2026-02-15T19:55:00.000000Z"
    }
  ]
}
```

### Create Website

```
POST /api/websites
```

**Request Body:**
```json
{
  "name": "My New Website",
  "domain": "newsite.com",
  "description": "Description of my website",
  "platform": "laravel",
  "php_version": "8.3",
  "database_type": "mysql",
  "document_root": "/var/www/html",
  "server_id": 1,
  "ssl_enabled": true,
  "auto_ssl": true
}
```

**Response:**
```json
{
  "message": "Website created successfully",
  "website": {
    "id": 2,
    "name": "My New Website",
    "domain": "newsite.com",
    "status": "pending",
    ...
  }
}
```

### Update Website

```
PUT /api/websites/{id}
```

**Request Body:**
```json
{
  "name": "Updated Website Name",
  "status": "active",
  "php_version": "8.4"
}
```

**Response:**
```json
{
  "message": "Website updated successfully",
  "website": {
    "id": 2,
    "name": "Updated Website Name",
    ...
  }
}
```

### Delete Website

```
DELETE /api/websites/{id}
```

**Response:**
```json
{
  "message": "Website deleted successfully"
}
```

### Get Performance Metrics

```
GET /api/websites/{id}/performance?hours=24
```

**Query Parameters:**
- `hours` (optional): Number of hours of historical data (default: 24)

**Response:**
```json
{
  "website": {
    "id": 1,
    "name": "My Website",
    "domain": "example.com"
  },
  "metrics": [
    {
      "id": 1,
      "response_time_ms": 245,
      "status_code": 200,
      "uptime_status": true,
      "checked_at": "2026-02-15T19:00:00.000000Z"
    }
  ],
  "summary": {
    "uptime_percentage": 99.95,
    "average_response_time": 245,
    "total_checks": 48,
    "successful_checks": 48,
    "failed_checks": 0
  }
}
```

### Get Website Statistics

```
GET /api/websites-statistics
```

**Response:**
```json
{
  "total_websites": 10,
  "active_websites": 8,
  "total_visitors": 150000,
  "total_bandwidth": 10737418240,
  "average_uptime": 99.87,
  "websites_by_platform": [
    {"platform": "wordpress", "count": 5},
    {"platform": "laravel", "count": 3},
    {"platform": "static", "count": 2}
  ],
  "websites_by_status": [
    {"status": "active", "count": 8},
    {"status": "pending", "count": 1},
    {"status": "maintenance", "count": 1}
  ]
}
```

## Filament Admin Panel

### Accessing Website Management

1. Navigate to **Multi-Site Management → Websites** in the admin panel
2. View the dashboard with website statistics
3. Use the table to browse, filter, and search websites

### Creating a Website

1. Click **Create** button
2. Fill in the required fields:
   - Website Name
   - Domain
   - Description (optional)
   - Platform
   - PHP Version (if applicable)
   - Database Type
   - Document Root
   - Server (optional)
   - SSL/TLS settings
3. Click **Save**

### Viewing Website Details

1. Click on a website in the list
2. View comprehensive information including:
   - Basic information
   - Configuration details
   - Performance metrics
   - Timestamps

### Editing a Website

1. Click **Edit** action on a website
2. Modify the desired fields
3. Click **Save**

### Monitoring Performance

The dashboard displays:
- Real-time uptime percentage
- Average response time
- Monthly visitor counts
- Bandwidth usage
- Health status indicators (excellent, good, fair, poor)

### Filtering and Searching

Available filters:
- Status (active, inactive, pending, maintenance, error)
- Platform (WordPress, Laravel, Static, Node.js, Custom)
- SSL Enabled
- Active Only

## Usage Examples

### Creating a WordPress Website via API

```bash
curl -X POST https://panel.example.com/api/websites \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "My WordPress Blog",
    "domain": "blog.example.com",
    "description": "My personal blog",
    "platform": "wordpress",
    "php_version": "8.3",
    "database_type": "mysql",
    "ssl_enabled": true,
    "auto_ssl": true
  }'
```

### Monitoring Website Performance

```bash
curl -X GET https://panel.example.com/api/websites/1/performance?hours=48 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Getting Overall Statistics

```bash
curl -X GET https://panel.example.com/api/websites-statistics \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Performance Monitoring

### Automated Health Checks

The system can perform automated health checks on websites:

```php
use App\Services\WebsiteService;

$websiteService = app(WebsiteService::class);
$result = $websiteService->checkWebsiteHealth($website);

// Returns:
// [
//   'success' => true,
//   'uptime_status' => true,
//   'status_code' => 200,
//   'response_time_ms' => 245
// ]
```

### Recording Custom Metrics

```php
use App\Services\WebsiteService;

$websiteService = app(WebsiteService::class);
$websiteService->recordPerformanceMetric($website, [
    'response_time_ms' => 250,
    'status_code' => 200,
    'uptime_status' => true,
    'cpu_usage' => 45.5,
    'memory_usage' => 62.3,
    'bandwidth_used' => 1024000,
    'visitors_count' => 150,
]);
```

## Security

- All API endpoints require authentication via Laravel Sanctum
- Users can only access their own websites
- Server-side validation on all inputs
- Rate limiting applied to API endpoints
- SSL/TLS support with automatic certificate management

## Best Practices

1. **Regular Monitoring**: Enable automated health checks to track uptime
2. **SSL Certificates**: Use auto-SSL (Let's Encrypt) for automatic certificate renewal
3. **Resource Allocation**: Monitor disk usage and bandwidth to prevent overages
4. **Performance Optimization**: Track response times and optimize slow sites
5. **Status Updates**: Keep website status current (active, maintenance, etc.)

## Troubleshooting

### Website Shows as Down

1. Check the performance metrics for recent status codes
2. Verify the domain is correctly configured
3. Ensure the server is online and accessible
4. Check SSL certificate validity if enabled

### High Response Times

1. Review recent performance metrics
2. Check server resource utilization
3. Consider upgrading server or optimizing the website
4. Review database query performance

### SSL Issues

1. Verify auto_ssl is enabled
2. Check domain DNS is correctly pointed
3. Ensure ports 80 and 443 are accessible
4. Review certificate expiration dates

## Future Enhancements

Planned features for future releases:
- Automated backups for websites
- Real-time alerting for downtime
- Integration with CDN services
- Advanced analytics and reporting
- Multi-region deployment support
- A/B testing capabilities
