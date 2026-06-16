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

**Nginx (`base/etc/nginx/nginx.conf-template`):**
- `worker_processes auto;`: Nginx automatically spawns one worker per available CPU core. This is essential in orchestrated environments (e.g. GKE Autopilot) where multiple vCPUs may be assigned to the pod at runtime.
- `worker_connections 8192;` and `worker_rlimit_nofile 8192;`: Allows each Nginx worker to handle up to 8,192 simultaneous network connections and open files.
- `keepalive_timeout 15;`: Keeps idle client connections open for 15 seconds. Upstream load balancers (e.g. GCE/GKE) hold connections open between requests; a value too low (e.g. `3s`) causes premature teardown and results in sporadic 502 errors.
- **Proxy Buffers:** Increased to `proxy_buffer_size 128k;` and `proxy_buffers 4 256k;` to handle large response headers and fast buffering of backend responses.

**PHP-FPM (`base/etc/php82/php-fpm.d/moodle.conf`):**
- `pm = dynamic`: Workers are created on demand and reaped when idle, making far more efficient use of memory than the previous `static` mode.
- `pm.max_children = 8`: Up to 8 concurrent PHP-FPM workers. At ~120 MB average RSS per worker, this stays within ~960 MB — well within a 2 Gi pod memory limit with headroom for Nginx and system overhead.
- `pm.start_servers = 3` / `pm.min_spare_servers = 2` / `pm.max_spare_servers = 4`: Balances cold-start latency against memory usage. The pool pre-warms 3 workers and keeps between 2 and 4 idle workers available at all times.
- `pm.max_requests = 500`: Each worker is recycled after handling 500 requests, preventing long-lived memory leaks from accumulating in PHP extensions.
- `memory_limit = 512M`: Each PHP process is allowed to consume up to 512 MB of RAM.

## 4. Security Boundaries
- **Remote Code Execution Prevention:** In PHP-FPM, dangerous system functions are explicitly disabled:
  `php_admin_value[disable_functions] = exec,passthru,shell_exec,system`
- **Internal File Protection:** Nginx immediately returns `404 Not Found` and denies access to sensitive internal files and directories such as `composer.json`, `/vendor/`, `readme.txt`, `.lock`, and environment variables.
- **Hidden Files:** Nginx is configured to block access to all dot files (e.g., `.git`, `.env`) with the exception of the `.well-known` directory.
- **Health Checks:** The FPM status and ping endpoints (`/fpm-status` and `/fpm-ping`) are strictly restricted to `127.0.0.1` (localhost).
- **Stream Wrappers (`allow_url_fopen`):** This is explicitly set to `on` via `php_admin_flag[allow_url_fopen] = on` in the FPM config. This is a crucial requirement for plugins like `tool_objectfs`, which rely on the `s3://` stream wrapper to serve remote files seamlessly.

## 5. OPcache Configuration

OPcache pre-compiles PHP scripts into bytecode and stores them in shared memory, eliminating the overhead of parsing and compiling on every request. The following settings are applied in `base/etc/php82/php.ini-template`:

| Setting | Value | Reason |
|---|---|---|
| `opcache.enable` | `1` | Enable OPcache for web requests |
| `opcache.enable_cli` | `1` | Enable OPcache for CLI (used by Moodle cron and upgrade scripts) |
| `opcache.memory_consumption` | `256` | Moodle 5.0+ has thousands of PHP files; 128 MB (the PHP default) causes frequent cache evictions under load |
| `opcache.interned_strings_buffer` | `16` | Larger interned string pool reduces hash collisions in string-heavy frameworks |
| `opcache.max_accelerated_files` | `20000` | Moodle 5.0+ ships approximately 18,000 PHP files; the default of 10,000 is too low |
| `opcache.max_wasted_percentage` | `5` | Trigger a cache restart when more than 5% of memory is fragmented |
| `opcache.validate_timestamps` | `0` | **Immutable container image** — PHP files never change at runtime, so `stat()` syscalls on every request are pure overhead. Disable completely. |
| `opcache.revalidate_freq` | `0` | Has no effect when `validate_timestamps=0`; set explicitly to avoid ambiguity |
| `opcache.save_comments` | `1` | **Critical for Moodle.** Setting this to `0` strips PHPDoc annotations from cached bytecode. Moodle's plugin registry, capability system, and dependency injection container read annotations at runtime — stripping them causes silent failures in core functionality and third-party plugins. |
| `opcache.fast_shutdown` | `1` | Defer memory cleanup to the OS on shutdown, reducing per-request teardown time |
| `opcache.jit` | `1235` | Enable the tracing JIT compiler, which provides the best throughput improvement for CPU-bound workloads |
| `opcache.jit_buffer_size` | `100M` | Memory allocated to the JIT native code cache |

> [!IMPORTANT]
> `opcache.save_comments = 1` is a **hard requirement** for Moodle. Never set it to `0` in production. The consequence is not an immediate crash but subtle, hard-to-diagnose runtime breakage in plugin loading and the caching subsystem.

> [!NOTE]
> Because `opcache.validate_timestamps = 0`, OPcache will **not** detect any PHP file changes after the cache is primed. In this containerised deployment this is intentional — PHP files are baked into the image at build time and are never modified at runtime. If you are running a development setup where you mount local PHP files, set `validate_timestamps = 1` and `revalidate_freq = 0` to pick up changes immediately.
