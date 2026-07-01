# ADR 0002: PHP-FPM Pool Sizing and Timeout Configuration

## Date

2026-07-01

## Status

Accepted

## Context

PHP-FPM is the PHP request processor that sits behind Nginx and handles all dynamic Moodle requests. Each PHP-FPM worker processes exactly one request at a time, making `pm.max_children` the primary concurrency limit for the application. The pool manager configuration directly determines how many simultaneous PHP requests the container can serve before returning connection-refused errors (502 Bad Gateway via Nginx).

Moodle generates a wide range of request durations:

- **Fast requests** (100–300ms): cached page hits, static asset redirects, lightweight API calls
- **Medium requests** (300ms–3s): dashboard rendering, forum listing, course pages with activity modules
- **Slow requests** (3–60s): first-time cache misses, gradebook with many students, large report generation
- **Very slow requests** (5–20 min): course backup, course restore, grade export, bulk operations

This bi-modal distribution creates a tension between worker pool sizing (more workers = more concurrency) and memory constraints (each worker uses ~80–133Mi depending on the loaded Moodle page). A pool that is too small causes 502 errors under moderate load; a pool that is too large causes OOM kills when all workers simultaneously consume peak memory.

Available process management modes:

| Mode | Behavior | Pros | Cons |
|------|----------|------|------|
| `static` | Fixed number of workers | Predictable memory, no spawn latency | Wastes memory at idle if set high |
| `dynamic` | Scales between `min_spare` and `max_spare` | Memory-efficient at idle, burst capacity | Requires tuning, spawn latency on scale-up |
| `ondemand` | Spawns workers on demand, kills after idle timeout | Zero memory at idle | High spawn latency under burst load (1–3s per worker) |

The `ondemand` mode is unsuitable for Moodle because PHP-FPM worker spawning takes 1–3 seconds (due to OPcache loading and class initialization), which adds unacceptable latency during traffic bursts.

For timeout alignment, three layers must agree:

1. **PHP `max_execution_time`** — PHP's internal execution timer. Only counts active CPU time, not I/O wait or sleeps.
2. **PHP-FPM `request_terminate_timeout`** — Wall-clock hard kill. Overrides `max_execution_time` if set lower. Terminates the worker process after the specified duration regardless of PHP's internal timer.
3. **Nginx `fastcgi_read_timeout`** — How long Nginx waits for a response from PHP-FPM before returning 504 Gateway Timeout to the client.

If these are misaligned (e.g., PHP-FPM kills at 300s but PHP allows 1200s), the shorter limit silently overrides the longer one, and the discrepancy is confusing for debugging.

## Decision

We will use `pm = dynamic` with `pm.max_children = 25` and align all timeout layers for a dual-profile request pattern.

### Pool Configuration

| Setting | Value | Rationale |
|---------|-------|-----------|
| `pm` | dynamic | Balances memory efficiency at idle with burst capacity. Pre-spawns workers to avoid on-demand spawn latency |
| `pm.max_children` | 25 | Caps concurrency at 25 workers. At ~120Mi/worker under load, peak memory is ~3,000Mi + ~512Mi base overhead = ~3,512Mi, fitting within a 4Gi container with ~512Mi headroom |
| `pm.start_servers` | 8 | Pre-spawns 8 workers immediately on startup, providing instant capacity before any scale-up occurs |
| `pm.min_spare_servers` | 4 | Keeps at least 4 idle workers ready for incoming requests |
| `pm.max_spare_servers` | 16 | Caps idle workers at 16 to reclaim memory during low-traffic periods |
| `pm.process_idle_timeout` | 30s | Terminates idle workers after 30 seconds. Short enough to recover memory, long enough to avoid thrashing |
| `pm.max_requests` | 1000 | Recycles workers after 1,000 requests to prevent memory leaks. At typical leak rates (~1Ki/request), a recycled worker has leaked only ~1Mi before restart |
| `request_terminate_timeout` | 1200 | 20-minute hard wall-clock limit. Matches `max_execution_time` to avoid ambiguity. Required for backup (10–20 min), restore (5–15 min), and grade export (5–10 min) operations |

### Memory Safety Analysis

| Scenario | Workers Active | Memory Estimate | % of 4Gi Limit |
|----------|---------------|-----------------|-----------------|
| Idle | 8 (start) | ~1,152Mi | 28% |
| Moderate load | 16 (max_spare) | ~2,112Mi | 52% |
| Full load | 25 (max_children) | ~3,512Mi | 86% |
| Worst case | 25 × 133Mi | ~3,837Mi | 94% |

With PHP `memory_limit = 512M` per worker, the theoretical maximum is 25 × 512Mi = 12,800Mi, far exceeding any reasonable container limit. The `pm.max_children = 25` setting provides a 512Mi safety margin at realistic per-worker memory usage (~120Mi average under load). If per-worker memory grows beyond ~150Mi (e.g., due to a memory leak or complex page), the container may approach OOM. This risk is mitigated by `pm.max_requests = 1000` worker recycling and Kubernetes OOM eviction.

### Timeout Configuration

| Setting | Value | Scope | Rationale |
|---------|-------|-------|-----------|
| `max_execution_time` | 1200s | PHP ini | 20-minute limit for admin operations |
| `request_terminate_timeout` | 1200s | PHP-FPM | Matches `max_execution_time` — both layers agree on 20 minutes |
| `fastcgi_read_timeout` (general) | 300s | Nginx | Student pages should complete within 5 minutes. Prevents long-queued requests from holding workers |
| `fastcgi_read_timeout` (admin) | 1200s | Nginx | Matches PHP-FPM for backup, restore, grade export. See ADR 0003 |

The dual timeout strategy (300s general, 1200s admin) is implemented via separate Nginx location blocks (see ADR 0003).

### Sizing for Different Memory Limits

For deployments with different memory constraints, adjust `pm.max_children` accordingly:

| Container Memory | `pm.max_children` | `pm.max_spare_servers` | `pm.start_servers` | Headroom |
|------------------|-------------------|------------------------|--------------------|----------|
| 2Gi | 12 | 8 | 4 | ~400Mi |
| 4Gi | 25 | 16 | 8 | ~512Mi |
| 6Gi | 40 | 24 | 10 | ~750Mi |
| 8Gi | 55 | 32 | 12 | ~780Mi |

Memory estimates assume ~120Mi per active worker + 512Mi base overhead (nginx, OPcache, system).

## Consequences

### Positive

- **Predictable concurrency** — `pm.max_children = 25` provides a clear ceiling of 25 simultaneous PHP requests per container. Horizontal scaling (more replicas) is the correct response to increased load.
- **Memory safety** — At 25 workers, the container stays within 4Gi with ~512Mi headroom, avoiding OOM kills under normal load patterns.
- **Worker recycling prevents leaks** — `pm.max_requests = 1000` ensures no worker accumulates more than ~1Mi of leaked memory before recycling.
- **Timeout alignment** — `max_execution_time` and `request_terminate_timeout` both set to 1200s eliminates ambiguity about which layer terminates a long-running request.
- **Fast failure mode** — When the pool is exhausted, Nginx returns 502 immediately (connection refused by PHP-FPM). This is a healthier failure mode than allowing requests to queue for minutes before timing out.

### Negative

- **Pool exhaustion under load** — With 25 workers, the container can serve at most 25 concurrent PHP requests. Any requests beyond this receive 502 errors until workers free up. This is a capacity constraint, not a bug.
- **OOM risk at sustained high concurrency** — If all 25 workers simultaneously handle memory-intensive pages (e.g., gradebook with 10,000+ students), per-worker memory can spike to 150–200Mi, approaching the 4Gi limit. Monitor `max children reached` in PHP-FPM status to detect pool exhaustion.
- **Dynamic scaling latency** — When traffic bursts from idle to capacity, PHP-FPM must spawn workers (1–3s each). The `pm.start_servers = 8` mitigates this by pre-warming 8 workers.

### Neutral

- **Memory per worker varies by page** — Simple cached pages use ~80Mi; complex pages with grade rendering, report generation, or backup operations can spike to 150–200Mi. The ~120Mi average is a reasonable planning figure.
- **The 502 error rate is directly proportional to oversubscription** — If concurrent requests exceed `pm.max_children × replica_count`, 502 errors increase proportionally. Horizontal scaling (increasing replicas) is the correct remediation.