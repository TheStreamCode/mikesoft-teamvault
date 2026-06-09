# TeamVault Cloud MVP Plan

## Summary

Create an optional cloud storage service sold directly from the TeamVault plugin, with Stripe subscriptions and no ecommerce layer.

The free WordPress.org plugin must remain fully usable with local storage. TeamVault Cloud is an external optional service that stores file binaries in managed cloud storage while keeping folders, permissions, metadata, and audit logs in the customer's WordPress site.

Recommended architecture:

- WordPress plugin connector
- TeamVault Cloud web app and API
- Cloudflare R2 for object storage
- Cloudflare D1 for cloud metadata
- Stripe Checkout and Stripe Customer Portal for billing

Primary public domain:

- `teamvault.mikesoft.it`

`teamvault.mikesoft.it` should be the main customer-facing domain because it reinforces the product name and can host the account dashboard, API, legal pages, and future product pages. `cloud.mikesoft.it` can be kept as a technical alias or redirect, but it should not be the primary brand unless the service later expands beyond TeamVault.

## Product Model

TeamVault Cloud should be presented as managed private storage for TeamVault, not as a generic Pro unlock.

Suggested MVP plans:

| Plan | Storage | Price |
| --- | ---: | ---: |
| Starter | 10 GB | 5 euro/month or 49 euro/year |
| Team | 50 GB | 9 euro/month or 89 euro/year |
| Business | 200 GB | 19 euro/month or 199 euro/year |
| Agency | 1 TB | 49 euro/month or 499 euro/year |

## WordPress Plugin Changes

Add a TeamVault Cloud panel in the plugin admin, either as a dedicated `TeamVault > Cloud` page or as a section in the existing settings page.

The panel should show:

- cloud connection status
- active plan
- subscription state
- used storage
- plan quota
- "Activate cloud" button
- "Manage subscription" button
- "Disconnect cloud" button

The plugin should keep local storage as the default and fallback storage mode.

When cloud is active:

- uploads go to TeamVault Cloud
- file metadata remains in WordPress
- folder records remain in WordPress
- permissions remain controlled by WordPress capabilities and whitelist rules
- audit logs remain in WordPress
- download and preview URLs are generated only after WordPress permission checks

In the first version, cloud ZIP export should be out of scope or disabled with a clear admin message. This avoids expensive and complex server-side ZIP generation before validating paid demand.

## TeamVault Cloud API

Build a separate minimal service named `teamvault-cloud`, hosted under `teamvault.mikesoft.it`.

The service should include both:

- a private API used by the plugin
- a minimal customer dashboard for account and subscription management

Recommended stack:

- Cloudflare Worker
- Cloudflare R2 bucket
- Cloudflare D1 database
- Stripe Billing

Recommended routes:

- `https://teamvault.mikesoft.it/account`
- `https://teamvault.mikesoft.it/legal/privacy`
- `https://teamvault.mikesoft.it/legal/terms`
- `https://teamvault.mikesoft.it/api/...`

Optional aliases:

- `https://cloud.mikesoft.it` redirects to `https://teamvault.mikesoft.it`
- `https://api.teamvault.mikesoft.it` can be added later if the API grows

Required endpoints:

- `POST /checkout/session`
- `POST /billing/portal`
- `GET /site/status`
- `POST /objects/upload`
- `GET /objects/download-url`
- `DELETE /objects/{id}`
- `POST /stripe/webhook`

The API stores:

- `site_id`
- hashed site token
- WordPress site URL
- Stripe customer ID
- Stripe subscription ID
- plan key
- subscription status
- storage quota in bytes
- used storage in bytes
- R2 object keys
- object sizes
- timestamps

## Account And Registration Model

Avoid a traditional registration form in the MVP.

The customer account should be created automatically from Stripe Checkout using the billing email. This keeps the buying flow short and avoids asking users to create a password before they have paid.

Recommended account flow:

1. WordPress admin clicks "Activate cloud".
2. Plugin calls the TeamVault Cloud API with the site URL and a nonce-protected activation request.
3. API creates a pending site connection and Stripe Checkout Session.
4. User pays on Stripe Checkout.
5. Stripe webhook confirms payment.
6. API creates or updates the customer account using the Stripe customer email.
7. API activates the site connection and issues a site token.
8. Plugin stores the site ID and token.
9. Plugin refreshes cloud status and enables cloud storage.

Customer login should use email magic links, not passwords.

The dashboard at `teamvault.mikesoft.it/account` should initially be minimal:

- list connected sites
- show active plan
- show storage usage
- open Stripe Customer Portal
- disconnect a site
- rotate a site token

Billing management should stay in Stripe Customer Portal for the MVP. Do not build custom invoice, card, cancellation, or plan-change UI until there is a clear need.

## Billing Flow

Use Stripe Checkout Sessions in subscription mode.

1. WordPress admin clicks "Activate cloud".
2. Plugin calls TeamVault Cloud API on `teamvault.mikesoft.it` to create a Checkout Session.
3. User completes payment on Stripe Checkout.
4. Stripe webhook notifies TeamVault Cloud.
5. Cloud API provisions or activates the site.
6. Plugin polls or refreshes `GET /site/status`.
7. Cloud storage becomes available in TeamVault.

Use Stripe Customer Portal for:

- updating payment methods
- downloading invoices
- changing plans
- canceling subscriptions

Stripe webhooks are the source of truth for:

- active subscriptions
- failed payments
- canceled subscriptions
- plan changes

## Storage Flow

### Upload

1. WordPress validates nonce, capability, whitelist, file extension, MIME type, and size.
2. WordPress checks cloud status and quota.
3. Plugin sends the file to TeamVault Cloud API.
4. API stores the object in R2 under a site-scoped key.
5. API updates usage.
6. Plugin creates the local file metadata record with cloud storage reference.
7. Plugin writes the normal audit log entry.

### Download And Preview

1. User clicks download or preview in WordPress.
2. WordPress validates the current user.
3. WordPress requests a short-lived URL from TeamVault Cloud API.
4. The browser downloads or previews the file from the temporary URL.

### Delete

1. WordPress validates delete permission.
2. Plugin calls TeamVault Cloud API to remove the object.
3. API deletes from R2 and updates usage.
4. Plugin removes or updates local metadata.
5. Plugin writes the audit log entry.

## WordPress.org Compliance

The cloud service must be optional and clearly disclosed.

Rules for the plugin:

- No external calls before explicit admin consent.
- No hidden telemetry.
- No required account for local storage.
- No local features removed from the free plugin.
- No dashboard-wide nags.
- Cloud promotion should stay inside TeamVault admin screens.

Update `readme.txt` with an external services section that explains:

- TeamVault Cloud is optional.
- Stripe handles payments.
- File binaries are sent to TeamVault Cloud only when cloud storage is enabled.
- Site URL, plan status, storage usage, and file metadata needed for the service may be sent to the Cloud API.
- Privacy policy URL.
- Terms of service URL.

## Failure Modes

Handle these states explicitly:

- not connected
- checkout pending
- active
- past due
- canceled
- quota exceeded
- cloud API unavailable
- upload failed
- object missing

Recommended behavior:

- `past_due`: allow downloads, block new uploads after a grace period.
- `canceled`: allow downloads for a retention window, block new uploads.
- `quota exceeded`: block new uploads, keep existing files downloadable.
- API unavailable: show a clear temporary error and do not corrupt local metadata.

## Test Plan

Plugin without cloud:

- local upload works
- local download works
- local preview works
- local delete works
- local move works
- local ZIP export works
- no external requests are made before cloud activation

Stripe:

- Checkout Session creates a subscription in test mode
- `checkout.session.completed` activates the site
- `invoice.payment_failed` marks site as `past_due`
- subscription cancellation marks site as `canceled`
- plan change updates quota

Cloud storage:

- upload below quota succeeds
- upload above quota is rejected
- download URL is generated only for authorized users
- preview URL is generated only for authorized users
- delete removes object and updates usage
- API failure during upload does not create a broken WordPress file record

WordPress.org compliance:

- local plugin remains useful without paid service
- external service disclosure exists in `readme.txt`
- privacy and terms links are present before release

## Assumptions

- "Everything from the plugin" means the admin starts and manages the flow from WordPress, with secure redirects to Stripe Checkout and Stripe Customer Portal.
- A separate TeamVault Cloud API is required because Stripe and R2 secrets must not be stored in customer WordPress installations.
- Cloudflare R2 is the preferred v1 storage provider because pricing is predictable and egress is not charged separately.
- The first release should validate payment and storage demand before adding advanced cloud-only features.
