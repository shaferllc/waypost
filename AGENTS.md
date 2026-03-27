## Learned User Preferences

- Prefer moving reusable Fleet satellite behavior (auth flows, middleware, profile helpers, two-factor settings) into a shared Composer package so multiple apps stay aligned.
- Prefer Livewire 4 over Volt; use Livewire or Alpine instead of ad-hoc raw JavaScript where interaction is needed.
- Satellite UX should honor Fleet Auth OAuth client settings: hide email code and magic link options when not enabled server-side; avoid exposing “Fleet” wording to end users in those flows when possible.
- Fleet Auth is the source of truth for OAuth client / integration policy: social providers, passwordless email options, optional vs required two-factor authentication, email verification requirements, and related satellite hints from `GET /api/social-login/providers`. Satellites mirror and enforce that JSON; a cached snapshot is only a performance replica—use a short TTL, `satellite_warm_providers_each_request` from the IdP, or the provisioning policy-cache purge after Integrations change so the app converges back to Fleet Auth quickly.
- Email sign-in methods (numeric code and/or magic link) should be confirmable via email before activation; turning off code or magic link should require password confirmation via a reusable modal pattern.
- Forgot-password flow should recognize fleet-managed emails, prompt for confirmation, and route reset through Fleet Auth when appropriate.

## Learned Workspace Facts

- Authoritative integration and auth policy for Fleet-linked apps lives on Fleet Auth (Passport client / Integrations). Satellites never invent competing policy over a valid IdP response; local env and cache only affect how and when that JSON is fetched.
- Waypost exposes Streamable HTTP MCP at `{public_base}/mcp/waypost` with a project-scoped Sanctum Bearer token; `waypost.json` and tooling reference `WAYPOST_API_TOKEN`, `WAYPOST_PUBLIC_URL`, and local hosts such as `waypost.test`.
- `WAYPOST_MCP_ENABLED` toggles POST `/mcp/waypost` (off yields 503 JSON); reachability and status payloads still report `mcp_enabled`.
- `waypost.mcp.http` lines with status 401 and `user_id: null` usually mean Sanctum did not get a valid project Bearer token, not a broken MCP route by itself.
- Cursor MCP failures may come from client-side server disablement, TLS or http→https redirect/CORS preflight issues, or certificate trust—not only Laravel application errors.
- Editor integration work has emphasized Cursor and URL-based MCP setup over maintaining a local stdio/npm agent where possible.
- Production HTTPS requires `APP_URL` and generated asset URLs to stay on HTTPS to avoid mixed-content blocking behind TLS terminators.
