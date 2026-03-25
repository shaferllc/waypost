# Laravel Reverb: local dev and production

Waypost broadcasts lightweight `ProjectDataUpdated` events on the `private-project.{id}` channel so open project pages can refresh without a full reload. That requires **Laravel Reverb** (or another broadcaster) when `BROADCAST_CONNECTION=reverb`.

## Local development

1. Copy Reverb variables from `.env.example` into `.env` (`BROADCAST_CONNECTION=reverb`, `REVERB_*`, `VITE_REVERB_*`).
2. Run the main stack: `composer run dev` (serve, queue, logs, Vite).
3. In a **second terminal**, start the WebSocket server:

   ```bash
   composer run reverb
   ```

   (`php artisan reverb:start`)

Without Reverb running, the app still works; Livewire polling on the project page continues to pick up changes on a delay.

## Production

- Run Reverb as a **long-lived process** (Supervisor, systemd, or your platform’s worker type). Example Supervisor program:

  ```ini
  [program:waypost-reverb]
  command=php /path/to/waypost/artisan reverb:start
  directory=/path/to/waypost
  autostart=true
  autorestart=true
  user=www-data
  ```

- Terminate TLS at **your reverse proxy** (nginx, Caddy, load balancer) and proxy WebSocket upgrades to Reverb’s host/port. Clients use `wss://` when the site is HTTPS.

- Set **`VITE_REVERB_HOST`** (and scheme/port if non-default) in the build environment so the compiled JS points at the public hostname clients use, not `127.0.0.1`.

- Ensure firewall rules allow the proxy → Reverb port; do not expose Reverb directly to the public internet without TLS.

- Use the same **`REVERB_APP_KEY`** / **`REVERB_APP_SECRET`** / **`REVERB_APP_ID`** in `.env` for the app, queue workers, and the Reverb process.

For HTTP API usage only, Reverb is optional; it affects signed-in browser UI freshness, not `POST`/`PATCH` responses.
