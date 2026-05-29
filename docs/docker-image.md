# Moodle Docker Image Documentation

This document explains the construction and design of the custom Moodle Docker image used in this project.

## Base Image
The image is built on top of **Alpine Linux 3.19.1** (`alpine:3.19.1`). Alpine is chosen for its extremely small footprint, security, and efficiency.

## Installed Packages
The image installs the core components required to run Moodle efficiently:
- **Web Server & PHP**: `nginx`, `php82-fpm`, `php82-opcache`
- **Database Connectivity**: `php82-mysqli`, `php82-redis`
- **Moodle Required PHP Extensions**: `iconv`, `mbstring`, `curl`, `openssl`, `tokenizer`, `intl`, `soap`, `xmlreader`, `fileinfo`, `sodium`, `exif`, `ctype`, `zip`, `xmlwriter`, `gd`, `simplexml`, `dom`, `xml`, `pecl-igbinary`, `phar`, `posix`, `pecl-zstd`
- **System Utilities**: `tar`, `curl`, `gzip`, `envsubst`, `tzdata`, `sudo`, `vim`, `icu-data-full`

### Optional Support Packages
- `aspell`: For spell checking in Moodle text editors.
- `graphviz`: For rendering dynamic charts and graphs.
- `ghostscript` & `poppler-utils`: For PDF processing and annotations (often used in assignment grading).
- `clamav`: For antivirus scanning on uploaded files.
- `python3`: For machine learning backend processing.

## Security & Permissions
- **Non-Root Execution**: The web server and PHP processes run under a dedicated unprivileged user (`www`).
- **File Ownership**: The core source files are owned by `root:root` where possible, while web-writable directories (like `/moodleroot/moodledata` and `/var/lib/nginx`) are assigned to `www:www`.
- **Initialization**: Entrypoint scripts (`/opt/*.sh`) are owned by `root:root` with strict execution permissions (`0755`) to prevent unauthorized tampering.

## Core Moodle Directories
The `Dockerfile` defines standard paths via `ARG` and `ENV` variables for consistency:
- **`MOODLE_ROOT_PATH`**: `/moodleroot`
- **`MOODLE_DATAROOT_PATH`**: `/moodleroot/moodledata`
- **`MOODLE_PATH`**: `/moodleroot/moodle`

## Initialization Process
When the container starts, the `ENTRYPOINT` is defined as `/opt/entrypoint.sh`. This script handles:
1. Environment variable substitution (via `envsubst`).
2. Finalizing folder permissions.
3. Bootstrapping either the Moodle installation process or starting the `nginx` and `php-fpm` daemon processes depending on the command invoked.
