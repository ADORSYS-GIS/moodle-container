# ADR 0004: ObjectFS S3 Path-Style Endpoint for S3-Compatible Storage

## Date

2026-07-01

## Status

Accepted

## Context

Moodle's ObjectFS plugin (`tool_objectfs`) uses the AWS SDK for PHP to interact with S3-compatible storage backends (MinIO, Ceph, GCS, etc.) via the `s3_base_url` configuration setting. When this endpoint is set, the AWS SDK defaults to **virtual-hosted-style** URL addressing, where the bucket name is embedded in the hostname (e.g., `http://moodle.minio.local:9000/objectfs/...`).

This creates two problems:

1. **Presigned URL resolution** — ObjectFS generates presigned URLs for range requests (video streaming, file downloads) that embed the virtual-hosted-style hostname. Clients outside the cluster cannot resolve `moodle.minio.local`, resulting in DNS failures and broken file access.

2. **S3-compatible storage compatibility** — Many S3-compatible backends (MinIO, Ceph, single-bucket GCS) do not support virtual-hosted-style addressing or require additional DNS configuration (`MINIO_DOMAIN`) to route bucket-level hostnames. This adds operational complexity and creates coupling between the storage backend's DNS configuration and the application's presigned URL generation.

The AWS SDK provides a `use_path_style_endpoint` configuration option that switches URL generation to **path-style** addressing, where the bucket name is part of the URL path (e.g., `http://minio.local:9000/moodle/objectfs/...`). Path-style URLs are universally supported by all S3-compatible backends without additional DNS configuration.

Three approaches were considered:

1. **Application-level patch** — Modify the ObjectFS S3 client (`client.php`) to pass `use_path_style_endpoint` to the AWS SDK when a configuration flag is set. The plugin's `configure_objectfs.php` script writes `s3_use_path_style` to the Moodle database, which is read by the patched client.

2. **DNS-level workaround** — Configure `MINIO_DOMAIN` on the MinIO server and add network aliases so that virtual-hosted-style URLs resolve correctly within the container network. This requires no code changes to ObjectFS but creates tight coupling between infrastructure DNS and application behavior.

3. **Server mode** — Configure ObjectFS to use an external ML backend server that handles S3 communication, bypassing the `exec()` requirement and the URL style issue. This is overkill for a single MinIO instance and adds an additional service to manage.

## Decision

We will use an application-level patch to the ObjectFS S3 client that adds `use_path_style_endpoint` support, controlled by a database configuration flag.

### S3 Client Patch

The ObjectFS plugin's `client.php` is patched to read `s3_use_path_style` from the Moodle configuration and pass it to the AWS SDK:

```php
// In set_client(), after the s3_base_url block:
if (!empty($config->s3_use_path_style)) {
    $options['use_path_style_endpoint'] = true;
}
```

This is mounted as a read-only volume overlay in both the main Moodle container and the ObjectFS initialization container, ensuring the patch persists across plugin upgrades (the ObjectFS plugin is installed from a read-only volume).

### Configuration Flag

The `s3_use_path_style` setting is written to the `tool_objectfs` plugin configuration by the initialization script:

```php
set_config('s3_use_path_style', env_bool('OBJECTFS_S3_USE_PATH_STYLE', true) ? 1 : 0, 'tool_objectfs');
```

The default is `true` because S3-compatible storage in containerized deployments typically lacks the DNS infrastructure for virtual-hosted-style addressing. Deployments targeting native AWS S3 should set the environment variable to `false`.

### Infrastructure Simplification

With path-style endpoints, the following infrastructure configuration is no longer needed and has been removed:

- `MINIO_DOMAIN` environment variable on the MinIO service (which enabled virtual-hosted-style addressing)
- Network aliases that mapped bucket hostnames (e.g., `moodle.minio.local`) to the MinIO container

## Consequences

### Positive

- **Universal S3 compatibility** — Path-style URLs work with MinIO, Ceph, GCS, and all S3-compatible backends without additional DNS configuration.
- **Presigned URLs are reachable** — Clients receive URLs like `http://minio.local:9000/moodle/objectfs/...` which resolve correctly within the container network.
- **Simplified infrastructure** — No `MINIO_DOMAIN` configuration or bucket-level DNS aliases needed.
- **Explicit configuration** — The `OBJECTFS_S3_USE_PATH_STYLE` environment variable makes the URL style choice visible and configurable per deployment.
- **No upstream code change required** — The patch is a minimal addition (3 lines) to `client.php`, suitable for an upstream contribution or a future plugin release.

### Negative

- **Plugin upgrade fragility** — The `client.php` patch is mounted as a volume overlay. If the upstream ObjectFS plugin version changes, the patched file must be updated to match the new version's `set_client()` method signature. This is mitigated by mounting the patch read-only; a version mismatch will cause a PHP fatal error (obvious, not silent).
- **AWS S3 performance** — Path-style addressing on native AWS S3 incurs additional DNS lookups per request (the bucket is resolved as part of the URL path rather than as a separate hostname). This is negligible for MinIO and other on-premises backends but may impact high-throughput AWS S3 deployments.

### Neutral

- **The patch does not change presigned URL generation logic** — ObjectFS's `proxy_range_request()` and `get_presigned_url()` methods continue to work as before; the URL style is determined by the AWS SDK based on the `use_path_style_endpoint` option.
- **Deployments targeting native AWS S3 should set `OBJECTFS_S3_USE_PATH_STYLE=false`** — This restores virtual-hosted-style addressing, which is AWS's recommended and most performant URL style.

## References

- AWS SDK for PHP `S3Client` configuration: [docs.aws.amazon.com/sdk-for-php/v3/api/class-Aws.S3.S3Client.html](https://docs.aws.amazon.com/sdk-for-php/v3/api/class-Aws.S3.S3Client.html)
- MinIO path-style vs virtual-hosted-style: [min.io/docs/minio/linux/operations/network-encryption.html](https://min.io/docs/minio/linux/operations/network-encryption.html)
- ObjectFS plugin: [github.com/catalyst/moodle-tool_objectfs](https://github.com/catalyst/moodle-tool_objectfs)
- See also: ADR 0001 — Startup Cache Pre-Warm and Cache Purge Policy (ObjectFS initialization uses `cfg.php` for cache invalidation)