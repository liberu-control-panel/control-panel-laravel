# Liberu — Dockerised Webhosting Control Panel

![](https://img.shields.io/badge/PHP-8.4-informational?style=flat&logo=php&color=4f5b93) ![](https://img.shields.io/badge/Laravel-12-informational?style=flat&logo=laravel&color=ef3b2d) ![](https://img.shields.io/badge/Filament-4.0-informational?style=flat&logo=data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0OCIgaGVpZ2h0PSI0OCIgeG1sbnM6dj0iaHR0cHM6Ly92ZWN0YS5pby9uYW5vIj48cGF0aCBkPSJNMCAwaDQ4djQ4SDBWMHoiIGZpbGw9IiNmNGIyNWUiLz48cGF0aCBkPSJNMjggN2wtMSA2LTMuNDM3LjgxM0wyMCAxNWwtMSAzaDZ2NWgtN2wtMyAxOEg4Yy41MTUtNS44NTMgMS40NTQtMTEuMzMgMy0xN0g4di01bDUtMSAuMjUtMy4yNUMxNCAxMSAxNCAxMSAxNS40MzggOC41NjMgMTkuNDI5IDYuMTI4IDIzLjQ0MiA2LjY4NyAyOCA3eiIgZmlsbD0iIzI4MjQxZSIvPjxwYXRoIGQ9Ik0zMCAxOGg0YzIuMjMzIDUuMzM0IDIuMjMzIDUuMzM0IDEuMTI1IDguNUwzNCAyOWMtLjE2OCAzLjIwOS0uMTY4IDMuMjA5IDAgNmwtMiAxIDEgM2gtNXYyaC0yYy44NzUtNy42MjUuODc1LTcuNjI1IDItMTFoMnYtMmgtMnYtMmwyLTF2LTQtM3oiIGZpbGw9IiMyYTIwMTIiLz48cGF0aCBkPSJNMzUuNTYzIDYuODEzQzM4IDcgMzggNyAzOSA4Yy4xODggMi40MzguMTg4IDIuNDM4IDAgNWwtMiAyYy0yLjYyNS0uMzc1LTIuNjI1LS4zNzUtNS0xLS42MjUtMi4zNzUtLjYyNS0yLjM3NS0xLTUgMi0yIDItMiA0LjU2My0yLjE4N3oiIGZpbGw9IiM0MDM5MzEiLz48cGF0aCBkPSJNMzAgMThoNGMyLjA1NSA1LjMxOSAyLjA1NSA1LjMxOSAxLjgxMyA4LjMxM0wzNSAyOGwtMyAxdi0ybC00IDF2LTJsMi0xdi00LTN6IiBmaWxsPSIjMzEyODFlIi8+PHBhdGggZD0iTTI5IDI3aDN2MmgydjJoLTJ2MmwtNC0xdi0yaDJsLTEtM3oiIGZpbGw9IiMxNTEzMTAiLz48cGF0aCBkPSJNMzAgMThoNHYzaC0ydjJsLTMgMSAxLTZ6IiBmaWxsPSIjNjA0YjMyIi8+PC9zdmc+&&color=fdae4b&link=https://filamentphp.com) ![Jetstream](https://img.shields.io/badge/Jetstream-5-purple.svg) ![Socialite](https://img.shields.io/badge/Socialite-latest-brightgreen.svg) ![](https://img.shields.io/badge/Livewire-3.5-informational?style=flat&logo=Livewire&color=fb70a9) ![](https://img.shields.io/badge/JavaScript-ECMA2020-informational?style=flat&logo=JavaScript&color=F7DF1E) [![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

[![Install](https://github.com/liberu-control-panel/control-panel-laravel/actions/workflows/install.yml/badge.svg)](https://github.com/liberu-control-panel/control-panel-laravel/actions/workflows/install.yml) [![Tests](https://github.com/liberu-control-panel/control-panel-laravel/actions/workflows/tests.yml/badge.svg)](https://github.com/liberu-control-panel/control-panel-laravel/actions/workflows/tests.yml) [![Docker](https://github.com/liberu-control-panel/control-panel-laravel/actions/workflows/main.yml/badge.svg)](https://github.com/liberu-control-panel/control-panel-laravel/actions/workflows/main.yml) [![Codecov](https://codecov.io/gh/liberu-control-panel/control-panel-laravel/branch/main/graph/badge.svg)](https://codecov.io/gh/liberu-control-panel/control-panel-laravel)


A modular, Docker-first Laravel control panel for managing web hosting: virtual hosts (NGINX), BIND DNS zones, Postfix/Dovecot mail, MySQL databases, and Docker Compose service orchestration. Designed for sysadmins and self-hosting teams who want a single web interface to manage hosting infrastructure.

Key features

- User and team management with Jetstream and role-based policies
- Manage NGINX virtual hosts with automated Let's Encrypt support
- BIND DNS zone and record management (A, AAAA, CNAME, MX, TXT, ...)
- Mail domain and mailbox management (Postfix + Dovecot)
- MySQL database + user lifecycle and backup/restore helpers
- Docker Compose orchestration: deploy, view logs, and manage services

Quickstart (Docker)

1. Clone the repository and switch to the project directory:

```
git clone https://github.com/liberu-control-panel/control-panel-laravel.git
cd control-panel-laravel
```

2. Copy the example environment and adjust the values you need:

```
cp .env.example .env
# Edit .env: set CONTROL_PANEL_DOMAIN, LETSENCRYPT_EMAIL, DB credentials, etc.
```

3. Start the services (build on first run):

```
docker compose up -d --build
```

4. (Optional) Run database migrations and seeders inside the main app container:

```
docker compose exec control-panel php artisan migrate --force
docker compose exec control-panel php artisan db:seed --class=DatabaseSeeder
```

5. Open your browser at http://localhost (or the domain set in `CONTROL_PANEL_DOMAIN`).

Notes

- The `setup.sh` script in the repo automates build + migrations + seeding for supported environments.
- For development using Laravel Sail follow Sail's instructions (see repository docs).

Related projects

| Project | Repository |
|---|---|
| Liberu Accounting | https://github.com/liberu-accounting/accounting-laravel |
| Liberu Automation | https://github.com/liberu-automation/automation-laravel |
| Liberu Billing | https://github.com/liberu-billing/billing-laravel |
| Liberu Boilerplate | https://github.com/liberusoftware/boilerplate |
| Liberu CMS | https://github.com/liberu-cms/cms-laravel |
| Liberu CRM | https://github.com/liberu-crm/crm-laravel |
| Liberu E‑commerce | https://github.com/liberu-ecommerce/ecommerce-laravel |
| Liberu Social Network | https://github.com/liberu-social-network/social-network-laravel |

Contributing

Contributions are welcome. Please open issues for bugs or feature requests, and submit pull requests from a feature branch. Ensure CI passes and include tests for new behavior where appropriate. For larger changes, open a short proposal issue first.

License

This project is licensed under the MIT License — see the LICENSE file for details.

Where to get help

- Use GitHub Issues for bugs and feature requests.
- For direct support or urgent questions, contact the maintainers via the project site: https://liberu.co.uk

Acknowledgements

Thanks to contributors and the open-source community. See the contributors graph below.

<a href="https://github.com/liberu-control-panel/control-panel-laravel/graphs/contributors"><img src="https://contrib.rocks/image?repo=liberu-control-panel/control-panel-laravel" alt="Contributors"/></a>
