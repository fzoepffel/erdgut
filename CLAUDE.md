# Erdgut Project

- **Stack**: Laravel 11, PHP 8.3, MySQL, Blade, Tailwind CSS, Alpine.js, Vite
- **Hosting**: IONOS Web Hosting Plus (PHP only, no Node runtime)
- **Deploy**: GitHub Actions → SSH/SCP archive upload + server extract
- **Payments**: SumUp (mandate + recurring charges)

## Conventions

- Use `composer lint` / `composer format` (Pint) for PHP style
- `.env.example` documents all required variables; never commit `.env`
- German locale (`de`) for user-facing content; English for code and comments
- **Payment tokens** (`sumup_token`, `sumup_customer_id`, `sumup_checkout_id`, `sumup_payment_id`, `provider_payload`): never expose to frontend, API responses, or logs. Use `$hidden` on models; keep token handling server-side only
- If necessary update the README
- Before starting one or several tickets at once always open a new branch

## Ticket Tracking

- **Ticket source**: Open tickets live in `erdgut-mvp-tickets.csv` at project root (columns: Ticket ID, Title, Epic, Phase, Priority, **Status**, Estimate Hours, …).
- **Fred product roadmap (ERD-194+)**: Additional backlog rows for the Fred × Erdgut Notion plan live in `docs/erdgut-fred-product-backlog.csv` with the same columns. Index and Notion mapping: `docs/erdgut-fred-product-backlog.md`. When working those tickets, mark **Status** to `Done` in that file (same commit as code), not in `erdgut-mvp-tickets.csv`, unless rows are merged later.
- **Redesign plan (ERD-300+)**: The unified-data-model redesign (post-audit) lives in `docs/erdgut-redesign-plan.csv` with an index at `docs/erdgut-redesign-plan.md`. Row `ERD-REDESIGN-00` holds locked decisions and project context — read it before picking up any ERD-3xx ticket. Mark `Done` in `docs/erdgut-redesign-plan.csv` (same commit as code).
- **Find next ticket**: The next ticket is the first row where `Status` is not `Done` (e.g. `Backlog` or `In Progress`). Scan the CSV from top to bottom and pick the first such row.
- **Mark ticket done** (mandatory): After completing a ticket, you MUST set its `Status` to `Done` in the CSV where that ticket lives (`erdgut-mvp-tickets.csv` for ERD-001–ERD-193, `docs/erdgut-fred-product-backlog.csv` for ERD-194–ERD-299, `docs/erdgut-redesign-plan.csv` for ERD-300+). Replace the current status value (e.g. `Backlog`) with `Done` in that row. Include this CSV change in the same commit as the implementation—do not skip this step.

## Git Flow After Implementing a Ticket

After implementing a ticket, always suggest this workflow:
1. Mark the ticket as `Done` in the correct CSV (`erdgut-mvp-tickets.csv`, `docs/erdgut-fred-product-backlog.csv`, or `docs/erdgut-redesign-plan.csv`, mandatory, same commit as code).
2. Create a new branch for the ticket (e.g. `feature/erd-XXX-description`).
3. Commit all changes (code + CSV) to this branch.
4. Push the branch to origin.
5. Deploy to staging by merging the branch into `staging` and pushing `staging`.
6. Push to `main` only if the user explicitly asks for it.

## Post-Implementation Manual Steps

After implementing or deploying a new feature, **always** tell the user if they need to do anything manually.

### When to Mention Manual Steps

- **Database migrations**: New migrations require `php artisan migrate --force` on the server
- **Environment variables**: New `.env` keys require updating the server `.env`
- **Config changes**: `php artisan config:clear` or `cache:clear` may be needed
- **Server setup**: SSH keys, cron jobs, file permissions, document root
- **Third-party setup**: API keys, webhooks, domains, SSL
- **Deployment**: Manual steps if CI/CD does not run migrations or deploy config

### How to Present

Add a short section at the end of your response, e.g.:

> **Manual steps on server**
> Run `php artisan migrate --force` in the app directory after deploy.

If there are no manual steps, you may briefly say so when relevant.

## Testing Trigger Rules

Write or update tests whenever a change affects behavior, data integrity, security, money flow, or scheduling.

### Must Add Tests

- New route, middleware, redirect, guard, or route name changes
- Any auth/account change (login, register, password reset, email verification, profile)
- Any DB migration, model relation, enum, cast, or business state transition
- Any payment logic (SumUp setup, webhook handling, retries, idempotency, token handling)
- Any scheduler/command/cron logic that mutates data or charges customers
- Any validation or authorization rule that can allow/deny user actions
- Bug fixes for regressions: first add a failing test, then implement the fix

### Minimum Test Types By Change

- Routing/Auth/UI flow changes: feature tests
- Domain/service logic (subscription, box, payment calculations): unit tests
- External event processing (webhooks, recurring charge commands): integration-style feature tests with fakes/mocks

### Definition of Done

- If behavior changed, tests changed in same PR
- Tests must assert outcomes (status, redirect, DB state, events), not only status 200
- Run relevant tests locally before finishing: `php artisan test`
- Relevant tests must pass before marking work complete
- If tests fail, either fix the issue in the same task or clearly document the blocker and do not claim the task is done

## Cursor Rules for Future Automation

After a new implementation, consider whether a new rule would help automate future workflows. If the implementation introduces a recurring pattern, convention, or workflow step that should be followed consistently, suggest adding a rule to capture it. Do not add rules for one-off or trivial cases.
