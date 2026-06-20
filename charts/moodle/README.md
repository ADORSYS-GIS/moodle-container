# Moodle Helm Chart

A Helm chart for deploying [adorsys-gis/moodle-container](https://github.com/ADORSYS-GIS/moodle-container) on Kubernetes with external database, Redis, and multi-pod support.

## Prerequisites

- Kubernetes 1.28+
- Helm 3.8+
- An external database (MariaDB/MySQL or PostgreSQL via CloudSQL or otherwise)
- An external Redis instance (e.g., Memorystore)
- A Filestore-backed StorageClass for RWX volumes (for multi-pod)

## Architecture

This chart deploys Moodle as a **multi-pod StatefulSet with HPA** (2–3 replicas), with:

- **RWX Filestore PVC** for shared `moodledata`
- **emptyDir** volumes for `localcache`, `localrequest`, and `/tmp`
- **External Redis** for sessions, application cache, and locks
- **External database** (CloudSQL or self-managed)
- **Pod anti-affinity** to spread pods across nodes
- **PodDisruptionBudget** for availability during disruptions
- **Startup/liveness/readiness probes** for reliable health checking

## Install

```bash
helm repo add moodle https://adorsys-gis.github.io/moodle-container
helm repo update
helm install my-moodle moodle/moodle
```

## Configuration

The chart uses the [bjw-s/common](https://github.com/bjw-s/helm-charts/tree/main/charts/library/common) library chart (v5.0.1) as a base. All common chart values are supported in addition to the ones listed below.

### Moodle Site Settings

| Key | Default | Description |
|-----|---------|-------------|
| `moodle.siteUrl` | `http://moodle.local` | Public URL of the Moodle site |
| `moodle.language` | `en` | Default language |
| `moodle.siteName` | `Moodle` | Site full name |
| `moodle.siteSummary` | `Moodle LMS running on Kubernetes` | Site summary |
| `moodle.username` | `admin` | Admin username |
| `moodle.email` | `admin@moodle.local` | Admin email |
| `moodle.enableFreshclam` | `no` | Enable ClamAV scanning |
| `moodle.enableMooshBootstrap` | `no` | Enable Moosh CLI bootstrap |
| `moodle.sslproxy` | `false` | Enable SSL Proxy (for reverse proxies) |
| `moodle.noEmailEver` | `false` | Completely disable Moodle emails |
| `moodle.curlSecurity.blockedHosts` | `""` | Comma-separated list of blocked hosts for cURL |
| `moodle.curlSecurity.allowedPorts` | `""` | Comma-separated list of allowed ports for cURL |

### SMTP Settings

| Key | Default | Description |
|-----|---------|-------------|
| `smtp.host` | `""` | SMTP Host |
| `smtp.port` | `587` | SMTP Port |
| `smtp.user` | `""` | SMTP Username |
| `smtp.protocol` | `tls` | SMTP Protocol (`tls`, `ssl`, etc.) |
| `smtp.noreplyAddress` | `noreply@moodle.local` | Moodle No-Reply address |
| `smtp.mailPrefix` | `[Moodle]` | Mail subject prefix |
| `smtp.existingSecretPasswordKey` | `smtp-password` | Secret key for SMTP password |

### Database

| Key | Default | Description |
|-----|---------|-------------|
| `externalDatabase.type` | `mariadb` | Database driver (`mariadb`, `pgsql`) |
| `externalDatabase.host` | `""` | Database host (required) |
| `externalDatabase.port` | `3306` | Database port |
| `externalDatabase.database` | `moodle` | Database name |
| `externalDatabase.user` | `moodle_user` | Database user |
| `externalDatabase.prefix` | `mdl_` | Database tables prefix |
| `externalDatabase.existingSecretPasswordKey` | `db-password` | Key inside the secret containing the password |
| `externalDatabase.readReplica.host` | `""` | Database read replica host |
| `externalDatabase.readReplica.port` | `3306` | Database read replica port |
| `externalDatabase.readReplica.user` | `""` | Database read replica user |
| `externalDatabase.readReplica.existingSecretPasswordKey` | `db-replica-password` | Key inside the secret containing the read replica password |

### Redis

| Key | Default | Description |
|-----|---------|-------------|
| `externalRedis.host` | `""` | Redis host |
| `externalRedis.port` | `6379` | Redis port |
| `externalRedis.existingSecretPasswordKey` | `redis-password` | Key inside the secret containing the password |

### Secrets

The chart auto-generates Kubernetes Secrets for passwords if no `existingSecret` is provided:

| Custom Value | Secret Key | Description |
|--------------|-----------|-------------|
| `moodle.password` | `moodle-password` | Moodle admin password |
| `moodle.existingSecret` | — | Use an existing Secret instead of auto-generated |
| `smtp.password` | `smtp-password` | SMTP server password |
| `smtp.existingSecret` | — | Use an existing Secret instead of auto-generated |
| `externalDatabase.password` | `db-password` | Database password |
| `externalDatabase.existingSecret` | — | Use an existing Secret instead of auto-generated |
| `externalDatabase.readReplica.password` | `db-replica-password` | Database read replica password |
| `externalDatabase.readReplica.existingSecret` | — | Use an existing Secret instead of auto-generated |
| `externalRedis.password` | `redis-password` | Redis password |
| `externalRedis.existingSecret` | — | Use an existing Secret instead of auto-generated |

### Scaling & Availability

| Key | Default | Description |
|-----|---------|-------------|
| `controllers.main.type` | `statefulset` | Controller type (`statefulset` or `deployment`) |
| `controllers.main.replicas` | `2` | Number of pod replicas |
| `controllers.main.horizontalPodAutoscaler.minReplicas` | `2` | HPA minimum replicas |
| `controllers.main.horizontalPodAutoscaler.maxReplicas` | `3` | HPA maximum replicas |
| `controllers.main.podDisruptionBudget.minAvailable` | `1` | PDB minimum available pods |

### Persistence

| Key | Default | Description |
|-----|---------|-------------|
| `persistence.moodledata.enabled` | `true` | Enable persistent Moodle data volume |
| `persistence.moodledata.size` | `100Gi` | PVC size |
| `persistence.moodledata.accessMode` | `ReadWriteMany` | PVC access mode (must be RWX for multi-pod) |
| `persistence.moodledata.storageClass` | `standard-rwx` | StorageClass (Filestore-backed) |
| `persistence.localcache.enabled` | `true` | Enable emptyDir for local cache |
| `persistence.localrequest.enabled` | `true` | Enable emptyDir for local request data |
| `persistence.tmp.enabled` | `true` | Enable emptyDir for /tmp |

### Ingress

| Key | Default | Description |
|-----|---------|-------------|
| `ingress.main.enabled` | `false` | Enable ingress |
| `ingress.main.className` | `""` | Ingress class name |
| `ingress.main.hosts[0].host` | `moodle.local` | Ingress hostname |

## Examples

### Minimal install (auto-generated secrets, single node)

```bash
helm install my-moodle moodle/moodle \
  --set externalDatabase.host=mariadb.mariadb.svc.cluster.local \
  --set externalRedis.host=redis.redis.svc.cluster.local
```

### Multi-pod GKE production deployment

```bash
helm install my-moodle moodle/moodle \
  --set moodle.siteUrl=https://moodle.example.com \
  --set externalDatabase.type=pgsql \
  --set externalDatabase.host=cloudsql-proxy.cloudsql.svc.cluster.local \
  --set externalDatabase.port=5432 \
  --set externalDatabase.user=moodle \
  --set externalDatabase.database=moodle \
  --set externalRedis.host=10.0.0.1 \
  --set externalRedis.password=myredispass \
  --set moodle.password=adminpass \
  --set persistence.moodledata.storageClass=standard-rwx \
  --set persistence.moodledata.size=100Gi \
  --set ingress.main.enabled=true \
  --set ingress.main.className=nginx \
  --set ingress.main.hosts[0].host=moodle.example.com
```

### With existing Secrets

```bash
helm install my-moodle moodle/moodle \
  --set moodle.existingSecret=my-moodle-secret \
  --set externalDatabase.existingSecret=my-db-secret \
  --set externalRedis.existingSecret=my-redis-secret \
  --set externalDatabase.host=db.example.com \
  --set externalRedis.host=redis.example.com
```

## Important Notes

### Plugin Installation

The Docker image has `$CFG->disableupdateautodeploy = true` set in `config.php`. This **prevents plugin installation via the Moodle web interface**. All plugins must be pre-installed in the Docker image. This is critical for multi-pod deployments where pod restarts would otherwise lose runtime-installed plugins.

### Storage Layout

| Path | Storage | Description |
|------|---------|-------------|
| `/moodleroot/moodledata` | RWX Filestore PVC | Shared Moodle data (configs, lang, etc.) |
| `/moodleroot/moodledata/localrequest` | emptyDir | Per-pod transient request data |
| `/tmp/moodle/localcache` | emptyDir | Per-pod local cache |
| `/tmp` | emptyDir | Temporary files |

## Source

- Docker image: [ghcr.io/adorsys-gis/moodle-nginx](https://github.com/ADORSYS-GIS/moodle-container/pkgs/container/moodle-container)
- Chart source: [charts/moodle](https://github.com/ADORSYS-GIS/moodle-container/tree/main/charts/moodle)