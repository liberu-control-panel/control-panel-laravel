# Systemd Service Files

This directory contains systemd service files for managing Laravel background processes in standalone deployments.

## Available Services

### 1. Queue Worker (`control-panel-queue.service`)

Manages Laravel queue workers for processing background jobs.

**Installation:**
```bash
sudo cp systemd/control-panel-queue.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable control-panel-queue
sudo systemctl start control-panel-queue
```

**Management:**
```bash
# Check status
sudo systemctl status control-panel-queue

# View logs
sudo journalctl -u control-panel-queue -f

# Restart
sudo systemctl restart control-panel-queue
```

### 2. Scheduler (`control-panel-scheduler.service` + `control-panel-scheduler.timer`)

Runs Laravel's task scheduler every minute (equivalent to cron).

**Installation:**
```bash
sudo cp systemd/control-panel-scheduler.service /etc/systemd/system/
sudo cp systemd/control-panel-scheduler.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable control-panel-scheduler.timer
sudo systemctl start control-panel-scheduler.timer
```

**Management:**
```bash
# Check timer status
sudo systemctl status control-panel-scheduler.timer

# List all timers
sudo systemctl list-timers

# View execution logs
sudo journalctl -u control-panel-scheduler -f
```

### 3. Horizon Dashboard (`control-panel-horizon.service`)

Manages Laravel Horizon for advanced queue management (optional).

**Installation:**
```bash
sudo cp systemd/control-panel-horizon.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable control-panel-horizon
sudo systemctl start control-panel-horizon
```

**Management:**
```bash
# Check status
sudo systemctl status control-panel-horizon

# View logs
sudo journalctl -u control-panel-horizon -f
```

## Configuration

Before installing, review and modify the service files to match your installation:

- **User/Group**: Default is `www-data`, change if needed
- **WorkingDirectory**: Default is `/var/www/control-panel`
- **PHP Path**: Default is `/usr/bin/php`
- **Resource Limits**: Adjust memory and CPU limits as needed

## Security Features

All services include security hardening:
- `NoNewPrivileges=true` - Prevents privilege escalation
- `PrivateTmp=true` - Isolated /tmp directory
- `ProtectSystem=strict` - Read-only system directories
- `ProtectHome=true` - Inaccessible home directories
- Resource limits (memory, CPU, file descriptors)

## Monitoring

Monitor all services:
```bash
# Check all control panel services
sudo systemctl status 'control-panel-*'

# View combined logs
sudo journalctl -u 'control-panel-*' -f

# Check service resource usage
sudo systemctl status control-panel-queue --no-pager -l
```

## Troubleshooting

### Service won't start
1. Check logs: `sudo journalctl -u control-panel-queue -n 50`
2. Verify permissions on working directory
3. Ensure PHP and artisan are accessible
4. Check database connectivity

### Queue jobs not processing
1. Check queue worker is running: `systemctl status control-panel-queue`
2. Verify Redis/database connection
3. Check Laravel logs: `/var/www/control-panel/storage/logs/laravel.log`

### Scheduler not executing
1. Verify timer is active: `systemctl list-timers control-panel-scheduler.timer`
2. Check recent executions: `journalctl -u control-panel-scheduler -n 20`
3. Ensure scheduled tasks are defined in Laravel

## Uninstallation

```bash
sudo systemctl stop control-panel-queue control-panel-scheduler.timer control-panel-horizon
sudo systemctl disable control-panel-queue control-panel-scheduler.timer control-panel-horizon
sudo rm /etc/systemd/system/control-panel-*.service
sudo rm /etc/systemd/system/control-panel-*.timer
sudo systemctl daemon-reload
```
