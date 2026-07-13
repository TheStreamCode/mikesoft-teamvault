# TeamVault Cloud Product and Architecture Design

**Status:** Approved direction, pre-implementation
**Date:** 2026-07-11
**Scope:** Commercial validation, public Core extension points, private Cloud service, and launch operations

## 1. Product Decision

TeamVault Cloud is an optional managed storage service for Mikesoft TeamVault. Its primary job is to solve insufficient disk space on WordPress hosting without forcing customers to migrate their websites or expose private documents through the Media Library.

The product promise is:

> Free space on your WordPress hosting. Store TeamVault documents in private EU cloud storage and access the same vault from multiple WordPress installations.

Cloud storage is the paid value. Multi-site persistence, centralized permissions, version recovery, and managed backups strengthen that value but are not the lead marketing message.

The local WordPress.org plugin remains complete and useful without a subscription. TeamVault Cloud does not remove, time-limit, or lock any existing local feature.

## 2. Commercial Model

TeamVault Cloud is sold as fixed-capacity storage with predictable pricing. Every plan includes the same Cloud capabilities; plans differ only by active storage and connected site limits.

| Plan | Monthly | Annual | Active storage | Connected sites |
| --- | ---: | ---: | ---: | ---: |
| Cloud 25 | EUR 12.90 | EUR 129 | 25 GB | 2 |
| Cloud 100 | EUR 24.90 | EUR 249 | 100 GB | 5 |
| Cloud 250 | EUR 49.90 | EUR 499 | 250 GB | 10 |

Prices are presented before applicable indirect tax. Users and groups are not metered. Downloads do not incur customer-facing overage charges.

At 80% usage, TeamVault shows a warning. At 100%, TeamVault blocks new uploads but keeps browsing, preview, download, deletion, export, billing management, and account cancellation available. There are no automatic overage charges.

The service targets businesses and professional teams. Consumer sales are not a launch requirement.

### 2.1 Payment provider

Stripe Managed Payments is the preferred payment path because Stripe acts as Merchant of Record for supported transactions and handles indirect tax, fraud, disputes, and transaction-level payment support. Subscriptions use Stripe Billing and hosted Checkout.

Launch is conditional on Stripe approving TeamVault Cloud and the relevant account for Managed Payments. If it is not eligible, the fallback is another Merchant of Record such as Paddle. Standard Stripe Payments is not the launch fallback because it would materially increase cross-border indirect-tax operations.

Stripe webhooks are the source of truth for subscription state. A successful browser redirect never activates storage by itself.

### 2.2 Economic guardrails

Infrastructure pricing is modeled conservatively at 2.25 times sold active capacity:

- 1.00x active objects;
- 0.25x retained versions and trash;
- 1.00x isolated backup copy.

Cloudflare R2 Standard currently charges USD 0.015 per GB-month, charges separately for operations, and does not charge internet egress. The service must preserve a contribution margin of at least 75% per plan after storage, normal object operations, and payment-provider transaction fees, before fixed business costs and taxes.

The initial commercial goal is EUR 900-1,000 MRR, not EUR 500 MRR. This provides room for infrastructure, payment fees, refunds, professional costs, taxes, and development tools.

## 3. Repository and Domain Boundaries

No new domain purchase is required.

### 3.1 Mikesoft website

Repository:

`C:\Users\Mike\Desktop\Workspace\mikesoft`

Responsibilities:

- localized Italian and English product landing pages;
- pricing and storage comparison;
- FAQ and documentation entry points;
- consented early-access form;
- privacy policy, service terms, and DPA;
- Checkout success and cancellation pages;
- search and conversion content.

Canonical marketing routes:

- `https://mikesoft.it/it/teamvault-cloud/`
- `https://mikesoft.it/en/teamvault-cloud/`

The existing site is an Astro application deployed on Vercel. It remains a marketing surface and must not host Cloud object access, subscription webhooks, vault metadata, or R2 credentials.

### 3.2 Public TeamVault Core

Repository:

`C:\Users\Mike\Desktop\Workspace\.github-projects\mikesoft-teamvault\mikesoft-teamvault-src`

Responsibilities:

- all existing local storage capabilities;
- public contracts and factories for alternate vault backends;
- local backend as the default implementation;
- a Cloud status entry point inside TeamVault screens;
- explicit administrator consent before any external request;
- external-service disclosure and privacy links;
- stable hooks consumed by the private Cloud connector.

The public plugin must contain no dormant paid implementation, no R2 or Stripe secret, no executable code downloader, and no mandatory account requirement.

### 3.3 Private TeamVault Cloud monorepo

Planned private repository:

`teamvault-cloud`

Recommended layout:

```text
teamvault-cloud/
├── plugin/       # Commercial WordPress connector
├── worker/       # Cloud API and Stripe webhooks
├── migrations/   # Cloud metadata schema
├── shared/       # Versioned API contracts
├── docs/         # Threat model, operations, privacy data map, runbooks
└── tests/        # API, tenant isolation, billing, sync, and connector tests
```

The repository is private because it contains the commercial connector and service implementation. It does not require a separate domain.

Development and private beta may use the default `workers.dev` hostname. Production may later use a free subdomain of the existing domain, such as `api-teamvault.mikesoft.it`. A custom Stripe Checkout domain is not required.

## 4. Cloud 1.0 Functional Scope

Cloud 1.0 must include:

1. migration of existing local TeamVault files and metadata;
2. private EU-jurisdiction object storage;
3. multiple connected and individually revocable WordPress installations;
4. concurrent read and write access from every connected installation;
5. centralized files, folders, identities, groups, permissions, and audit metadata;
6. global user identity mapped from normalized WordPress account email;
7. optimistic concurrency control for metadata mutations;
8. immutable file versions rather than silent binary overwrite;
9. trash and recovery windows;
10. active-storage quota enforcement;
11. subscription activation, renewal, plan change, past-due, cancellation, and retention states;
12. full customer export before final deletion;
13. cloud usage and connected-site management inside TeamVault;
14. security, privacy, retention, and incident-response documentation.

Cloud 1.0 explicitly excludes:

- a public or customer-facing document portal;
- public share links;
- electronic signatures;
- AI document processing;
- customer-supplied S3 credentials;
- storage-based usage billing or surprise overages;
- custom checkout and card-management forms;
- custom invoicing UI;
- arbitrary third-party integrations;
- cloud ZIP generation.

Individual downloads and previews are supported. Full-account export is supported as an asynchronous operational export. Existing interactive folder ZIP export remains available only for the local backend until a safe Cloud implementation is justified.

## 5. Public Core Refactor

The current Core directly constructs `MSTV_Storage`, filesystem services, and concrete WordPress database repositories in `MSTV_Bootstrap`. Several services type-hint concrete repository classes, and local integer IDs are embedded across controllers and permission logic.

Cloud support must begin with a behavior-preserving refactor, not with R2 calls inside `MSTV_Storage`.

### 5.1 Required extension boundary

The Core needs a vault backend abstraction that covers:

- file metadata queries and mutations;
- folder queries and mutations;
- binary upload, download, preview, move, and delete;
- groups and memberships;
- folder permission policies;
- storage usage and quotas;
- activity records;
- backend health and capability reporting.

The local implementation continues to wrap the existing repositories and filesystem. A backend factory selects the local implementation by default and allows an installed commercial connector to register a Cloud implementation through documented WordPress hooks.

The public REST controllers depend on contracts rather than Cloud-specific classes. Local behavior, route shapes, permissions, and package contents remain backward compatible.

### 5.2 Identifier strategy

Cloud records use tenant-scoped integer identifiers where practical so the existing Core does not require a broad conversion from integers to UUID strings. Every Cloud query still includes and enforces the organization and vault boundary. Object storage keys remain opaque and are never treated as authorization credentials.

### 5.3 Compatibility requirement

With no commercial connector installed or no Cloud subscription active:

- no external request occurs;
- local upload, preview, download, move, rename, delete, ZIP export, quotas, reports, permissions, notifications, and maintenance behavior remain unchanged;
- all existing PHPUnit tests remain green;
- new contract tests run against the local backend.

## 6. Cloud Architecture

### 6.1 Platform

- Cloudflare Workers for the API and webhook handlers;
- Cloudflare R2 Standard for file objects;
- R2 jurisdiction `eu`, not a best-effort location hint;
- Cloudflare D1 for transactional metadata at launch;
- Stripe Managed Payments, Stripe Billing, hosted Checkout, and hosted Customer Portal;
- the existing Mikesoft Astro site for marketing and legal pages.

R2 buckets are private. The service does not expose `r2.dev` public access.

### 6.2 Tenant model

The hierarchy is:

```text
customer account
└── organization
    └── vault
        ├── connected sites
        ├── global identities
        ├── groups and permissions
        ├── folders and files
        ├── versions
        └── audit events
```

Every metadata query and mutation is scoped by organization and vault. Tenant-isolation tests are release blockers.

### 6.3 Site authentication

Each WordPress installation receives a unique revocable credential after a one-time pairing flow. Credentials are stored hashed by the service and protected in WordPress configuration. They are never placed in URLs, logs, analytics, or browser-visible HTML.

Site credentials can be rotated and revoked without canceling the organization subscription. Revocation immediately blocks new API operations from that installation.

A connected WordPress site is part of the service trust boundary. If a connected site is compromised, its credential and asserted logged-in users may be abused until the site is revoked. The product documentation must state this operational assumption and provide fast revocation controls.

### 6.4 Global identity

A global TeamVault identity is keyed by a normalized, verified WordPress account email. Connected sites map their current authenticated user to that identity. Different WordPress numeric user IDs with the same verified email resolve to one global identity.

Email changes do not silently merge, split, or transfer permissions. They require an explicit verified transition. Deleting a WordPress user from one site does not automatically delete the global identity if it remains connected elsewhere.

### 6.5 Authorization

The Cloud API, not only the WordPress plugin, evaluates global folder and action permissions. The plugin sends a short-lived signed user assertion produced by the connected site. The API validates the site, identity, vault, requested resource, action, and subscription state before issuing upload or download capability.

Administrators retain recovery access subject to documented organization ownership rules. Permission-denied events are audited without logging file contents or credentials.

## 7. Data and Synchronization

### 7.1 Cloud source of truth

When Cloud is active, Cloud metadata and R2 objects are the source of truth. WordPress may cache listings and state for performance but cannot authoritatively mutate Cloud data offline.

Each vault maintains a monotonically advancing change cursor. Connected sites request changes after their last cursor and invalidate local caches. Real-time sockets are not required for Cloud 1.0.

### 7.2 Optimistic concurrency

Mutable metadata records carry a revision. Mutation requests include the expected revision.

- Matching revision: the mutation commits and increments the revision.
- Stale revision: the API returns a conflict and the plugin refreshes the resource.
- New file content: a new immutable version is created.
- Concurrent rename or move: no silent last-write-wins behavior.

### 7.3 Upload

1. WordPress validates capability, nonce, file type, declared size, and Cloud state.
2. The Cloud API validates subscription, identity, permission, quota, and object policy.
3. The API creates a short-lived staged upload capability.
4. The browser or connector uploads without persisting the final binary on WordPress hosting.
5. The client commits the upload with size, checksum, and expected folder revision.
6. The API verifies staged-object metadata and atomically creates the file version and audit event.
7. Failed or abandoned staged objects expire automatically.

The active usage counter changes only after a successful commit.

### 7.4 Download and preview

1. The plugin identifies the logged-in WordPress user.
2. The Cloud API validates the connected site and global permission.
3. The API returns a short-lived, resource-specific access URL.
4. The browser downloads or previews directly from private Cloud storage.

URLs expire quickly and cannot be reused to list or access adjacent objects.

### 7.5 Delete, trash, and versions

Deleting a file moves the logical record to trash and retains existing object versions for the configured recovery period. Deleting a folder requires an explicit recursive operation or an empty folder. Final purge is asynchronous, audited, and reduces billable storage only after objects are removed.

## 8. Billing and Account Lifecycle

### 8.1 Activation

1. An administrator selects a plan inside TeamVault.
2. The plugin requests a one-time activation state from the Cloud API.
3. The API creates a hosted Stripe Checkout Session.
4. The administrator completes Checkout in the browser.
5. Stripe sends a signed webhook.
6. The webhook handler verifies the signature and processes the event idempotently.
7. The service activates the organization and pairing state.
8. The plugin polls the one-time state and stores its site credential.

The success redirect is informational only.

### 8.2 Subscription states

- `pending`: no Cloud writes; activation may resume.
- `active`: normal operation within quota.
- `past_due`: downloads continue; uploads stop after the documented grace period.
- `canceled`: uploads stop; downloads and export remain available during retention.
- `retention`: read/export only until the deletion deadline.
- `deleted`: customer metadata and objects are purged according to policy.

Plan upgrades apply the new quota after webhook confirmation. Downgrades below current usage do not delete files; they block new uploads until usage is below the new quota or the plan is upgraded.

### 8.3 Customer management

Stripe Customer Portal manages payment methods, invoices, plan changes, and cancellation. TeamVault manages storage usage, sites, identities, permissions, migration, export, and service deletion.

## 9. Backup and Recovery

Cloud primary storage is not described as a backup by itself. TeamVault Cloud maintains:

- immutable application-level file versions;
- a separate backup object set with isolated credentials;
- metadata backups independent of the live metadata database;
- tested restore procedures;
- a customer export path that does not depend on a working WordPress installation.

The launch documentation must state retention periods and recovery objectives without making unsupported guarantees. Restore drills are required before paid launch and after material storage or schema changes.

## 10. Security and Privacy Requirements

Paid launch requires:

- private R2 buckets in the EU jurisdiction;
- TLS for all network traffic;
- standard provider encryption at rest;
- no claim of end-to-end or zero-knowledge encryption;
- least-privilege Worker, R2, D1, Stripe, and deployment credentials;
- per-site credential rotation and revocation;
- webhook signature verification and idempotency;
- strict tenant scoping in every query;
- short-lived upload and download capabilities;
- request size, type, rate, and quota limits;
- sanitised logs with no document contents, secrets, or signed URLs;
- audit events for access and administrative changes;
- dependency and secret scanning;
- backup and restore verification;
- data export, cancellation, retention, and deletion procedures;
- privacy data map, subprocessors list, privacy policy, terms, and DPA;
- incident triage and customer-notification runbook.

Security-sensitive changes require focused tests and independent review before release when available. No production secret is stored in either public repository or the Mikesoft website repository.

## 11. WordPress.org Compliance

The public plugin follows these constraints:

- local mode remains fully functional;
- no external call occurs before explicit administrator consent;
- no telemetry is enabled by default;
- promotion is limited to relevant TeamVault screens and is dismissible where appropriate;
- Cloud is documented as an optional external service;
- the readme identifies data sent to the service, purpose, privacy policy, terms, and subprocessors;
- paid executable code is not included or locked inside the WordPress.org package;
- the public plugin does not download or install the commercial connector;
- account and subscription state do not affect local mode.

The commercial connector is installed separately from a customer-authorized download outside WordPress.org and integrates into the same TeamVault navigation and visual language.

## 12. Validation Before Infrastructure Build

The first deliverable is demand validation, not the Cloud backend.

### 12.1 Mikesoft landing page

The localized landing page includes:

- the hosting-space problem;
- the 25/100/250 GB plans and real prices;
- EU storage and no-download-overage message;
- multi-site value;
- a product-flow illustration or mockup;
- clear separation between free local TeamVault and optional Cloud;
- an early-access form with explicit consent;
- no claim that the service is already available.

### 12.2 In-plugin discovery

A future public Core release may add a static, dismissible Cloud introduction inside TeamVault settings or storage UI. It makes no tracking request. Clicking it opens the Mikesoft landing page.

The message should be contextual to TeamVault storage usage and should never appear as a dashboard-wide advertisement.

### 12.3 Validation gate

Backend implementation proceeds only after the public offer has produced:

- at least 30 qualified early-access contacts;
- at least 10 respondents who explicitly request a paid beta at the displayed price range;
- recurring evidence that hosting storage is the problem they want solved.

If the gate fails, revise positioning, plans, or target customer before building Cloud infrastructure.

## 13. Delivery Sequence

### Phase 1: Validation surface

- Mikesoft localized landing page;
- pricing and FAQ;
- privacy-aware early-access collection;
- conversion and consent tests;
- no paid infrastructure dependency.

### Phase 2: Public Core extension boundary

- backend contracts and factory;
- local adapter over existing storage and repositories;
- behavior-preserving controller migration;
- local contract tests;
- Cloud entry point and external-service disclosure only after the private connector path exists.

### Phase 3: Private Cloud foundation

- private monorepo;
- Worker API;
- EU R2 buckets;
- D1 schema and migrations;
- organization, vault, site, identity, group, permission, object, version, audit, usage, and billing models;
- tenant-isolation test harness.

### Phase 4: Storage and multi-site

- resumable migration;
- staged direct uploads;
- downloads and previews;
- change cursor and cache invalidation;
- optimistic concurrency and immutable versions;
- connected-site rotation and revocation.

### Phase 5: Billing and operations

- Stripe test-mode Checkout and Managed Payments configuration;
- webhook processing;
- subscription lifecycle and quota enforcement;
- Customer Portal;
- export, retention, deletion, monitoring, and support runbooks.

### Phase 6: Paid beta and launch

- ten-company private beta;
- destructive restore drills;
- security and privacy review;
- migration failure and rollback exercises;
- production pricing and terms;
- gradual public availability.

## 14. Verification Strategy

### Public Core

- existing `composer ci` remains green;
- contract tests exercise the local backend;
- no external requests without activation;
- package inspection excludes repository-only and commercial files;
- WordPress Plugin Check remains green.

### Commercial connector

- connector refuses operation without compatible Core and API versions;
- site credentials are stored, rotated, and deleted safely;
- user assertions cannot cross site, organization, or vault boundaries;
- failed Cloud operations do not create phantom local state;
- migration is resumable and idempotent.

### Cloud API

- tenant isolation for every resource type;
- authorization tests for every action;
- quota races cannot exceed the plan silently;
- stale revisions produce conflicts;
- webhook replay is idempotent;
- canceled and past-due states enforce documented behavior;
- signed capabilities expire and are resource-scoped;
- export and deletion complete across metadata, active objects, versions, trash, and backups.

### Mikesoft website

- Italian and English routes, metadata, canonicals, sitemap, and copy stay aligned;
- early-access submission requires explicit consent;
- no Cloud secret is exposed to the browser or website repository;
- `npm test`, `npm run check`, and `npm run build` remain green.

## 15. Launch Criteria

TeamVault Cloud does not accept paid production customers until all of the following are true:

- the validation gate has passed;
- Stripe Managed Payments eligibility or the Merchant-of-Record fallback is confirmed;
- Core local mode has no regression;
- multi-site write conflicts are handled without silent data loss;
- tenant-isolation tests pass;
- backup and restore drills pass;
- quota and lifecycle behavior pass;
- privacy policy, terms, DPA, retention, and subprocessor disclosures are published;
- incident and deletion runbooks have been exercised;
- the customer can export data without relying on the original WordPress site.

## 16. Final Constraints

- No new domain purchase before proven sales.
- No Cloud implementation inside the Mikesoft marketing repository.
- No paid executable code in the WordPress.org package.
- No custom billing UI when Stripe-hosted surfaces are sufficient.
- No Cloud feature beyond storage, multi-site persistence, global authorization, recovery, and operations before paid validation.
- No claim of tax, legal, GDPR, backup, availability, or security compliance beyond what has been verified and documented.
