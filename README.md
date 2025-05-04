# NoMercyStack

A multi-service development stack using Docker Compose, including MySQL, PostgreSQL, Keycloak, phpMyAdmin, pgAdmin, Portainer, Nginx proxy, and a website container. This stack is designed for local development and testing.

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop) (Windows, Mac) or [Docker Engine](https://docs.docker.com/engine/install/) (Linux)
- [Docker Compose](https://docs.docker.com/compose/install/) (usually included with Docker Desktop)

## Setup Instructions

1. **Clone the repository**
   ```sh
   git https://github.com/NoMercy-Entertainment/NoMercyStack.git
   cd NoMercyStack
   ```

1. **Provide SSL Certificates**
    - Place your SSL certificate files (`cert.pem` and `key.pem`) in the `proxy/certificates/` directory.
    - These files are required for the Nginx proxy to serve HTTPS traffic.

1. **Configure Domain and Environment Variables**
   - Copy `.env.example` to `.env` if provided, and adjust values as needed.
   - Some services may require additional configuration in their respective folders.

1. **Access Services**
   - **Website**: https://example.com
   - **phpMyAdmin**: https://phpmyadmin.example.com
   - **pgAdmin**: https://pgadmin.example.com
   - **Keycloak**: https://auth.example.com
   - **Portainer**: https://portainer.example.com
   - **Nginx Proxy**: Handles routing to the above services

   > Replace `example.com` with your actual domain as configured in the `proxy/sites/` conf files.

1. **Start the Stack**
   ```sh
   docker compose up -d
   ```
   This will start all services defined in `docker-compose.yml` and referenced compose files.

## Enabling Keycloak Login for Portainer

To enable Keycloak authentication in Portainer:

1. Log in to Portainer at https://portainer.example.com as an admin.
2. Go to **Settings** > **Authentication**.
3. Select **OAuth** as the authentication method.
4. Enter the following values (replace `example.com` with your actual domain):

   - **Client ID**: `master`
   - **Client Secret**: `*******` (your Keycloak client secret)
   - **Authorization URL**: `https://auth.example.com/realms/master/protocol/openid-connect/auth`
   - **Access Token URL**: `https://auth.example.com/realms/master/protocol/openid-connect/token`
   - **Resource URL**: `https://auth.example.com/realms/master/protocol/openid-connect/userinfo`
   - **Redirect URL**: `https://portainer.example.com`
   - **Logout URL**: `https://auth.example.com/realms/master/protocol/openid-connect/logout?redirect_uri=https://portainer.example.com/#!/auth`
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
