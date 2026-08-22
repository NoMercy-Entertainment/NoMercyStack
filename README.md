# nomercy-stack

A multi-service stack using Docker Compose, including MySQL, PostgreSQL, Keycloak, phpMyAdmin, pgAdmin, Portainer, Nginx proxy, and a website container. This is the production stack for the control plane (`nomercy-tv`) and Keycloak; it is also runnable locally for development and testing.

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop) (Windows, Mac) or [Docker Engine](https://docs.docker.com/engine/install/) (Linux)
- [Docker Compose](https://docs.docker.com/compose/install/) (usually included with Docker Desktop)

## Setup Instructions

1. **Clone the repository**
   ```sh
   git clone https://github.com/NoMercy-Entertainment/nomercy-stack.git
   cd nomercy-stack
   ```

1. **Provide SSL Certificates**
    - Place your SSL certificate files (`cert.pem` and `key.pem`) in the `proxy/certificates/` directory.
    - These files are required for the Nginx proxy to serve HTTPS traffic.

1. **Configure Domain and Environment Variables**
   - Copy `.env.example` to `.env` if provided, and adjust values as needed.
   - Some services may require additional configuration in their respective folders.

1. **Access Services**
   - **Website**: https://nomercy.tv (production; `proxy/sites/website.conf`)
   - **phpMyAdmin**: https://phpmyadmin.nomercy.tv
   - **pgAdmin**: https://pgadmin.nomercy.tv
   - **Keycloak**: https://auth.nomercy.tv
   - **Portainer**: https://portainer.nomercy.tv
   - **Nginx Proxy**: Handles routing to the above services

   > These are the production domains configured in `proxy/sites/`. Running the stack under your own domain, replace them there and below.

1. **Start the Stack**
   ```sh
   docker compose up -d
   ```
   This will start all services defined in `docker-compose.yml` and referenced compose files.

## Enabling Keycloak Login for Portainer

To enable Keycloak authentication in Portainer:

1. Log in to Portainer at https://portainer.nomercy.tv as an admin.
2. Go to **Settings** > **Authentication**.
3. Select **OAuth** as the authentication method.
4. Enter the following values (replace `nomercy.tv` with your own domain if self-hosting the stack):

   - **Client ID**: `master`
   - **Client Secret**: `*******` (your Keycloak client secret)
   - **Authorization URL**: `https://auth.nomercy.tv/realms/master/protocol/openid-connect/auth`
   - **Access Token URL**: `https://auth.nomercy.tv/realms/master/protocol/openid-connect/token`
   - **Resource URL**: `https://auth.nomercy.tv/realms/master/protocol/openid-connect/userinfo`
   - **Redirect URL**: `https://portainer.nomercy.tv`
   - **Logout URL**: `https://auth.nomercy.tv/realms/master/protocol/openid-connect/logout?redirect_uri=https://portainer.nomercy.tv/#!/auth`
   - **User Identifier**: `email`
   - **Scopes**: `openid profile email`
   - **Auth Style**: (leave as default or as required by your setup)

5. Save the settings. You should now be able to log in to Portainer using Keycloak.

## Folder Structure

- `docker/`         - Additional compose files for Cloudflared tunnel and GitHub runner.
- `keycloak/`       - Keycloak service and custom themes
- `mysql/`          - MySQL service and data
- `pgadmin/`        - pgAdmin service
- `phpmyadmin/`     - phpMyAdmin service
- `portainer/`      - Portainer service and data
- `postgres/`       - PostgreSQL service and data
- `postgres-kc/`    - Separate PostgreSQL for Keycloak
- `proxy/`          - Nginx reverse proxy, configs, certificates, and site definitions
- `website/`        - Website container (PHP, Nginx, etc.)
- `shares/`         - Shared files (if any)
- `scripts/`        - Host provisioning + maintenance scripts (see below)
- `fail2ban/`       - Fail2Ban jail/filter config and compose file
- `autoheal/`       - Autoheal service that restarts unhealthy containers
- `dns/`            - DNS service config
- `logs/`           - Bind-mounted log output (nginx, etc.), rotated by `scripts/`

## Host provisioning scripts (production droplet)

These install host-level config that lives outside any container (docker
daemon, system logrotate) so it survives a fresh droplet provision. Run as
root from the stack root on the target host, and re-run any time after
pulling changes to `docker/daemon.json` or `scripts/logrotate/`:

```sh
./scripts/provision-docker-daemon-logging.sh   # caps every container's json-file log (max-size 20m x max-file 5)
./scripts/provision-nginx-logrotate.sh         # rotates the bind-mounted logs/nginx/*.log files
```

`scripts/disk-cleanup.sh` runs daily via cron (`/etc/cron.d` or `crontab -l`
on the host) to prune Docker build cache/images and vacuum the journal.

## Zero-downtime website deploys (blue-green)

Production deploys of the website no longer stop-and-recreate a single
container. `scripts/deploy-website.sh` (invoked by nomercy-tv's `ci.yml`
`deploy` job) builds and boots the **idle** color (`website-blue` /
`website-green`, see `website/website-compose.yml`), health-gates it (docker
healthcheck + a real `curl /up` from inside the container), and only then
flips the nginx upstream to it with `nginx -s reload`. A graceful reload
finishes in-flight requests on the old upstream and sends new connections to
the new one — the `:80`/`:443` listener socket never closes, so there is no
refused-connection window. The previous color drains for 15s then gets
stopped (not removed), ready to be next deploy's idle target.

State lives in two gitignored, server-local files:
- `website/.active-color` — which color is currently live.
- `proxy/sites/upstream-website.conf` — what nginx currently points at
  (generated from `proxy/sites/upstream-website.conf.default`, kept in sync
  with the marker above by the deploy script). Never hand-edit or commit
  this file; it's overwritten on every deploy.

**Expand/contract migrations are required.** Blue and green share one
database, and both are live against it during the health-gate + drain
window — the outgoing color keeps serving real traffic against a
possibly-already-migrated schema until the switch and drain complete. Any
migration shipped through this pipeline must be backward compatible with the
code still running in the outgoing color: additive columns/tables/indexes
only. A breaking change (rename, drop, type change, new `NOT NULL` on an
existing column) has to be split into an **expand** deploy (add the new
shape, dual read/write) that soaks first, followed by a later **contract**
deploy (remove the old shape) once you've confirmed no color still needs it.
The deploy script does not detect or block breaking migrations — that's a
migration-review call, not an infra one.

**Rollback:** if the idle color never reports docker-healthy or `/up` never
returns 200, the script exits non-zero, nginx is never touched, and the
previously-active color keeps serving. The unhealthy idle container is left
running (not removed) for `docker logs`/`docker inspect` triage; stop it once
diagnosed and re-run the script to retry. If a regression is found *after*
a successful automated switch (health check passed but the release has a
business-logic bug), roll back manually: edit
`proxy/sites/upstream-website.conf` back to the previous color, then
`docker exec nomercy.tv-proxy nginx -t && docker exec nomercy.tv-proxy nginx -s reload`,
and flip `website/.active-color` back to match — the previous color's
container is still there (stopped) if it needs a `docker compose start`.

**One-time cutover:** the very first run of this script on a server still
running the old single `website` container detects it, stops it, and starts
`website-blue` in its place, reusing the same `./data` bind mount and
`vendor`/`node_modules` volumes. That one recreate is a normal few-second
blip (same class as the old deploy), not a zero-downtime switch — plan it
for a low-traffic window. Every deploy after that is a real blue-green
switch.

## Shares

Every folder placed in the `shares/` directory will automatically become available as a subdomain. For example, if you add a folder named `docs` inside `shares/`, it will be accessible at `https://docs.example.com` (replace `example.com` with your actual domain).

This allows you to easily expose additional static sites or resources as separate subdomains by simply adding folders to the `shares/` directory.

## Notes

- Data folders (e.g., `mysql/data/`, `postgres/data/`) are git-ignored for safety and performance.
- Custom Keycloak themes can be placed in `keycloak/themes/<your theme name>/`.
- Configuration files for each service are in their respective folders.
- PHPMyAdmin has a keycloak login flow

## Troubleshooting

- Ensure no other services are running on the same ports.
- Check logs with `docker compose logs <service>` for debugging.
- For permission issues on data folders, ensure Docker has access to your drive.

## Contact

For further information or support, visit NoMercy.tv or contact our support team.

Made with ❤️ by [NoMercy Entertainment](https://nomercy.tv)

---

Feel free to customize this stack for your development needs!
