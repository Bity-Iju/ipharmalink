# iPharmaLink

B2B pharmaceutical wholesale marketplace and retail pharmacy storefront built with PHP 8.2+, PDO, and MySQL 8.

## Current foundation

- Normalized marketplace schema in `database/schema.sql`
- PDO database bootstrap with environment configuration
- Small HTTP entry point in `public/index.php`
- Composer autoloading for `src/`

## Setup

```bash
cp .env.example .env
composer install
mysql -u root -p < database/schema.sql
php -S localhost:8000 -t public
```

The schema deliberately separates wholesale purchasing from retail customer sales:

`Supplier -> Wholesale order -> Retail pharmacy inventory -> Retail order -> Customer`

See `database/schema.sql` for roles, verification, product packaging, tiered pricing, batches, inventory movements, payments, credit terms, deliveries, and audit records.
