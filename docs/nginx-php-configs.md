# Nginx & PHP-FPM Configurations

This document outlines the specific configuration tunings, performance limits, and security boundaries applied to the web server (`nginx`) and the PHP processor (`php-fpm`) inside the Moodle Docker container.

## 1. Upload and File Size Limits
Moodle frequently handles large file uploads (like video resources or heavy backup archives). Both Nginx and PHP have been explicitly configured to support up to **512 MB** uploads.

**Nginx (`base/etc/nginx/nginx.conf-template`):**
- `client_max_body_size 512M;`: Allows Nginx to accept HTTP request bodies up to 512 MB.
- `client_body_buffer_size 512k;`: Buffers the first 512 KB of the request body in memory before writing to temporary files, improving performance for small-to-medium files.

**PHP (`base/etc/php82/php.ini-template`):**
- `post_max_size = 512M`: PHP will accept POST data up to 512 MB.
- `upload_max_filesize = 512M`: PHP allows individual uploaded files to be up to 512 MB.

## 2. Timeout and Execution Limits
Processing large files, restoring course backups, or generating heavy reports can take significant time. The timeout limits are raised above standard defaults to prevent premature termination.

**Nginx:**
- `fastcgi_read_timeout 1200;`: Nginx will wait up to **20 minutes** (1200 seconds) for PHP-FPM to return a response before throwing a 504 Gateway Timeout error.

**PHP:**
- `max_execution_time = 1200`: A PHP script is allowed to run for up to **20 minutes** before the engine terminates it.
- `max_input_time = 180`: A script may spend up to 3 minutes parsing incoming request data (like large file uploads).

## 3. Worker and Process Tuning
The environment is tuned to handle high concurrency efficiently.

**Nginx:**
- `worker_processes 1;`: Optimized for containers where CPU limits are strictly controlled.
- `worker_connections 8192;` and `worker_rlimit_nofile 8192;`: Allows the Nginx worker to handle up to 8,192 simultaneous network connections and open files.
- **Proxy Buffers:** Increased to `proxy_buffer_size 128k;` and `proxy_buffers 4 256k;` to handle large response headers and fast buffering of backend responses.

**PHP-FPM (`base/etc/php82/php-fpm.d/moodle.conf`):**
- `pm = static`: The process manager uses a fixed number of child processes rather than spinning them up dynamically.
- `pm.max_children = 3`: Maintains exactly 3 PHP-FPM child processes.
- `memory_limit = 512M`: Each PHP process is allowed to consume up to 512 MB of RAM.
- **Total PHP memory footprint:** 3 children * 512 MB = ~1.5 GB max memory reservation.

## 4. Security Boundaries
- **Remote Code Execution Prevention:** In PHP-FPM, dangerous system functions are explicitly disabled:
  `php_admin_value[disable_functions] = exec,passthru,shell_exec,system`
- **Internal File Protection:** Nginx immediately returns `404 Not Found` and denies access to sensitive internal files and directories such as `composer.json`, `/vendor/`, `readme.txt`, `.lock`, and environment variables.
- **Hidden Files:** Nginx is configured to block access to all dot files (e.g., `.git`, `.env`) with the exception of the `.well-known` directory.
- **Health Checks:** The FPM status and ping endpoints (`/fpm-status` and `/fpm-ping`) are strictly restricted to `127.0.0.1` (localhost).
- **Stream Wrappers (`allow_url_fopen`):** This is explicitly set to `on` via `php_admin_flag[allow_url_fopen] = on` in the FPM config. This is a crucial requirement for plugins like `tool_objectfs`, which rely on the `s3://` stream wrapper to serve remote files seamlessly.
