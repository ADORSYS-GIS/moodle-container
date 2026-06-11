# Moodle Helm Chart

A Helm chart for deploying [adorsys-gis/moodle-container](https://github.com/ADORSYS-GIS/moodle-container) on Kubernetes with external database and Redis services.

## Prerequisites

- Kubernetes 1.22+
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
| `controllers.main.containers.main.env.SITE_URL` | `http://moodle.local` | Public URL of the Moodle site |
| `controllers.main.containers.main.env.MOODLE_LANGUAGE` | `en` | Default language |
| `controllers.main.containers.main.env.MOODLE_SITENAME` | `Moodle` | Site full name |
| `controllers.main.containers.main.env.MOODLE_SITESUMMARY` | `Moodle LMS running on Kubernetes` | Site summary |
| `controllers.main.containers.main.env.MOODLE_USERNAME` | `admin` | Admin username |
| `controllers.main.containers.main.env.MOODLE_EMAIL` | `admin@moodle.local` | Admin email |
| `controllers.main.containers.main.env.ENABLE_FRESHCLAM` | `no` | Enable ClamAV scanning |
| `controllers.main.containers.main.env.ENABLE_MOOSH_BOOTSTRAP` | `no` | Enable Moosh CLI bootstrap |
| `controllers.main.containers.main.env.ENABLE_OBJECTFS` | `no` | Enable ObjectFS plugin |

### Database

| Key | Default | Description |
|-----|---------|-------------|
| `controllers.main.containers.main.env.DB_TYPE` | `mariadb` | Database driver |
| `controllers.main.containers.main.env.DB_HOST` | `""` | Database host (required) |
| `controllers.main.containers.main.env.DB_HOST_PORT` | `3306` | Database port |
| `controllers.main.containers.main.env.DB_NAME` | `moodle` | Database name |
| `controllers.main.containers.main.env.DB_USER` | `moodle_user` | Database user |
| `controllers.main.containers.main.env.DB_PREFIX` | `mdl_` | Table prefix |

### Redis

| Key | Default | Description |
|-----|---------|-------------|
| `controllers.main.containers.main.env.REDIS_SESSION_ID_HOST` | `""` | Redis session host |
| `controllers.main.containers.main.env.REDIS_SESSION_ID_PORT` | `6379` | Redis session port |
| `controllers.main.containers.main.env.REDIS_SESSION_IP_AND_PORT` | `""` | Redis session host:port |
| `controllers.main.containers.main.env.REDIS_APP_IP_AND_PORT` | `""` | Redis application cache host:port |
| `controllers.main.containers.main.env.REDIS_LOCK_HOST_AND_PORT` | `""` | Redis lock factory host:port |

### Secrets

The chart auto-generates Kubernetes Secrets for passwords if no `existingSecret` is provided:

| Custom Value | Secret Key | Description |
|--------------|-----------|-------------|
| `moodle.password` | `moodle-password` | Moodle admin password |
| `moodle.existingSecret` | — | Use an existing Secret instead of auto-generated |
| `externalDatabase.password` | `db-password` | Database password |
| `externalDatabase.existingSecret` | — | Use an existing Secret instead of auto-generated |
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
  --set controllers.main.containers.main.env.DB_HOST=mariadb.mariadb.svc.cluster.local \
  --set controllers.main.containers.main.env.REDIS_SESSION_ID_HOST=redis.redis.svc.cluster.local \
  --set controllers.main.containers.main.env.REDIS_SESSION_IP_AND_PORT=redis.redis.svc.cluster.local:6379 \
  --set controllers.main.containers.main.env.REDIS_APP_IP_AND_PORT=redis.redis.svc.cluster.local:6379 \
  --set controllers.main.containers.main.env.REDIS_LOCK_HOST_AND_PORT=redis.redis.svc.cluster.local:6379
```

### With existing Secrets

```bash
helm install my-moodle moodle/moodle \
  --set moodle.existingSecret=my-moodle-secret \
  --set externalDatabase.existingSecret=my-db-secret \
  --set externalRedis.existingSecret=my-redis-secret \
  --set controllers.main.containers.main.env.DB_HOST=mariadb.mariadb.svc.cluster.local \
  --set ingress.main.enabled=true \
  --set ingress.main.hosts[0].host=moodle.example.com
```

### With ingress and custom image tag

```bash
helm install my-moodle moodle/moodle \
  --set controllers.main.containers.main.image.tag=4.5 \
  --set controllers.main.containers.main.env.SITE_URL=https://moodle.example.com \
  --set controllers.main.containers.main.env.DB_HOST=mariadb.mariadb.svc.cluster.local \
  --set ingress.main.enabled=true \
  --set ingress.main.className=nginx \
  --set ingress.main.hosts[0].host=moodle.example.com \
  --set ingress.main.tls[0].hosts[0]=moodle.example.com \
  --set ingress.main.tls[0].secretName=moodle-tls
```

## Source

- Docker image: [ghcr.io/adorsys-gis/moodle-container](https://github.com/ADORSYS-GIS/moodle-container/pkgs/container/moodle-container)
- Chart source: [charts/moodle](https://github.com/ADORSYS-GIS/moodle-container/tree/main/charts/moodle)