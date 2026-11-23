# Dakila Free Accounting

A focused accounting application built with Laravel, Next.js, PostgreSQL, and the existing Sneat/MUI template.

## Included

- Accounting dashboard with revenue, expenses, net income, assets, liabilities, and equity.
- Chart of accounts, fund sources, taxes, and transaction numbering.
- Journal entries, general ledger, trial balance, income statement, balance sheet, and cash flow.
- Fiscal years and accounting periods.
- Company-scoped roles and permissions using the existing custom authorization system.

Sales invoicing, customer management, inventory, bills, and unrelated settings are excluded from the free edition. Their backend compatibility code remains where existing accounting records depend on it.

## Requirements

- PHP 8.3+ and Composer 2, with the extensions required by Composer (including BCMath and PostgreSQL PDO).
- Node.js 20.9+ and pnpm.
- PostgreSQL and a database created for the application.

## Local setup

Run these commands from the repository root in two separate terminals.

### Backend

```bash
cd accountingsystem-backend
composer install
cp .env.example .env
php artisan key:generate
```

Set `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env`. Then initialize a **new local database**:

```bash
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

For an existing database, use `php artisan migrate`; do not rerun the initial seed or reset the database. The development seeder creates `aiur_admin@demo.com` with password `admin1234`. These are local demo credentials, not production credentials.

### Frontend

```bash
cd accountingsystem-frontend
pnpm install
cp .env.example .env.local
pnpm dev
```

Open [http://127.0.0.1:3001](http://127.0.0.1:3001). The example environment points both browser and server API requests to `http://127.0.0.1:8000`. Keep the hostname consistent across the browser, frontend, and backend.

## Repository contents

| Path | Purpose |
| --- | --- |
| `accountingsystem-backend/` | Laravel API, modules, migrations, and tests |
| `accountingsystem-frontend/` | Next.js app, template components, and frontend tests |
| `docs/` | Developer reference and authentication recovery behavior |
| `LICENSE` | Repository license |

See the [developer guide](docs/PROJECT_DOCUMENTATION.md) for architecture, checks, deployment, and troubleshooting, and [authentication recovery](docs/AUTHENTICATION_RECOVERY.md) for session behavior.

## License and credits

The repository uses the [MIT License](LICENSE). The retained frontend template declares a commercial license; confirm its redistribution terms before publishing an open-source distribution. Third-party assets retain their respective licenses.

Original contributors: [@talismantimpac](https://github.com/talismanjt) and @marcabanilla.
