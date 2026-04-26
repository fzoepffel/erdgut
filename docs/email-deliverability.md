# Email deliverability — IONOS path (ERD-340)

Erdgut sends transactional email through IONOS' SMTP (the same provider that hosts the app). This document is the single source of truth for the DNS records and IONOS control-panel settings that keep mail out of Gmail/Yahoo spam folders, plus the rationale behind the `List-Unsubscribe` header.

## Why this exists

Gmail and Yahoo have enforced bulk-sender rules since Feb 2024:

1. **SPF + DKIM + DMARC must all align.** SPF authorises the sending IP; DKIM cryptographically signs each message; DMARC is the policy that ties the two to the From: domain.
2. **`List-Unsubscribe` header on every message.** Already shipped — see [app/Listeners/AddListUnsubscribeHeader.php](../app/Listeners/AddListUnsubscribeHeader.php).
3. **Spam rate < 0.3 %** (measured by user "report spam" clicks).

Erdgut is well below the 5 000-emails/day "bulk sender" threshold today, but Gmail still penalises senders that fail (1) — quietly, by spam-foldering. Right now the only known offender is the Laravel verification email; getting (1) + (2) right will fix that and raise the floor for the other six lifecycle mails too.

## What we DO NOT do (deliberate)

- **No third-party transactional provider** (Resend / Postmark / SendGrid). At Erdgut's volume IONOS' shared SMTP is fine *if* SPF/DKIM/DMARC are clean. Re-evaluate only if mail-tester.com keeps scoring < 9/10 after this setup.
- **No DKIM key generation in code.** IONOS signs DKIM at the SMTP relay when you enable it in their control panel; Laravel's mail driver doesn't need a private key.

## DNS records — three records on `erd-gut.de`

All three records go in **IONOS Domains & SSL → erd-gut.de → DNS**. TTL: leave at default (3600). After publishing, run `dig` on each (commands below) to confirm they resolved before testing mail.

### 1. SPF

| Type | Host | Value |
| ---- | ---- | ----- |
| TXT  | `@`  | `v=spf1 include:_spf.perfora.net include:_spf.kundenserver.de ~all` |

This is the standard SPF for IONOS-hosted mail. The two `include:` clauses cover both of IONOS' outgoing relay pools (`perfora.net` for hosting customers, `kundenserver.de` for IONOS Mail / business customers — IONOS uses one or the other depending on the product, so listing both is safe and the canonical recommendation).

The `~all` ("softfail") tells receivers: if the message arrives from anywhere not on this list, treat it as suspicious but don't reject. After 2 weeks of clean DMARC reports, you can tighten to `-all` (hardfail).

**Important:** if `erd-gut.de` *already* has a TXT record on `@` starting with `v=spf1`, do NOT publish a second one — two SPF records on the same domain are both invalid per RFC. Edit the existing record to merge in any missing `include:` clauses.

Verify after publishing:
```bash
dig +short txt erd-gut.de
```

### 2. DKIM (auto-configured by IONOS when domain uses IONOS nameservers)

Per [IONOS' DKIM help page](https://www.ionos.de/hilfe/domains/mailserver-und-verwandte-records-konfigurieren/e-mail-authentifizierung-mit-dkim/): if `erd-gut.de` uses IONOS nameservers (the default for IONOS-hosted domains), DKIM is already published automatically — no UI toggle needed, and no `default._domainkey` record. IONOS uses CNAME records on three custom selectors instead:

| Type  | Host | Value | TTL |
| ----- | ---- | ----- | --- |
| CNAME | `s1-ionos._domainkey`           | `s1.dkim.ionos.com` | 3600 |
| CNAME | `s2-ionos._domainkey`           | `s2.dkim.ionos.com` | 3600 |
| CNAME | `s{customer-id}._domainkey`     | `s{customer-id}.dkim.ionos.com` | 3600 |

The third selector is account-specific — IONOS' example was `s42582890._domainkey`, but the actual value differs per customer. Log into IONOS → **Domains & SSL → erd-gut.de → DNS** and scroll the record list to find it.

**Verify the auto-published records resolve:**
```bash
dig +short cname s1-ionos._domainkey.erd-gut.de
# expect: s1.dkim.ionos.com.

dig +short cname s2-ionos._domainkey.erd-gut.de
# expect: s2.dkim.ionos.com.
```

If both return the expected values, DKIM is active — no action needed. Skip to DMARC.

**If nothing resolves**, the records were deleted at some point. Re-add `s1-ionos._domainkey` + `s2-ionos._domainkey` as CNAMEs in the IONOS DNS panel using the values above. For the customer-specific third selector, contact IONOS support (or check the DNS panel for any other `_domainkey` CNAME — it's the one with a numeric prefix like `s42582890`).

**No control-panel toggle exists** for DKIM on Mail Basic or any IONOS tier — DKIM is automatic via DNS when IONOS nameservers are in use. Don't waste time looking for one.

### 3. DMARC

| Type | Host      | Value |
| ---- | --------- | ----- |
| TXT  | `_dmarc`  | `v=DMARC1; p=quarantine; rua=mailto:dmarc-reports@erd-gut.de; adkim=s; aspf=s; pct=100` |

Breakdown:
- `p=quarantine` — start in spam-fold mode; tighten to `p=reject` after 2 clean weeks.
- `rua=mailto:dmarc-reports@erd-gut.de` — aggregate report inbox (daily XML summaries from Gmail/Yahoo/Outlook). You need a real inbox here. Two easy options:
  - **Forward `dmarc-reports@erd-gut.de` → your ops inbox** in IONOS Email → Forwarding.
  - Or sign up at **https://dmarcian.com** (free tier) and use the address they give you. Their dashboard parses the XML into something readable.
- `adkim=s` / `aspf=s` — strict alignment. The From: domain must exactly match the DKIM-signing domain and SPF-authorised domain. Subdomains don't count.
- `pct=100` — apply the policy to 100 % of mail.

**Don't skip this.** DMARC reports are how you find out *before* Gmail starts spam-foldering you that something's misaligned. The first week of reports often surfaces a forgotten alias or a third-party tool that was sending as you.

Verify:
```bash
dig +short txt _dmarc.erd-gut.de
```

## Server `.env` — what to set

The current production `.env` already uses IONOS SMTP. The only addition is the unsubscribe mailbox:

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.ionos.de        # or whatever your existing IONOS host is — leave it
MAIL_PORT=587
MAIL_USERNAME=noreply@erd-gut.de
MAIL_PASSWORD=********
MAIL_FROM_ADDRESS="noreply@erd-gut.de"
MAIL_FROM_NAME="Erdgut"

MAIL_UNSUBSCRIBE_MAILBOX="unsubscribe@erd-gut.de"
```

After editing:
```bash
php artisan config:clear
php artisan config:cache
```

## List-Unsubscribe header

Every outgoing mail receives a `List-Unsubscribe: <mailto:unsubscribe@erd-gut.de?subject=unsubscribe>` header via [app/Listeners/AddListUnsubscribeHeader.php](../app/Listeners/AddListUnsubscribeHeader.php). The listener noops when `MAIL_UNSUBSCRIBE_MAILBOX` is empty, never overwrites an existing header.

You need to either:
- Create an actual `unsubscribe@erd-gut.de` mailbox in IONOS Email and check it manually (low volume), OR
- Forward `unsubscribe@erd-gut.de` → your ops inbox via IONOS forwarding.

Volume should be low (a handful per quarter at current scale). Once you're processing more than ~5/week, build the proper signed-URL one-click endpoint plus `List-Unsubscribe-Post: List-Unsubscribe=One-Click` so Gmail can one-click it server-side.

## Multipart (plain-text alternatives)

All seven lifecycle Mailables ship a `-text` Blade counterpart (added in Batch D). Symfony Mailer assembles the multipart body automatically from the `html:` and `text:` keys of the `Content` envelope. No action required.

## Manual steps on server (deploy checklist)

1. **DNS:** publish SPF + DMARC TXT records (records #1 and #3 above) in IONOS Domains → DNS.
2. **DKIM:** verify the three IONOS-managed CNAMEs already resolve (the `dig` commands in record #2). If yes, no action. If empty, re-add `s1-ionos._domainkey` and `s2-ionos._domainkey` as CNAMEs and contact IONOS support for the customer-specific third selector.
3. **Verify DNS** with the three `dig` commands above. SPF + DMARC should appear within minutes; DKIM may take 10–30 min after activation.
4. **Mailbox:** create or forward `unsubscribe@erd-gut.de` and `dmarc-reports@erd-gut.de` to a real inbox.
5. **`.env`:** add `MAIL_UNSUBSCRIBE_MAILBOX=unsubscribe@erd-gut.de`. Run `php artisan config:clear && php artisan config:cache`.
6. **Smoke-test:**
   ```bash
   php artisan tinker
   ```
   ```php
   use Illuminate\Support\Facades\Mail;
   Mail::raw('ping', fn ($m) => $m->to('your-personal-gmail@gmail.com')->subject('Erdgut deliverability check'));
   ```
   Check Gmail headers (Show original): `dkim=pass`, `spf=pass`, `dmarc=pass`. Hover the sender → Gmail shows an "Unsubscribe" link. That confirms the `List-Unsubscribe` header is being read.

   Stricter check: send to a fresh `test-xxxx@mail-tester.com` address (https://mail-tester.com), then click their score button. **≥ 9/10 = production-ready.** If lower, the report itself tells you exactly what's failing.

7. **Two weeks later:** if DMARC reports show no surprise senders, tighten the `_dmarc` record from `p=quarantine` to `p=reject`.

## Rollback

The List-Unsubscribe header is harmless — if it ever causes problems (it won't), set `MAIL_UNSUBSCRIBE_MAILBOX=` (empty) and the listener noops.

DNS rollback: simply delete the SPF, DKIM, and DMARC records. You're back to whatever silent state IONOS was in before. Mail still flows.
