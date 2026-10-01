# iPharmaLink

iPharmaLink is a PHP and MySQL pharmacy commerce application. Its current implemented workflows include retail pharmacy storefronts, customer orders, pharmacy inventory, delivery management, and administration. It does not require an AI API.

## Local preview

With XAMPP Apache and MySQL running, open `http://localhost/iPharmaLink/`. The app detects its subfolder from Apache and keeps routes, links, assets, and redirects inside it. Set `APP_URL=http://localhost/iPharmaLink` in `.env` when generating absolute links for emails or callbacks.

To preview from the PHP development server instead, run this from the project directory:

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8000 tools/dev-router.php
```

Open `http://127.0.0.1:8000` for that development-server preview.

## Inventory alerts

Fresh databases get `inventory_alerts` from `database/schema.sql`. For an existing XAMPP database, run the additive migration once:

```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\iPharmaLink\tools\migrate-inventory-alerts.php
```

Alternatively, import `database/migrations/001_inventory_alerts.sql` in phpMyAdmin after selecting the `ipharmalink` database.

The supplier registration and verification queue use a separate supplier organization model. Add its schema and role to an existing database with:

```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\iPharmaLink\tools\migrate-supplier-onboarding.php
```

New installations include supplier tables in `database/schema.sql` and the supplier role/permission in `database/seed.sql`.

Stock changes update alert state immediately. A scheduled scan also checks all active products for low stock, out of stock, and expiry within 90, 60, or 30 days. A notification is created when an alert becomes active; repeated scans do not duplicate it. When the product recovers or is no longer in the alert window, its alert is resolved. The next recurrence can create a new notification.

Run one scan from a Windows terminal:

```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\iPharmaLink\cron\inventory-alerts.php
```

For automatic checks, configure Windows Task Scheduler to run that command daily. The script reads the existing `.env` and uses the app's configured MySQL connection; it is CLI-only and does not expose a web endpoint.
