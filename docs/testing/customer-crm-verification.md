# Customer CRM verification

The customer registration flow is covered by `tests/Feature/Customers/CustomerCrmTest.php`.

The focused feature suite exercises customer creation, optional phone numbers, duplicate detection and confirmation, social-account creation, acquisition channels, province/city address persistence, profile interactions, tenant isolation, and customer search.

Run locally:

```bash
php artisan test --filter=CustomerCrmTest
npm run check
npm run types:check
npm run build
```

The GitHub Actions workflow uses PHP 8.4 and an in-memory SQLite database for its setup migration and feature tests.
