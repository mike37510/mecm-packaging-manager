# Docker — MECM Packaging Manager

## Persistence (important)

The **whole project directory** is bind-mounted:

```yaml
volumes:
  - .:/var/www/html
```

Code edited in the container (or Cursor) is stored on the **host** and survives rebuilds.
Runtime data stays under `HOST_DATA_ROOT` (`./docker-data` by default).

One-time migration if you still run an old compose (data-only mounts):

```bash
cd /home/docker/mecm-packaging-manager
bash docker-data/backups/_persist_sync/APPLY_ON_HOST.sh
```

## Requirements
- Docker Engine + Compose v2
- Free port: `8080` (HTTP)

## Quick start

```bash
cp .env.example .env
# optional: set ADMIN_* / HOST_DATA_ROOT before production

sudo php bin/fix-permissions.php --migrate
docker compose up -d --build
```

App: http://localhost:8080  
JSON storage and the admin account are initialized at startup (`RUN_MIGRATIONS=1`).

**Admin account (first install)**:

- Email: `mickael.rousseau@univ-tours.fr` (override with `ADMIN_EMAIL`)
- Password: bcrypt hash in `config/auth.php` / `ADMIN_PASSWORD_HASH`

Override with `ADMIN_EMAIL` / `ADMIN_PASSWORD_HASH` (`.env` or `config/auth.php`). After that, change the password under **Users**.

**Default lab VM**: `192.168.1.1` / `admin` / `admin` (SSH port 22)  
**Default MECM**: site `SIT`, server `sccm.domain.local`, DP `dp.domain.local`

Clean reinstall (bind data):

```bash
docker compose down
# optional: rm -rf "${HOST_DATA_ROOT:-./docker-data}"/*
docker compose up -d --build
```

## What the image includes
- PHP **8.2** + Apache (`DocumentRoot` = `public/`)
- Extensions: `zip`, `gd` (freetype/jpeg/webp), `intl`, `mbstring`, `opcache`, `curl`
- **ImageMagick** + **librsvg2** (`rsvg-convert`) for MECM 512×512 icons — Bookworm ships IM6 (`convert`); the image also exposes `magick` (symlink) for the install check and icon normalizer
- Entrypoint that sets `www-data` ownership on:
  - `storage/data` (JSON)
  - `storage/packages`
  - `storage/exports`
  - `storage/backups`
  - `storage/logs`
  - `storage/cache`
  - `storage/bios` (HP / Dell / Lenovo BIOS config versions)
  - `public/assets/uploads/icons`

## Storage (bind mounts)
Data lives on the **host**, not in named Docker volumes.

| Container | Host (default) |
|-----------|----------------|
| `/var/www/html/storage/data` | `./docker-data/data` |
| `/var/www/html/storage/packages` | `./docker-data/packages` |
| `/var/www/html/storage/exports` | `./docker-data/exports` |
| `/var/www/html/storage/backups` | `./docker-data/backups` |
| `/var/www/html/storage/logs` | `./docker-data/logs` |
| `/var/www/html/storage/cache` | `./docker-data/cache` |
| `/var/www/html/storage/bios` | `./docker-data/bios` |
| `/var/www/html/public/assets/uploads/icons` | `./docker-data/icons` |

Change the location in `.env`:

```bash
HOST_DATA_ROOT=/var/lib/sccm-packaging-factory
```

Then create `data`, `packages`, `exports`, `backups`, `logs`, `cache`, `bios`, `icons` under that path
(or run: `sudo php bin/fix-permissions.php --data-root=/var/lib/sccm-packaging-factory`).

## Renewal mailing (cron)

On container start, a daily cron is installed (`0 8 * * *`, `TZ=Europe/Paris`) to run `bin/send-renewal-mails.php` when SMTP is configured under **Settings → Mailing**. Log: `storage/logs/renewal-mails.log`.

## Permissions (`bin/fix-permissions.php` + Settings)
On the host, before the first `compose up` (or after binding a new path):

```bash
sudo php bin/fix-permissions.php --migrate
# or with another bind root:
sudo php bin/fix-permissions.php --data-root=/var/lib/sccm-packaging-factory --migrate
```

The script creates folders, applies `www-data:www-data` + `2775`/`0664`, and locks down `config/*.local.php` (`640`).  
Partial equivalent in the admin UI: **Settings** → **Permissions check** (`/settings#permissions`). That page only checks `storage/` and uploads, not host `docker-data/`.

## SSH into the container
Enabled by default (`ENABLE_SSH=1`).

| | |
|--|--|
| Host port | `2222` (`SSH_PUBLISH_PORT`) |
| User | `admin` (`SSH_USER`) |
| Password | value of `SSH_PASSWORD` (default set in `docker-compose.yml`) |
| Root | same password (lab) |

```bash
ssh -p 2222 admin@localhost
# password: see SSH_PASSWORD
```

Disable: `ENABLE_SSH=0` in `.env`.

## Admin auth
Credentials via `ADMIN_EMAIL` / `ADMIN_PASSWORD_HASH` (or `config/auth.php`).  
The password is always a bcrypt hash (never plaintext in files).

## Useful commands

```bash
# logs
docker compose logs -f app

# shell
docker compose exec app bash

# JSON init / admin seed
docker compose exec app php bin/migrate.php

# project backup
docker compose exec app bash bin/backup-project.sh

# rebuild
docker compose up -d --build
```

## Security
- Change `ADMIN_PASSWORD_HASH` / `SSH_PASSWORD` before production
- Put a TLS reverse proxy (NPM / Traefik / Caddy) in front of port 8080
