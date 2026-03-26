<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Deploy (Composer / `fleet/idp-client`)

**`composer install` on the server** must resolve **`fleet/idp-client`** from our private mirror, not a missing local path. Canonical package URL: **[packages.shafer.llc/packages/fleet/idp-client](https://packages.shafer.llc/packages/fleet/idp-client)**.

1. Configure auth for the registry on the build host (Forge/Ploi/GitHub Actions/Docker), e.g.  
   `composer config http-basic.packages.shafer.llc USERNAME TOKEN`  
   or set **`COMPOSER_AUTH`** with a base64-encoded `{"http-basic":{"packages.shafer.llc":{"username":"…","password":"…"}}}` payload.
2. Run **`composer install --no-dev --optimize-autoloader`** (or your pipeline equivalent) from the app root; **`composer.lock`** pins the version.
3. **Do not** rely on a sibling **`../fleet-idp-client`** checkout on the server — that is only for optional local development (see below).
4. Optional: after first deploy, run **`php artisan fleet:idp:configure`** (package **≥ 0.4**) from the app root if Fleet Auth exposes **`FLEET_AUTH_CLI_SETUP_TOKEN`** — see the [fleet/idp-client README](https://github.com/shaferllc/fleet-idp-client/blob/main/README.md#cli-bootstrap-fleetidpconfigure).

**Local development** against a git checkout of **`fleet-idp-client`** next to this repo:

```bash
composer config repositories.fleet-idp-client '{"type":"path","url":"../fleet-idp-client","options":{"symlink":true}}'
composer update fleet/idp-client
```

Remove that repository when you want to match production (`composer config --unset repositories.fleet-idp-client` then `composer update fleet/idp-client`).

## Fleet operator API

Internal JSON endpoints for fleet-wide dashboards (e.g. Fleet Console). Auth is **`dply/fleet-operator`** middleware: **`Authorization: Bearer <token>`** (same secret as **`FLEET_OPERATOR_TOKEN`** in `.env`). If the token is missing or empty, responses are **404** JSON (`Operator API is not configured`).

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/operator/summary` | Snapshot counts / metrics |
| GET | `/api/operator/readme` | Root `README.md` as JSON (`format`, `content`, `title`) |

Product APIs are defined in `routes/api.php` and `routes/web.php`.

## Fleet Auth (central login)

Sign-in can use **Fleet Auth** ([shaferllc/fleet-auth](https://github.com/shaferllc/fleet-auth)) via the Composer package **`fleet/idp-client`**:

- **Registry:** [packages.shafer.llc/packages/fleet/idp-client](https://packages.shafer.llc/packages/fleet/idp-client) (configure `http-basic.packages.shafer.llc` for `composer install`).
- **Docs (views, routes, controllers):** see the package README “Views and UI” on [GitHub](https://github.com/shaferllc/fleet-idp-client/blob/main/README.md).

This repo’s `composer.json` includes the **`https://packages.shafer.llc`** repository (`canonical: false`). For a path checkout of the package while hacking on it, see [Deploy (Composer / `fleet-idp-client`)](#deploy-composer--fleetidp-client). **`fleet/idp-client` registers** `GET /oauth/fleet-auth` and the OAuth callback route from `FLEET_IDP_REDIRECT_PATH` (default `/oauth/fleet-auth/callback`); the login UI uses **`x-fleet-idp::oauth-button`** (no app controller).

- **Continue with Fleet** — OAuth2 authorization code via package routes; callback path must match Passport.
- **Email / password** — Passport password grant against the same IdP; the local `User` record is synced from `GET /api/user`. The account must already exist on **Fleet Auth** (e.g. via **`/register`** there).
- **First-time OAuth** — If you have no Fleet Auth user yet, open **Continue with Fleet**, use **Create account** on the Fleet Auth login page, then approve the app; Waypost **creates your local user** on the callback automatically.

Copy client credentials from Fleet Auth after `php artisan db:seed`. In **Waypost** `.env` (see `.env.example`):

| Variable | Purpose |
|----------|---------|
| `FLEET_IDP_URL` | Fleet Auth base URL only (not Waypost’s callback) |
| `FLEET_IDP_CLIENT_ID` / `FLEET_IDP_CLIENT_SECRET` | Auth-code client |
| `FLEET_IDP_REDIRECT_URI` | Default `${APP_URL}/oauth/fleet-auth/callback` — must match Passport |
| `FLEET_IDP_PASSWORD_CLIENT_ID` / `FLEET_IDP_PASSWORD_CLIENT_SECRET` | Password grant client |
| `FLEET_IDP_USER_MODEL` | Optional; defaults to `App\Models\User` |

