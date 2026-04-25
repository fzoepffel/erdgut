# Email deliverability

Erdgut ships all transactional email through Resend in staging + production. This document is the single source of truth for DNS records, provider setup, and the rationale behind the List-Unsubscribe header.

## Why this exists (ERD-340)

Gmail and Yahoo enforce bulk-sender rules since Feb 2024 (SPF + DKIM + DMARC aligned, one-click List-Unsubscribe, spam rate < 0.3 %). Erdgut is below the 5 000/day bulk threshold today but the penalty for failing the rules is silent spam-foldering, which is exactly the problem we observed before this ticket.

## Provider: Resend

Resend is set as the active mailer for staging + production. SMTP fallback is kept for local development (Mailhog / log driver).

**Production config (`.env`):**
```
MAIL_MAILER=resend
RESEND_KEY=re_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
MAIL_FROM_ADDRESS="noreply@erd-gut.de"
MAIL_FROM_NAME="Erdgut"
MAIL_UNSUBSCRIBE_MAILBOX="unsubscribe@erd-gut.de"
```

The `resend/resend-laravel` package registers the `resend` transport automatically. No mail driver code change is needed — only env vars.

## DNS records

All three records must be live before switching `MAIL_MAILER=resend` in production. Resend refuses to send from unverified domains.

### 1. SPF (authorises Resend to send for erd-gut.de)

| Type | Host | Value | TTL |
| ---- | ---- | ----- | --- |
| TXT  | `@`  | `v=spf1 include:amazonses.com ~all` | 3600 |

Resend relays through Amazon SES, so we authorise SES. If another sender (e.g. Microsoft 365 for manual mail) also sends from the domain, add its `include:` **in the same record** — never publish two SPF records. Example combined:
`v=spf1 include:amazonses.com include:spf.protection.outlook.com ~all`

### 2. DKIM (provided by Resend during domain verification)

Resend generates three CNAME records when you add `erd-gut.de` in the Resend dashboard. They look like:

| Type  | Host | Value | TTL |
| ----- | ---- | ----- | --- |
| CNAME | `resend._domainkey`  | `resend._domainkey.{resend-id}.dkim.amazonses.com` | 3600 |
| CNAME | `resend2._domainkey` | `resend2._domainkey.{resend-id}.dkim.amazonses.com` | 3600 |
| CNAME | `resend3._domainkey` | `resend3._domainkey.{resend-id}.dkim.amazonses.com` | 3600 |

Copy the exact values from the Resend domain page — the `{resend-id}` differs per account. Propagation takes 5–30 min; the Resend dashboard shows the verification state.

### 3. DMARC (policy + reporting)

| Type | Host      | Value | TTL |
| ---- | --------- | ----- | --- |
| TXT  | `_dmarc`  | `v=DMARC1; p=quarantine; rua=mailto:dmarc-reports@erd-gut.de; ruf=mailto:dmarc-reports@erd-gut.de; adkim=s; aspf=s; pct=100` | 3600 |

- Start with `p=quarantine` for two weeks, then move to `p=reject` once the weekly reports show no surprise senders.
- `rua` / `ruf` aggregate and forensic report inbox — can be a shared inbox or the ops inbox. Forward the reports to `dmarcian.com` or `postmarkapp.com/dmarc-weekly` for human-readable parsing.

### 4. MX + A records (only if they don't already exist)

Skip if IONOS already serves mail for `erd-gut.de`. Check with `dig mx erd-gut.de` before publishing anything.

## List-Unsubscribe header

Every outgoing message receives a `List-Unsubscribe` header via [app/Listeners/AddListUnsubscribeHeader.php](../app/Listeners/AddListUnsubscribeHeader.php) pointing at `MAIL_UNSUBSCRIBE_MAILBOX` (default `unsubscribe@erd-gut.de`).

Format: `<mailto:unsubscribe@erd-gut.de?subject=unsubscribe>`.

Gmail and Apple Mail surface this as a "Unsubscribe" link next to the sender name. Replies land in the ops inbox — handled manually for now (volume is low). If volume grows beyond ~5 unsubscribes per week, add a signed-URL unsubscribe endpoint + `List-Unsubscribe-Post: List-Unsubscribe=One-Click` so Gmail can one-click it server-side.

Set `MAIL_UNSUBSCRIBE_MAILBOX` to an empty string to disable the header (not recommended in production).

## Multipart (plain-text alternatives)

All seven lifecycle Mailables shipped in Batch D include a `-text` Blade counterpart. No action required — Symfony Mailer assembles the multipart body automatically when both are set via the `html:` and `text:` keys of the `Content` envelope.

## Manual steps on server (deploy checklist)

1. Create a Resend account and add `erd-gut.de` as a sending domain → copy the 3 DKIM CNAME records.
2. Publish SPF + DKIM + DMARC in IONOS DNS (TTL 3600).
3. Wait for `dig txt erd-gut.de` to return the SPF record **and** the Resend dashboard to report all DKIM records verified.
4. Generate a production API key in Resend (scope: sending only, no full access).
5. Update `/.env` on the server:
   ```
   MAIL_MAILER=resend
   RESEND_KEY=re_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
   MAIL_UNSUBSCRIBE_MAILBOX=unsubscribe@erd-gut.de
   ```
6. `php artisan config:clear && php artisan config:cache`
7. Send a smoke-test via `php artisan tinker` → `Mail::raw('ping', fn ($m) => $m->to('you@gmail.com')->subject('Erdgut deliverability check'));` and verify it lands in the inbox (not spam) with `mail-tester.com` scoring ≥ 9/10.
8. Two weeks later: flip DMARC `p=quarantine` → `p=reject`.

## Rollback

If deliverability regresses, set `MAIL_MAILER=smtp` and clear config — the smtp block in `config/mail.php` still points at IONOS and works as a fallback. The List-Unsubscribe header continues to ship regardless of driver.
