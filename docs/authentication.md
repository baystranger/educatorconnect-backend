# Authentication API

This Laravel 12 API uses Sanctum's HTTP-only session cookies for the first-party web frontend and bearer tokens for other API clients. Parents do not have accounts; public childcare search endpoints remain unauthenticated.

## Local setup

1. Copy `.env.example` to `.env`, generate an application key, and configure a local MySQL database (SQLite is also supported for development and tests).
2. Set `FRONTEND_URL` and `FRONTEND_URL_ALTERNATE` to exact frontend origins. Add each frontend host and port to `SANCTUM_STATEFUL_DOMAINS`. Use the same hostname for the frontend and API in the browser (for example, `localhost` for both).
3. Add the Google and LinkedIn OAuth client credentials. Register the callback URLs from `GOOGLE_REDIRECT_URI` and `LINKEDIN_REDIRECT_URI` with each provider.
4. Run `composer install`, `php artisan migrate --seed`, then `php artisan serve`.
5. In the frontend, copy its `.env.example` to `.env.local` if the API is not at the default URL and set `VITE_API_BASE_URL`. Run `npm run dev`.

Do not commit `.env` files or provider credentials. Use HTTPS, `APP_DEBUG=false`, a production database, and a real mail provider in production. OAuth is unavailable until valid provider credentials and matching callback URLs are configured.

## Endpoints

All JSON API routes are versioned under `/api/v1`.

| Method | Endpoint | Access | Purpose |
| --- | --- | --- | --- |
| POST | `/auth/register` | Public, rate-limited | Create educator or childcare-centre account and profile |
| POST | `/auth/login` | Public, rate-limited | Start a browser session and issue a Sanctum token for API clients |
| GET | `/auth/me` | Bearer token | Return the signed-in account |
| POST | `/auth/logout` | Bearer token | Revoke the current token |
| POST | `/auth/forgot-password` | Public, rate-limited | Send a non-enumerating password-reset email |
| POST | `/auth/reset-password` | Public, rate-limited | Reset a password and revoke existing tokens |
| POST | `/auth/oauth/exchange` | Public, rate-limited | Exchange a short-lived, single-use OAuth code for a browser session or bearer token |

OAuth start and callback routes use the Laravel web/session middleware at `/oauth/{provider}/redirect` and `/oauth/{provider}/callback` so Socialite can validate state. The callback returns a hashed-at-rest, two-minute, single-use code to the frontend; exchanging that code establishes a browser session (or returns a bearer token to a non-browser API client), and provider access tokens are never exposed to the browser. Logout invalidates the browser session or revokes the current bearer token.

Signup accepts only `educator` and `childcare_centre` roles. Password signup requires a confirmed password with at least 10 characters, a number, and a symbol. Roles and profiles are created transactionally. The frontend uses Laravel's CSRF-protected session cookie and does not store bearer credentials in browser storage. The login `remember` option uses Laravel's persistent remember cookie; enable secure cookies and HTTPS in production.

Run the auth-focused API tests with `php artisan test --filter=AuthApiTest`.
