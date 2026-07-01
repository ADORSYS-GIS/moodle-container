# ADR 0001: Startup Cache Pre-Warm and Cache Purge Policy

## Date

2026-07-01

## Status

Accepted

## Context

Moodle's Dependency Injection (DI) container compiles a `CompiledContainer.php` file into the local cache directory (`$CFG->localcachedir`) on first request. This file is critical — every PHP request `require`s it to bootstrap the Moodle service container. When it is missing, Moodle must regenerate it, which takes 2–5 seconds of CPU-intensive work involving class scanning, dependency resolution, and file compilation.

In multi-replica deployments, this creates a race condition. If one replica purges its local cache (e.g., during plugin initialization), it may invalidate cache indicators that other replicas depend on, causing them to attempt simultaneous regeneration. Under load, this leads to a death spiral where all PHP-FPM workers block on container regeneration, no worker is free to serve traffic, and requests fail with PHP fatal errors.

The `localcachedir` is designed by Moodle to be node-local and must NOT be shared between servers (Moodle `config-dist.php`). On Kubernetes, this directory should be mounted on an `emptyDir` volume so each pod has its own independent copy. However, cache purge operations that touch shared cache directories (on NFS/PVC) can still trigger cross-pod invalidation through `.lastpurged` timestamp files.

Several approaches to cache management during startup were considered:

1. **Full cache purge on every startup** — Guarantees a clean state but causes the death spiral under load when multiple replicas start or when cache is purged while traffic is flowing.
2. **Cache purge only on first install** — Safe for existing deployments but leaves stale cache entries across plugin upgrades.
3. **Targeted cache invalidation + pre-warming** — Only invalidate specific cache definitions that change (e.g., ObjectFS, OIDC) and pre-build the DI container and theme CSS before accepting traffic.
4. **No cache management** — Rely on Moodle's built-in cache invalidation logic. Simplest but can leave stale definitions after plugin changes.

Forces in tension: cache freshness (ensuring new plugin definitions take effect) vs. startup reliability (ensuring the DI container exists before traffic arrives) vs. multi-replica safety (avoiding cross-pod cache invalidation).

## Decision

We will use targeted cache invalidation with startup pre-warming: only invalidate cache definitions that change during initialization, and pre-build the DI container and theme CSS before PHP-FPM accepts traffic.

### Cache Purge Policy

The `purge_caches.php --muc --other` command is **not** used during pod startup. This command purges all application and alternative caches, including `CompiledContainer.php` in `localcachedir`, and writes a `.lastpurged` timestamp to the shared `$CFG->cachedir` that invalidates local caches on all pods.

Instead, cache definitions for plugins being configured (ObjectFS, OIDC) are invalidated individually using `cfg.php` commands, which only affect the specific cache definitions that change. This is equivalent to visiting the Moodle admin notifications page — Moodle detects changes and rebuilds only the affected definitions.

### Startup Pre-Warming

The startup sequence runs `build_theme_css.php` for each installed and active theme before PHP-FPM starts. The base theme (`boost`) is always compiled; additional themes are compiled conditionally:

```bash
php "$MOODLE_PATH/admin/cli/build_theme_css.php" --themes=boost
# Additional themes are compiled based on deployment configuration
```

`build_theme_css.php` serves a dual purpose:

1. **DI container creation** — It bootstraps Moodle (which triggers `CompiledContainer.php` generation if missing), guaranteeing the container exists before any web request arrives.
2. **Theme CSS compilation** — It compiles theme CSS from SCSS/LESS sources, pre-populating `$CFG->localcachedir` with the compiled CSS files that every page load references.

Only installed themes that are actively used need to be pre-warmed. Deployments that install additional themes beyond `boost` should add corresponding `build_theme_css.php` calls to their startup sequence.

### When to Use `purge_caches.php`

| Scenario | Use `purge_caches.php`? | Alternative |
|----------|------------------------|-------------|
| Pod startup / initialization | **No** | Use `cfg.php` per-definition invalidation + pre-warming |
| Plugin upgrade (web UI) | **Yes** | Moodle handles this automatically via admin notifications |
| Manual maintenance window | **Yes** | Run during low-traffic periods with a single replica |
| After config change | **No** | Use `cfg.php` to invalidate only the changed definitions |

## Consequences

### Positive

- **Eliminates DI container death spiral** — Each pod independently builds its `CompiledContainer.php` during startup before accepting traffic. No cross-pod invalidation can occur.
- **Startup pre-warming guarantees warm caches** — The DI container and theme CSS exist before the first web request, eliminating cold-start latency for these resources.
- **Plugin configuration still works** — `cfg.php` commands in initialization scripts invalidate only the cache definitions that change, so new plugin settings take effect without a full purge.
- **Safe for multi-replica deployments** — No startup operation touches shared cache directories in a way that could invalidate other pods' local caches.

### Negative

- **Pre-warming adds ~5–10 seconds to startup** — Each `build_theme_css.php` call takes a few seconds. This is acceptable because Kubernetes startup probes give pods ample time (default: 600 seconds) before marking them as failed.
- **Stale cache entries after manual plugin installation** — If a new plugin is installed by directly copying files (not through the initialization scripts), its cache definitions may not be invalidated. This is mitigated by running `purge_caches.php` manually or relying on Moodle's automatic detection on the admin notifications page.
- **Only pre-configured themes are pre-warmed** — Deployments must explicitly add `build_theme_css.php` calls for each additional theme beyond `boost`. Missing themes will be compiled on first request, not cause failures, but incur cold-start latency.

### Neutral

- **The `objectfs_init.sh` script still uses `cfg.php` for cache invalidation** — This is a targeted approach that only affects the ObjectFS-related cache definitions. The removal of `purge_caches.php` does not affect this mechanism.
- **Pre-warming is idempotent** — Running `build_theme_css.php` when the CSS already exists is a no-op (Moodle checks file timestamps), so it does not add overhead on subsequent pod restarts if the cache was not cleared.

## References

- Moodle `config-dist.php` — `$CFG->localcachedir` documentation: "This must NOT be shared between servers."
- Moodle `admin/cli/build_theme_css.php` — CLI script for compiling theme CSS from SCSS/LESS sources
- Moodle `admin/cli/purge_caches.php` — CLI script for purging all or specific cache types
- Moodle `admin/cli/cfg.php` — CLI script for setting individual configuration values and invalidating specific cache definitions