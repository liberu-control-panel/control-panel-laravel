# Hosting control panel: workflow and parity roadmap

## Scope and evidence

This is a delivery plan, not a claim that the application already matches or exceeds every competing panel. Installed models, API endpoints and resource screens establish implementation surfaces; they do not prove that remote provisioning, recovery or failover works.

The reference baseline was reviewed on 2026-09-06:

- [cPanel/WHM features](https://www.cpanel.net/products/cpanel-whm-features/) establish customer hosting tools, provider account/package management, backups and databases as baseline workflows.
- [Plesk features](https://www.plesk.com/features/) and [WP Toolkit](https://www.plesk.com/wp-toolkit/) emphasize a consolidated site dashboard, staging, cloning, updates, monitoring and recovery.
- [DirectAdmin backup, restore and migration](https://docs.directadmin.com/directadmin/backup-restore-migration/index.html) covers account archives containing websites, databases and email, with separate user, reseller and administrator interfaces.
- [Virtualmin navigation](https://www.virtualmin.com/docs/getting-started/how-to-navigate-in-virtualmin/) distinguishes domain-owner and administrator workflows and documents migration tools.
- [KubePanel architecture](https://kubepanel.io/documentation/) and [workloads](https://kubepanel.io/features/multi-workload/) provide the reference for per-site container isolation and Kubernetes-backed hosting. Its workload page labels Node.js as coming soon; advertised runtime flexibility is not evidence that every workload is production-ready.

## Delivered increment: Websites overview

`Web Hosting → Websites` is registered in both panels. It appears only when the web-hosting provider is enabled and the actor has an active, accessible team and domain-list permission. The app panel uses the current team; the admin panel uses its selected tenant.

- Search hostnames, filter lifecycle status, paginate and sort.
- Review active virtual-host configuration counts, issued-certificate expiry and failed deployment counts together.
- Filter sites needing attention: non-active lifecycle, no active host configuration, failed deployment, or no issued certificate valid beyond 30 days.
- Ignore revoked certificates and cross-team certificate/deployment records when computing these indicators.
- Show management links only when the resource is registered in the current panel, its policy allows the operation and its current-team scoping agrees with the selected team.
- Use database aggregates instead of loading secrets or querying relationships once per website.

These are configuration indicators, not uptime probes. The overview does not assert that a certificate is installed, that renewal works, or that a backup is recoverable. Customer accounts without the management resource get a read-only overview; adding a domain still requires their team administrator. No module is forcibly enabled by this change.

## Prioritized delivery backlog

Onboarding hardening now binds the wizard to its originating team, rechecks owner access, derives step eligibility from persisted progress, and uses a locked revision plus a database transaction to reject stale-tab updates. Optional OAuth pairs are validated and secrets are not loaded back into inputs. The setup UI uses Filament-native controls and labels stored credentials as unverified storage. Runtime consumers for those team credentials and real connection verification remain outstanding.

Source inspection also confirms that `CreateDomain`, `CreateVirtualHost`, and `ActivateDomain` currently write records; those actions alone do not configure a remote web server. A launch wizard must integrate a real execution/reconciliation adapter and verify the result rather than call these three actions and declare the site deployed.

| Priority | Workflow | Existing surface to build on | Acceptance gate |
| --- | --- | --- | --- |
| P0 | Secure onboarding and connection readiness | Account setup, scoped settings, connected accounts, node credentials | Team owners authorize changes; stored credentials are distinguished from tested connections; reconnect, expiry and revoked-access paths are tested; no secret appears in client state or logs. |
| P0 | Launch a website | Accounts, domains, virtual hosts, runtime, DNS and certificate modules | One resumable workflow produces a functioning test site; prerequisites are checked before mutation; retries are idempotent; failures identify the failed stage and safe recovery action. |
| P0 | Backup and verified restore | Backup destinations, schedules, executions and restores | A disposable-site restore verifies file checksums and database content; destructive replacement requires explicit confirmation; retention and remote-storage failures are visible. |
| P1 | Website workspace | Websites overview, hosting resources, file/database/mail/DNS modules | Site-scoped tools preserve team context and role permissions; non-admin customers can complete allowed tasks without raw identifiers; keyboard, mobile and dark-mode browser tests pass. |
| P1 | WordPress lifecycle | Hosted applications, update checks, Git deployment | Install, clone to staging, back up, update and roll back on an isolated test host; failed updates cannot silently replace a working site. |
| P1 | Migration assistant | Account, file, mail, database and DNS modules | Import representative cPanel, Plesk, DirectAdmin and Virtualmin archives using dry-run mapping, quota checks, safe archive extraction, progress and post-import reconciliation. Do not change public DNS without confirmation. |
| P1 | Mail deliverability and DNS diagnostics | Mail diagnostics, DKIM, zones, DNSSEC and propagation | Show authoritative DNS results, SPF/DKIM/DMARC problems, mail delivery traces and timestamped remediation guidance; enforce tenant scope and outbound request restrictions. |
| P1 | Reseller and delegated hosting | Hosting packages, account delegation and teams | Enforce quotas and delegated permissions on both API and UI operations; test suspension, renewal and billing retries with provider sandboxes. |
| P2 | Container and Kubernetes operations | Workloads, networks, secrets, clusters, ingress and autoscaling | Prove resource isolation, network policy, deployment readiness, rollback and node-failure recovery on disposable infrastructure. |
| P2 | Fleet health and remediation | Monitoring, incidents, OS adapters and automation | Correlate timestamped observed health with configuration; proposed remediation includes scope and risk; retries and audit trails are tested. |

## Release standard

Each increment needs policy and cross-team tests, bounded queries, reproducible locked dependency installation, formatting/static analysis, and the full test suite. Infrastructure features additionally require disposable-host or cluster integration tests; record-only tests are insufficient. Browser validation must cover the assembled panels before describing the UX as fully verified. Publish a major release only after upgrade PRs and the integrated main branch pass their required checks.
