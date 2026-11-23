# Dakila Accounting API

Laravel 12 API with Sanctum session authentication, company-scoped authorization, and modular accounting services.

Follow the [root setup guide](../README.md#local-setup) for installation and environment configuration. The [developer guide](../docs/PROJECT_DOCUMENTATION.md) covers architecture, active modules, API access, and deployment. [Authentication recovery](../docs/AUTHENTICATION_RECOVERY.md) describes session and outage handling.

From this directory:

```bash
php artisan serve --host=127.0.0.1 --port=8000
php artisan route:list --path=api
```

Run the focused free-edition checks:

```bash
php artisan test --filter='ModuleAvailabilityTest|CurrentTenantAccessApiTest|AccountingDashboardApiTest'
```

Disabled business modules remain as historical ledger dependencies. Their presence in the source tree does not mean those features are enabled.
