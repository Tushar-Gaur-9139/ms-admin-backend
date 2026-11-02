# M.S. Enterprises Backend

Laravel API shared by the storefront and the separate admin frontend. Both applications use the same users, roles, and database through this backend.

## Local setup

1. Install PHP dependencies with `composer install`.
2. Set `DB_CONNECTION=sqlite` and `DB_DATABASE=database/database.sqlite` in `.env`, then run `php artisan migrate`.
3. Configure `SANCTUM_STATEFUL_DOMAINS` for the local frontend origins and set `ADMIN_FRONTEND_URL=http://localhost:5173`.
4. Start Laravel with `php artisan serve --host=127.0.0.1 --port=8000`.
5. Start `ms-admin-frontend` with `npm run dev`. Its Vite proxy forwards API and CSRF requests to Laravel.

The PHP CLI must have `pdo_sqlite` and `sqlite3` enabled to use the local SQLite database. If PHP reports `could not find driver`, enable those extensions in the active PHP configuration before starting the API.

## Authentication API

- `POST /api/v1/auth/register` — creates an account with the server-controlled `customer` role; submitted roles are ignored.
- `POST /api/v1/auth/login` — starts a session for an account with an approved staff role.
- `POST /api/v1/auth/logout` and `GET /api/v1/auth/me` — protected session operations.
- `POST /api/v1/auth/forgot-password` and `POST /api/v1/auth/reset-password` — sends a time-limited password-reset link and accepts a valid reset token.

Authentication uses Laravel Sanctum's stateful session cookies and CSRF protection. Passwords are hashed by Laravel. Inputs are validated server-side; database access uses Eloquent/query-builder parameter binding rather than SQL assembled from user input. Authentication and password recovery routes are rate-limited, and forgot-password responses do not reveal whether an email is registered.

## Staff roles

Self-registration never grants admin access. After an account is registered, a trusted server operator can assign an approved role:

```bash
php artisan users:assign-role staff@example.com admin
```

Supported roles are `admin`, `catalog`, `orders`, `inventory`, `support`, and `finance`. The command also supports `customer`. Use it to promote the first administrator; staff-role management through the admin UI can be added later.

## Deployment

For separate subdomains such as `shop.example.com`, `admin.example.com`, and `api.example.com`, configure:

- `ADMIN_FRONTEND_URL=https://admin.example.com`
- `FRONTEND_ALLOWED_ORIGINS=https://shop.example.com,https://admin.example.com`
- `SANCTUM_STATEFUL_DOMAINS=shop.example.com,admin.example.com`
- `SESSION_DOMAIN=.example.com`
- `SESSION_SECURE_COOKIE=true`

Configure a real mail transport before relying on password-reset email in production. Do not expose debug error pages in production; keep `APP_DEBUG=false`.

The storefront frontend is still a local demo and must be connected to these shared APIs before its customer accounts, catalogue, carts, and orders are database-backed.
