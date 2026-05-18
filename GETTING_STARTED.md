# 🚀 Getting Started Guide

Welcome to the developer manual for the **Adorsys GIS Moodle Nginx Container**! This document provides all the necessary instructions to set up your local workspace, configure dynamic runtime parameters, execute administrative and commands.

---

## 📋 Table of Contents
1. [Prerequisites](#-prerequisites)
2. [Quickstart Workspace Onboarding](#-quickstart-workspace-onboarding)
3. [Comprehensive Environment Variable Reference](#-comprehensive-environment-variable-reference)
4. [Administrative & Development Operations](#-administrative--development-operations)
5. [Troubleshooting](#-troubleshooting)

---

## 🛠️ Prerequisites
Before starting, ensure that your system has the following dependencies:
* **Docker Engine** (v20.10.0+)
* **Docker Compose V2**
* **Git**

---

## ⚡ Quickstart Workspace Onboarding

Setting up your Moodle instance locally is simple:

### Step 1: Clone the Repository
```bash
git clone https://github.com/ADORSYS-GIS/moodle-container.git
cd moodle-container
```

### Step 2: Boot up the Services
Run docker-compose to build the localized image and spin up the complete isolated stack in the background:
```bash
docker compose up -d
```

### Step 3: Access your Moodle Site
* The stack leverages decoupled healthchecks. The initial installation requires **30 to 60 seconds** to bootstrap.
* Open your browser and navigate to: **`http://localhost:8080`**
* **Default Admin Credentials**:
  * **Username**: `admin`
  * **Password**: `MoodleAdmin123!`

---

## ⚙️ Comprehensive Environment Variable Reference

This container dynamically builds the Moodle `config.php` file at runtime based on the environment variables defined inside your `compose.yaml` (or passed to your Kubernetes pods).

### Core Application Settings

| Variable Name | Default Value | Description |
| :--- | :--- | :--- |
| `SITE_URL` | `http://localhost:8080` | The public base URL of the Moodle site. |
| `MOODLE_LANGUAGE` | `en` | Moodle default language code. |
| `MOODLE_SITENAME` | `"Local Development Moodle"` | Title displayed on the dashboard home. |
| `MOODLE_USERNAME` | `admin` | Default console administrator username. |
| `MOODLE_PASSWORD` | `MoodleAdmin123!` | Secure admin password (do not use simple passwords in production). |
| `SSLPROXY` | `false` | Set to `true` if your container resides behind an SSL terminating reverse proxy. |

### Database Connections (MariaDB / MySQL / PostgreSQL)

| Variable Name | Default Value | Description |
| :--- | :--- | :--- |
| `DB_TYPE` | `mariadb` | Database engine type (`mariadb`, `mysqli`, or `pgsql`). |
| `DB_HOST` | `mariadb` | Host container name or external server IP. |
| `DB_HOST_PORT` | `3306` | Connection port. Use `3306` for MySQL/MariaDB or `5432` for PostgreSQL. |
| `DB_NAME` | `moodle` | Target database schema name. |
| `DB_USER` | `moodle_user` | Authorized database connection username. |
| `DB_PASS` | `moodle_pass` | Secure connection password. |
| `DB_PREFIX` | `mdl_` | Prefix prepended to all tables inside the schema database. |

### Redis Distributed Cache Configurations

All session and caching states are securely authenticated.

| Variable Name | Default Value | Description |
| :--- | :--- | :--- |
| `REDIS_SESSION_ID_HOST` | `redis` | Redis session container hostname. |
| `REDIS_SESSION_ID_PORT` | `6379` | Connection port. |
| `REDIS_SESSION_ID_AUTH_STRING`| `moodledevpass` | Secure password utilized for connecting to session cache. |
| `REDIS_SESSION_IP_AND_PORT` | `redis:6379` | IP and Port mapping configuration. |
| `REDIS_SESSION_AUTH_STRING` | `moodledevpass` | Shared Redis session authentication key. |
| `REDIS_APP_IP_AND_PORT` | `redis:6379` | Host mapping for Moodle's Application Cache Store. |
| `REDIS_APP_AUTH_STRING` | `moodledevpass` | Auth password for Application Cache Store. |
| `REDIS_LOCK_HOST_AND_PORT` | `redis:6379` | Host mapping for Moodle's distributed Lock Factory. |
| `REDIS_LOCK_AUTH_STRING` | `moodledevpass` | Auth password for distributed Lock Factory. |

---

## 🛠️ Administrative & Development Operations

### Running Moodle CLI commands
You can run standard Moodle CLI tools directly inside the running `moodle` container as the unprivileged `www` user:
```bash
# Check the status of your Moodle installation
docker compose exec -u www moodle php82 /moodleroot/moodle/admin/cli/status.php

# Purge all local application caches
docker compose exec -u www moodle php82 /moodleroot/moodle/admin/cli/purge_caches.php
```

---

## 🔍 Troubleshooting

### My container is stuck in restarting loop / failing to start
Check the startup logs using Docker Compose. The `moodle-init` container must complete successfully before the main `moodle` webserver starts:
```bash
# View installer bootstrap logs
docker compose logs -f moodle-init

# View live web server logs
docker compose logs -f moodle
```

### Complete Environment Reset
If you corrupt your database, files, or cache registries and need to spin up a completely fresh workspace:
```bash
# Terminate containers and delete all data volumes
docker compose down -v

# Rebuild and start fresh
docker compose up -d --build
```
