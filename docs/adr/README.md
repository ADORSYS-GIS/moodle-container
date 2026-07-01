# Architecture Decision Records

This directory contains Architecture Decision Records (ADRs) for the moodle-nginx container image, following the format described by Michael Nygard in [Documenting Architecture Decisions](https://cognitect.com/blog/2011/11/15/documenting-architecture-decisions).

ADRs document decisions that are "architecturally significant" — ones that affect the structure, non-functional characteristics, dependencies, interfaces, or construction techniques of the container image. These decisions apply to the image itself and are independent of any specific deployment.

## Format

Each ADR follows this structure:

- **Title** — a short noun phrase, also used in the filename in the format `NNNN-title-of-decision.md`
- **Context** — the forces at play, including technological, performance, and operational factors. Written in value-neutral language.
- **Decision** — the response to these forces, stated in full sentences with active voice ("We will …")
- **Status** — `proposed`, `accepted`, `deprecated`, or `superseded` with a reference to the replacement ADR.
- **Consequences** — the resulting context after applying the decision, including positive, negative, and neutral consequences.

## Index

| Number | Title | Status |
|--------|-------|--------|
| [ADR 0000](0000-adr-template.md) | ADR Template | — |
| [ADR 0001](0001-startup-cache-pre-warm-and-cache-purge-policy.md) | Startup Cache Pre-Warm and Cache Purge Policy | Accepted |
| [ADR 0002](0002-php-fpm-pool-sizing-and-timeout-configuration.md) | PHP-FPM Pool Sizing and Timeout Configuration | Accepted |
| [ADR 0003](0003-nginx-dual-timeout-and-admin-location-block.md) | Nginx Dual Timeout and Admin Location Block | Accepted |
| [ADR 0004](0004-objectfs-s3-path-style-endpoint-for-s3-compatible-storage.md) | ObjectFS S3 Path-Style Endpoint for S3-Compatible Storage | Accepted |