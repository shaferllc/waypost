<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Waypost (this application)

Waypost is a small, free project workspace: **projects**, **Kanban tasks**, **roadmap versions and themes**, **OKRs**, **wishlist**, **links**, optional **editor sync (MCP)**, and a **read-only public roadmap** URL you can share. There is no billing or team tiering—see the marketing copy on `/` for positioning.

**Typical flow:** create a project → add tasks on **Board** → plan initiatives and versions on **Roadmap** → copy a **public roadmap** link from project settings when you want stakeholders to view goals and releases without logging in. CSV export and a personal API (see `docs/api.md`) support automation and backups.

**Developing this app in Cursor:** MCP uses `WAYPOST_API_TOKEN` from the **project** whose work you want to track (project → Sync). For this repository, use the **Waypost** product project’s token—not another project’s—so scoped API/MCP calls hit the correct `project_id`. See **`waypost.json.example`** and **`.cursor/rules/waypost-repo-mcp.mdc`**.

**Performance:** the board loads all tasks for the project in one request (virtual scrolling / lazy loading is not implemented). Above a few hundred tasks, use search and roadmap filters, split work across projects, or expect slower interactions—the UI warns when task count exceeds 300.

The sections below are upstream Laravel README material retained for framework reference.

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

## Deploy (Composer / `shaferllc/fleet-idp-client`)

Fleet login uses **`shaferllc/fleet-idp-client`** from **[Packagist](https://packagist.org/packages/shaferllc/fleet-idp-client)** (the **`fleet`** vendor name is taken on Packagist.org, so the package is not named `fleet/idp-client`).

1. Run **`composer install --no-dev --optimize-autoloader`** on the server; **`composer.lock`** pins the version. No private Composer registry or HTTP-basic auth is required for this package once it is on Packagist.
2. **`composer.json`** lists a **`path`** repository for **`../fleet-idp-client`** first (symlinked local package). On hosts **without** that sibling folder, remove that repository block (keep the VCS entry) and run **`composer update shaferllc/fleet-idp-client`** so the lock uses **dist/git** again. The fallback VCS URL is **GitHub `main`** (`dev-main as 0.9.99`) until **v0.9+** tags exist on [Packagist](https://packagist.org/packages/shaferllc/fleet-idp-client).
3. Optional: after first deploy, run **`php artisan fleet:idp:configure`** if Fleet Auth exposes **`FLEET_AUTH_CLI_SETUP_TOKEN`** — see the [package README](https://github.com/shaferllc/fleet-idp-client/blob/main/README.md#cli-bootstrap-fleetidpconfigure).
4. Optional: publish themed package assets once per app — **`php artisan fleet:idp:install`** (views under **`resources/views/vendor/fleet-idp/`**, lang, account layout stub).

**Local development** with **`fleet-idp-client`** next to this repo (`Apps/fleet-idp-client` + `Apps/waypost`): the path repository is already in **`composer.json`**. Run **`composer update shaferllc/fleet-idp-client`** once; **`vendor/shaferllc/fleet-idp-client`** becomes a symlink. The package **`composer.json`** uses **`"version": "dev-main"`** so it satisfies **`dev-main as 0.9.99`**.

### Troubleshooting: `Source path "../fleet-idp-client" is not found`

That means **`composer.lock`** still records **`shaferllc/fleet-idp-client`** as a **path** install (or your environment merged in a path repository). Fix:

1. From the app root run **`composer update shaferllc/fleet-idp-client`** and **commit the updated `composer.lock`** so CI/deploy matches `composer.json`.
2. If you added a path repo locally: **`composer config --unset repositories.fleet-idp-client`** (or whatever name you used), then update again.
3. Check global config: **`composer config --global --list`** and remove any **`repositories.*`** entry pointing at **`../fleet-idp-client`**.

### Troubleshooting: `git@github.com: Permission denied (publickey)`

Composer is installing **from git source** over SSH. Prefer **Packagist** + **`"preferred-install": "dist"`** (already set in this app) so the host downloads a **zip** instead. Ensure the **`shaferllc/fleet-idp-client`** GitHub repository is **public**, or supply **`COMPOSER_AUTH`** with a **github.com** token for private repos. See [Composer authentication](https://getcomposer.org/doc/articles/authentication-for-private-packages.md).

## Fleet operator API

Internal JSON endpoints for fleet-wide dashboards (e.g. Fleet Console). Auth is **`dply/fleet-operator`** middleware: **`Authorization: Bearer <token>`** (same secret as **`FLEET_OPERATOR_TOKEN`** in `.env`). If the token is missing or empty, responses are **404** JSON (`Operator API is not configured`).

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/operator/summary` | Snapshot counts / metrics |
| GET | `/api/operator/readme` | Root `README.md` as JSON (`format`, `content`, `title`) |

Product APIs are defined in `routes/api.php` and `routes/web.php`.

## Fleet Auth (central login)

Sign-in can use **Fleet Auth** ([shaferllc/fleet-auth](https://github.com/shaferllc/fleet-auth)) via the Composer package **`shaferllc/fleet-idp-client`**:

- **Packagist:** [packagist.org/packages/shaferllc/fleet-idp-client](https://packagist.org/packages/shaferllc/fleet-idp-client)
- **New satellite apps:** run **`php artisan fleet:idp:install`** to publish package Blade/lang (then theme them), then **`php artisan fleet:idp:configure`** for `.env` secrets. Coding agents can use the package wiki [AI assistant: satellite integration](https://github.com/shaferllc/fleet-idp-client/blob/main/docs/wiki/AI-assistant-satellite-integration.md).
- **Docs (views, routes, controllers):** see the package README on [GitHub](https://github.com/shaferllc/fleet-idp-client/blob/main/README.md).
- **Reusing styled account views in other apps** (reset password Blade, Livewire profile password form, Fleet-aware notices): [Custom account views](https://github.com/shaferllc/fleet-idp-client/blob/main/docs/wiki/Custom-account-views.md) in the package wiki. Waypost’s reference copies live at `resources/views/auth/reset-password.blade.php`, `resources/views/auth/forgot-password.blade.php`, and `resources/views/livewire/profile/update-password-form.blade.php`.

For deploy notes and local path checkouts, see [Deploy (Composer / `shaferllc/fleet-idp-client`)](#deploy-composer--shaferllcfleet-idp-client). The package registers **`GET /oauth/fleet-auth`** and the OAuth callback route from **`FLEET_IDP_REDIRECT_PATH`** (default **`/oauth/fleet-auth/callback`**); the login UI uses **`x-fleet-idp::oauth-button`** (no app controller).

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

