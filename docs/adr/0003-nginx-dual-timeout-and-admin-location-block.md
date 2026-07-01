# ADR 0003: Nginx Dual Timeout and Admin Location Block

## Date

2026-07-01

## Status

Accepted

## Context

Moodle handles two fundamentally different categories of PHP requests:

1. **Student-facing pages** — course views, forum posts, dashboard, profile pages. These should complete in 100–500ms under normal conditions. When the PHP-FPM pool is saturated, queued student requests hold worker slots for the full duration of the timeout, preventing new requests from being served.

2. **Admin operations** — course backup (`/backup/`), course restore, grade export, large report generation (`/report/`), plugin management (`/admin/`). These operations routinely take 5–20 minutes and legitimately need extended timeouts.

A single timeout value must satisfy one of two constraints:
- **Short timeout (e.g., 300s)** — Protects pool capacity by quickly freeing workers from queued requests. But admin operations exceeding 300s are killed mid-execution, losing work and potentially corrupting backup files.
- **Long timeout (e.g., 1200s)** — Allows admin operations to complete. But a student request queued behind a long-running request holds a worker for up to 20 minutes, exhausting the pool faster under load.

Nginx provides location-based routing with per-location `fastcgi_read_timeout`, enabling a dual timeout strategy where different URL patterns receive different timeout ceilings.

The challenge is selecting URL patterns that correctly identify long-running operations without also matching routine student pages. Initial attempts with broad patterns like `/course/` and `/grade/` caught student-facing pages (e.g., `/course/view.php`, `/grade/report/grader/index.php`), applying the long timeout to pages that should be fast and, in some configurations, breaking FastCGI parameter resolution for those URLs.

For the admin location block regex, the following patterns were considered:

| Pattern | Matches | Issue |
|---------|----------|-------|
| `^/(admin\|backup\|course\|grade\|report)/` | All admin paths + `/course/` + `/grade/` | Too broad — catches student pages |
| `^/(admin\|backup\|report)/` | Admin, backup, reports only | Excludes `/grade/export/` and `/course/restore/` which are long-running |
| `^/(admin\|backup\|report)/.*\.php` | Same as above but only PHP files | More precise — excludes static assets in admin paths |
| Individual path enumeration | `/admin/`, `/backup/`, `/grade/export/`, etc. | Escapes URL pattern easily, high maintenance |

## Decision

We will use a dual timeout strategy with a precisely-scoped admin location block matching `/admin/`, `/backup/`, and `/report/` paths, and a shorter general timeout for all other PHP requests.

### General PHP Location Block (300s timeout)

```nginx
location ~ [^/]\.php(/|$) {
    fastcgi_split_path_info ^(.+\.php)(/.+)$;
    include fastcgi_params;
    fastcgi_index index.php;
    fastcgi_pass unix:/var/run/php84-fpm-moodle.sock;
    fastcgi_read_timeout 300;
    fastcgi_buffers 16 16k;
    fastcgi_buffer_size 32k;
}
```

This is the default location for all PHP requests not matched by the admin block. The 300-second (5-minute) timeout is long enough for any student-facing page under load, while being short enough to prevent pool exhaustion from indefinitely queued requests.

### Admin Location Block (1200s timeout)

```nginx
location ~ ^/(admin|backup|report)/.*\.php(/|$) {
    include fastcgi_params;
    fastcgi_index index.php;
    fastcgi_pass unix:/var/run/php84-fpm-moodle.sock;
    fastcgi_read_timeout 1200;
    fastcgi_buffers 16 16k;
    fastcgi_buffer_size 32k;
}
```

The 1200-second (20-minute) timeout matches `request_terminate_timeout` in PHP-FPM and `max_execution_time` in `php.ini`, creating a consistent 20-minute ceiling across all layers for admin operations.

### Pattern Scope Rationale

The regex `^/(admin|backup|report)/.*\.php` was chosen to match only paths that are genuinely long-running:

| Path | Included | Rationale |
|------|----------|-----------|
| `/admin/*.php` | Yes | Admin settings, plugin management, bulk operations, site configuration |
| `/backup/*.php` | Yes | Course backup and restore operations (10–20 min) |
| `/report/*.php` | Yes | Reports, analytics, log exports (5–15 min) |
| `/course/*.php` | **No** | Student course views — accessed thousands of times, must be fast |
| `/grade/*.php` | **No** | Teacher gradebook — only `/grade/export/` is genuinely long-running |
| `/enrol/*.php` | **No** | Enrollment sync — rare and not consistently long |
| `/index.php?q=...` | **No** | Moodle's default router — cannot distinguish admin from student via query parameters |

Paths excluded from the long timeout still receive the 300s general timeout, which is sufficient for the vast majority of Moodle pages. The few long-running operations that slip through (e.g., grade export via `/grade/export/`) will be terminated at the 300s mark. This can be addressed by adding specific patterns to the admin block if issues arise.

### FastCGI Buffer Configuration

```nginx
fastcgi_buffers 16 16k;
fastcgi_buffer_size 32k;
```

Total buffer: 16 × 16KiB + 32KiB = 288KiB per request. These values ensure that typical Moodle responses (100–300KiB for HTML pages) are buffered entirely in memory rather than written to temporary files on disk. Both location blocks share these buffer settings.

### Client Timeouts

```nginx
client_body_timeout 30s;
client_header_timeout 30s;
send_timeout 30s;
```

Applied globally. These protect against slowloris-style attacks and clean up stuck client connections. At 30 seconds, legitimate clients on slow connections (mobile, satellite) still have generous time to send headers and request bodies.

## Consequences

### Positive

- **Pool capacity protection** — Student-facing requests time out after 300s, freeing PHP-FPM workers for new requests. Under load, this prevents workers from being held for 20 minutes by a single queued request.
- **Admin operations complete** — Backup, restore, and report operations have the 20-minute ceiling they need. The timeout stack is consistent: `fastcgi_read_timeout` (1200s) = `request_terminate_timeout` (1200s) = `max_execution_time` (1200s).
- **No URL resolution breakage** — The narrowed regex excludes `/course/` and `/grade/` paths, preventing the 404 regression caused by overly broad pattern matching in earlier configurations.
- **FastCGI buffers prevent disk spill** — Typical Moodle responses (100–300KiB) are buffered entirely in memory, reducing I/O latency.

### Negative

- **Some long-running paths miss the admin block** — `/grade/export/`, `/course/restore/`, and operations routed through `/index.php` with query parameters receive the 300s general timeout. These are relatively rare paths; they can be added to the admin regex if issues arise in production.
- **Moodle's index.php routing** — Many operations are routed through `/index.php?page=...` or `/index.php?q=...` without a distinct path prefix. These will always match the general 300s location, not the admin 1200s location. There is no reliable way to distinguish admin from student requests in URL query parameters.
- **Configuration complexity** — Maintaining two location blocks with different timeouts requires understanding which Moodle paths are long-running. Adding new admin paths requires updating the nginx regex.

### Neutral

- **The 300s timeout is a safety net, not the primary circuit breaker** — Most student requests complete in <1 second or receive an immediate 502 when the pool is full. The 300s timeout only affects requests that PHP-FPM accepts but takes longer than 5 minutes to process.
- **Nginx returns 504 Gateway Timeout when `fastcgi_read_timeout` is exceeded** — This provides a distinct signal (different from 502 Bad Gateway) that a request timed out, useful for monitoring and alerting.
- **The regex uses `.*\.php` rather than a more permissive pattern** — This ensures only actual PHP script requests receive the extended timeout, not static assets or non-PHP resources under admin paths.

## References

- Nginx `fastcgi_read_timeout` documentation: [nginx.org/en/docs/http/ngx_http_fastcgi_module.html#fastcgi_read_timeout](https://nginx.org/en/docs/http/ngx_http_fastcgi_module.html#fastcgi_read_timeout)
- PHP-FPM `request_terminate_timeout` documentation: [php.net/manual/en/install.fpm.configuration.php](https://www.php.net/manual/en/install.fpm.configuration.php)
- See also: ADR 0002 — PHP-FPM Pool Sizing and Timeout Configuration