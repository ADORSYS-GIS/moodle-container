# 🚀 Optimized Moodle Nginx Container

[![Build and Publish Moodle Nginx Image](https://github.com/ADORSYS-GIS/moodle-container/actions/workflows/publish-image.yml/badge.svg)](https://github.com/ADORSYS-GIS/moodle-container/actions/workflows/publish-image.yml)
[![Docker Image](https://img.shields.io/badge/docker-alpine-blue.svg)](https://hub.docker.com/_/alpine)
[![PHP](https://img.shields.io/badge/php-8.2-777bb4.svg)](https://www.php.net/)
[![Nginx](https://img.shields.io/badge/nginx-1.24-009639.svg)](https://nginx.org/)

A production-grade, highly optimized Docker setup for **Moodle** powered by **Alpine Linux**, **Nginx**, and **PHP 8.2 (FPM)**. This image is carefully engineered for cloud-native architectures, featuring advanced caching (Redis), built-in security, ClamAV antivirus, opcache precompilation, and manual CI/CD workflows for seamless deployments.

---

## ✨ Features

- **⚡ High Performance**: Nginx + PHP-FPM 8.2 communication over optimized Unix sockets.
- **⚙️ Memory Optimized**: Built on Alpine Linux to ensure the smallest possible footprint.
- **🔋 Production Caching**: Built-in support for Redis session handlers, application cache, and lock factory.
- **🛡️ Built-in Security**: ClamAV Antivirus integration for scanning uploads out-of-the-box.
- **🔧 Moosh Integration**: Embedded support for [Moosh](https://moosh-project.org/) (Moodle Shell) for automated provisioning and administration.
- **🤖 Manual CI/CD**: Ready-to-go GitHub Actions workflow for building and publishing multi-platform images to GHCR.

---

## 📂 Project Structure

```text
moodle-container/
├── .github/
│   └── workflows/
│       ├── lint-codebase.yml     # Automated QA linting workflow
│       ├── publish-image.yml     # Manual CI/CD workflow targeting GHCR
│       └── test-build.yml        # Dry-run compilation test on push/PR
├── base/                         # Base configuration templates
│   ├── etc/
│   │   ├── nginx/
│   │   │   ├── fastcgi_params
│   │   │   ├── mime.types
│   │   │   └── nginx.conf-template
│   │   └── php82/
│   │       ├── php-fpm.d/
│   │       │   └── moodle.conf   # Optimized PHP-FPM Moodle pool
│   │       ├── php-fpm.conf
│   │       └── php.ini-template  # Standardized PHP configuration
│   ├── moodle/
│   │   └── local/
│   │       └── defaults.php
│   └── opt/
│       ├── entrypoint.sh         # Custom Alpine entrypoint
│       └── setup_moodle.sh       # Comprehensive Moodle auto-installer/upgrader
├── .gitignore
├── compose.yaml                  # Local development compose setup (MariaDB + Redis)
├── config.php.template           # Production-ready config.php template
├── Dockerfile                    # Multi-stage Alpine 3.19 build file
└── README.md
```

---

## 🚀 Quick Start with Docker Compose

The repository includes a ready-to-use [compose.yaml](compose.yaml) file for spin-up in a development environment. This deploys Moodle, MariaDB, and Redis with fully optimized configurations:

### 1. Build and Start the Services
Simply run:
```bash
docker compose up -d --build
```

This will:
- Build the optimized Nginx + PHP 8.2 Moodle container from your local directory.
- Start a Redis 8.0 cache backend.
- Start a MariaDB database backend.
- Automatically link the components and bootstrap Moodle.

### 2. Access Moodle
Once initialized, access the application:
- **URL**: `http://localhost:8080`
- **Username**: `admin`
- **Password**: `MoodleAdmin123!`

---

## ⚙️ Configuration & Environment Variables

| Variable | Description | Default |
| :--- | :--- | :--- |
| `SITE_URL` | The public base URL of the Moodle site | *(Required)* |
| `DB_TYPE` | Database type (`pgsql`, `mysqli`, `mariadb`) | `pgsql` |
| `DB_HOST` | Database host | `moodle-db` |
| `DB_HOST_PORT` | Database port | `5432` (or `3306`) |
| `DB_NAME` | Database name | `moodle` |
| `DB_USER` | Database username | `moodle_user` |
| `DB_PASS` | Database password | *(Required)* |
| `DB_PREFIX` | Prefix for database tables | `mdl_` |
| `MOODLE_SITENAME` | The name of your Moodle site | `Moodle LMS` |
| `MOODLE_USERNAME`| Admin console username | `admin` |
| `MOODLE_PASSWORD`| Admin console password | *(Required)* |
| `MOODLE_EMAIL`   | Admin email address | *(Required)* |
| `SSLPROXY` | Set to `true` if behind a reverse proxy handling SSL | `false` |
| `NOEMAIL_EVER` | Prevent sending emails under all circumstances | `false` |
| `ENABLE_FRESHCLAM` | Periodically update the ClamAV signature database | `no` |

### ⚡ Caching Configuration (Redis)

| Variable | Description |
| :--- | :--- |
| `REDIS_SESSION_ID_HOST` | Hostname for Redis session caching |
| `REDIS_SESSION_ID_PORT` | Port for Redis session caching (default `6379`) |
| `REDIS_SESSION_ID_AUTH_STRING` | Redis authentication password |
| `REDIS_APP_IP_AND_PORT` | IP & Port for Application Cache Store |
| `REDIS_APP_AUTH_STRING` | Application Cache Redis Auth Password |

---

## 🛠️ GitHub Actions CI/CD (GHCR)

The repository includes a professional GitHub Actions workflow that builds multi-architecture (`linux/amd64`, `linux/arm64`) Docker images using QEMU and pushes them directly to the **GitHub Container Registry (GHCR)**.

### How to trigger manually:
1. Navigate to the **Actions** tab on your GitHub repository.
2. Select the **Build and Publish Moodle Nginx Image** workflow on the left side.
3. Click the **Run workflow** dropdown on the right.
4. Input your desired **Moodle Version/Tag** (e.g., `5.0.0`) and choose whether to push it as the `latest` tag.
5. Click **Run workflow**.

---

## 🔒 Security Hardening

- **Unprivileged execution**: The Nginx and PHP-FPM processes run under a limited user (`www`).
- **PHP-FPM Restrictions**: Insecure PHP functions are explicitly disabled in `base/etc/php82/php-fpm.d/moodle.conf`.
- **System Isolation**: File execution is restricted, and read permissions are hardened globally.
- **Antivirus Scanning**: Built-in support for `clamav` and automatic executable protection mechanisms prevents unauthorized execution of web shell uploads.

---

## 📄 License

This project is licensed under the Apache License 2.0. See [LICENSE](LICENSE) for more details.