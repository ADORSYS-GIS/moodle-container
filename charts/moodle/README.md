# Moodle Helm Chart

A Helm chart for deploying [adorsys-gis/moodle-container](https://github.com/ADORSYS-GIS/moodle-container) on Kubernetes with external database and Redis services.

## Prerequisites

- Kubernetes 1.28+
- Helm 3.8+
- An external MariaDB/MySQL database
- An external Redis instance

## Install

```bash
helm repo add moodle https://adorsys-gis.github.io/moodle-container
helm repo update
helm install my-moodle moodle/moodle
```

## Configuration

The chart uses the [bjw-s/common](https://github.com/bjw-s/helm-charts/tree/main/charts/library/common) library chart as a base. All common chart values are supported in addition to the ones listed below.

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
| `moodle.enableObjectfs` | `no` | Enable ObjectFS plugin |
| `moodle.sslproxy` | `false` | Enable SSL Proxy (for reverse proxies) |
| `moodle.noEmailEver` | `false` | Completely disable Moodle emails |

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
| `externalDatabase.type` | `mariadb` | Database driver |
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

### Persistence

| Key | Default | Description |
|-----|---------|-------------|
| `persistence.moodledata.enabled` | `true` | Enable persistent Moodle data volume |
| `persistence.moodledata.size` | `8Gi` | PVC size |
| `persistence.moodledata.accessMode` | `ReadWriteOnce` | PVC access mode |

### Ingress

| Key | Default | Description |
|-----|---------|-------------|
| `ingress.main.enabled` | `false` | Enable ingress |
| `ingress.main.className` | `""` | Ingress class name |
| `ingress.main.hosts[0].host` | `moodle.local` | Ingress hostname |

## Examples

### Minimal install (auto-generated secrets)

```bash
helm install my-moodle moodle/moodle \
  --set externalDatabase.host=mariadb.mariadb.svc.cluster.local \
  --set externalRedis.host=redis.redis.svc.cluster.local
```

### With existing Secrets

```bash
helm install my-moodle moodle/moodle \
  --set moodle.existingSecret=my-moodle-secret \
  --set externalDatabase.existingSecret=my-db-secret \
  --set externalRedis.existingSecret=my-redis-secret \
  --set externalDatabase.host=mariadb.mariadb.svc.cluster.local \
  --set externalRedis.host=redis.redis.svc.cluster.local \
  --set ingress.main.enabled=true \
  --set ingress.main.hosts[0].host=moodle.example.com
```

### With ingress and custom image tag

```bash
helm install my-moodle moodle/moodle \
  --set controllers.main.containers.main.image.tag=4.5 \
  --set moodle.siteUrl=https://moodle.example.com \
  --set externalDatabase.host=mariadb.mariadb.svc.cluster.local \
  --set externalRedis.host=redis.redis.svc.cluster.local \
  --set ingress.main.enabled=true \
  --set ingress.main.className=nginx \
  --set ingress.main.hosts[0].host=moodle.example.com \
  --set ingress.main.tls[0].hosts[0]=moodle.example.com \
  --set ingress.main.tls[0].secretName=moodle-tls
```

## Source

- Docker image: [ghcr.io/adorsys-gis/moodle-container](https://github.com/ADORSYS-GIS/moodle-container/pkgs/container/moodle-container)
- Chart source: [charts/moodle](https://github.com/ADORSYS-GIS/moodle-container/tree/main/charts/moodle)