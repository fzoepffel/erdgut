# Erdgut

Erdgut connects small-scale farmers with Berliners through weekly subscription boxes of transparently sourced produce.

## Requirements

- PHP 8.3
- Composer
- Node.js 20+
- MySQL 8 (or SQLite for local dev)

## Local Setup

### 1. Clone and install dependencies

```bash
git clone https://github.com/fzoepffel/erdgut.git
cd erdgut

composer install
npm install
```

### 2. Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and set at least:
- `APP_URL` (e.g. `http://localhost:8000`)
- `DB_*` for MySQL, or use SQLite (`DB_CONNECTION=sqlite`, `DB_DATABASE=database/database.sqlite`)

If you use SQLite, create the database file once:

```bash
touch database/database.sqlite
```

### 3. Database

```bash
php artisan migrate
```

### 4. Seed (optional)

```bash
php artisan db:seed
```

`db:seed` currently runs the default Laravel seeder scaffold (no Erdgut sample data yet).

### 5. Frontend assets

```bash
npm run build
```

For development with hot reload:

```bash
npm run dev
```

### 6. Run the app

```bash
php artisan serve
```

Open http://localhost:8000

## Commands

| Command | Description |
|---------|-------------|
| `composer lint` | Check PHP style (Pint) |
| `composer format` | Fix PHP style |
| `php artisan migrate` | Run migrations |
| `php artisan db:seed` | Seed database |
| `npm run build` | Build production assets |
| `npm run dev` | Dev server with Vite HMR |

## Deployment

See [DEPLOYMENT.md](DEPLOYMENT.md) for staging and production deploy via GitHub Actions.
