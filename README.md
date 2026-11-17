# TMDB Movies

A movie search application on top of The Movie Database (TMDB) API, currently at the stage of a Dockerised Symfony 8
skeleton plus a detailed implementation plan.

## Context

Started in April 2026 as a take-home style task: a login-protected web app for browsing and searching movies, with a
Symfony JSON API that proxies TMDB and a Vue 3 single-page frontend. The repository shows how the task was approached
before coding: the infrastructure is set up and the design is written down in [`plan/PLAN.md`](plan/PLAN.md)
(architecture, key classes, configuration, security, Docker, testing) and [`plan/STEPS.md`](plan/STEPS.md)
(step-by-step checklist). The application code itself has not been implemented in this repository yet.

## Features

Implemented:

- Symfony 8 skeleton running on PHP 8.4-FPM behind Nginx in Docker Compose
- Nginx config with gzip and basic security headers (`X-Frame-Options`, `X-Content-Type-Options`)
- GitHub Actions CI workflow (composer validation, PHP lint, PHPStan, PHPUnit)

Planned (see `plan/PLAN.md`):

- Stub login that issues a JWT, behind a replaceable authentication provider interface
- Popular movies on the home page, title search with debounce, pagination
- Movie cards with poster, title, rating and release year
- Symfony proxies all TMDB requests, so the API key never reaches the browser
- Cached TMDB responses and rate limiting on the login endpoint

## Tech stack

Current: PHP 8.4, Symfony 8.0, Doctrine ORM/Migrations bundles, PHPUnit 13, PHPStan 2, Docker Compose, Nginx,
GitHub Actions.

Planned in addition: LexikJWTAuthenticationBundle, Symfony HTTP Client and Cache, Vue 3, Pinia, Vue Router, Vite,
Tailwind CSS.

## Architecture

Current layout: standard Symfony skeleton (`src/Kernel.php`, `config/`, `public/index.php`), `Dockerfile` for the PHP
image, `docker-compose.yml` with `app` (PHP-FPM) and `web` (Nginx) services, `docker/nginx/default.conf`.

Planned design (from `plan/PLAN.md`):

- SPA architecture: Vue 3 talks to the Symfony backend over a JSON API
- `TmdbClientInterface` with an HTTP implementation and a caching decorator around it
- Response mapper and DTOs between the TMDB payload and the API output
- `AuthProviderInterface` so the stub login can later be replaced by a database or OAuth provider

Planned configuration is environment-driven, for example:

```dotenv
TMDB_API_KEY=your_tmdb_read_access_token
AUTH_USERNAME=admin
AUTH_PASSWORD=your_password
```

## Getting started

Requirements: Docker with Docker Compose.

```bash
git clone https://github.com/okarabudka/media-app.git
cd media-app

docker compose up -d --build
docker compose exec app composer install
```

Nginx listens on http://localhost:8080 (override with `APP_PORT`). At this stage it serves the Symfony skeleton.

## Testing

There are no tests yet (`tests/` contains only the bootstrap file). The CI workflow in `.github/workflows/ci.yml` runs
the checks that can be reproduced locally:

```bash
docker compose exec app composer validate
docker compose exec app php bin/phpunit
docker compose exec app vendor/bin/phpstan analyse
```

PHPStan has no configuration file in the repository yet, so it needs paths passed explicitly until one is added.

## Status

Planning stage since April 2026: infrastructure and implementation plan are in place, application code is not
implemented.

## License

MIT, see [LICENSE](LICENSE).
