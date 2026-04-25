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

### 2. DKIM (configured in the IONOS Email control panel, NOT manually)

DKIM on IONOS is enabled per-mailbox or per-domain in their **Email** control panel — not by adding records yourself.

1. Log in to IONOS → **Email** → click on the `erd-gut.de` mail domain.
2. Look for **"DKIM"** or **"DomainKeys Identified Mail"** in the settings (sometimes under "Security" or "Spam protection"). The exact menu label depends on which IONOS product the mailbox runs under.
3. Click **"Activate DKIM"** or **"Enable DKIM signing"**. IONOS generates the keypair and publishes the DNS record automatically (you may see a new TXT record on `default._domainkey.erd-gut.de` appear in the DNS panel — that's IONOS managing it).
4. Wait 5–10 minutes for activation. Re-check the DKIM status in the panel — it should read "Active".

If your IONOS plan doesn't expose DKIM activation in the UI, contact IONOS support and ask them to enable DKIM signing for outgoing mail on `erd-gut.de`. They can do it server-side. Reference: this is required for Gmail/Yahoo bulk-sender compliance.

Verify after activation:
```bash
dig +short txt default._domainkey.erd-gut.de
# expect: a long "v=DKIM1; k=rsa; p=MIGfM..." string
```

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
2. **DKIM:** enable DKIM signing in IONOS Email control panel for `erd-gut.de` (record #2). If the option is missing, open a ticket with IONOS support.
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
