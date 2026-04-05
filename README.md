# TMDB Movies

A web application for browsing and searching movies powered by [The Movie Database (TMDB) API](https://www.themoviedb.org/).

**Backend:** Symfony 8 · PHP 8.4 · JWT authentication · APCu caching  
**Frontend:** Vue 3 · Pinia · Vue Router · Vite · Tailwind CSS  
**Infrastructure:** Docker · Nginx · PHP-FPM

---

## Features

- Stub login with JWT issuance (replaceable auth provider interface)
- Browse popular movies on the home page
- Real-time search by title with 400 ms debounce
- Movie cards: poster, title, rating, release year
- Pagination (up to 500 pages, 20 results per page)
- Symfony proxies all TMDB requests — API key never reaches the browser
- Response caching with APCu (configurable TTL)
- Rate limiting on the login endpoint (5 attempts / minute per IP)

---

## Requirements

- Docker >= 24.x
- Docker Compose >= 2.x
- A TMDB API **Read Access Token (v4 Bearer)** — get one at [themoviedb.org/settings/api](https://www.themoviedb.org/settings/api)

---

## Getting Started

### 1. Clone and configure environment

```bash
git clone <repo-url> tmdb-movies
cd tmdb-movies
cp .env.example .env
```

Open `.env` and fill in the required values:

```dotenv
TMDB_API_KEY=your_tmdb_bearer_token_here   # Read Access Token v4
AUTH_USERNAME=admin
AUTH_PASSWORD=your_password_here
APP_SECRET=                                # run: openssl rand -hex 32
```

### 2. Build and start containers

```bash
docker compose up -d --build
```

This starts two services:

| Service | Description | Port |
|---------|-------------|------|
| `app` | PHP 8.4-FPM (Symfony) | internal |
| `web` | Nginx | `8080` (configurable via `APP_PORT`) |

### 3. Install dependencies

```bash
docker compose exec app composer install
```

### 4. Generate JWT keys

```bash
docker compose exec app php bin/console lexik:jwt:generate-keypair
```

Keys are written to `config/jwt/`. The `JWT_PASSPHRASE` is automatically added to `.env.local`.

### 5. Clear cache

```bash
docker compose exec app php bin/console cache:clear
```

### 6. Open the app

```
http://localhost:8080
```

Log in with the credentials you set in `.env` (`AUTH_USERNAME` / `AUTH_PASSWORD`).

---

## Frontend Development

The Vue.js frontend is built with Vite. For development with hot reload:

```bash
# Install Node dependencies
docker compose exec node npm install

# Start Vite dev server (hot reload on port 5173)
docker compose exec node npm run dev
```

For a production build:

```bash
docker compose exec node npm run build
```

The compiled assets land in `public/build/` and are served by Nginx.

---

## API Reference

All endpoints under `/api` require a `Authorization: Bearer <token>` header, except `/api/auth/login`.

### Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/auth/login` | Obtain a JWT token |
| `GET` | `/api/auth/me` | Get current user info |

**Login request:**
```json
POST /api/auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "your_password"
}
```

**Login response:**
```json
{
  "token": "<jwt-token>"
}
```

### Movies

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/movies` | Popular movies (paginated) |
| `GET` | `/api/movies/search?q=...` | Search movies by title |
| `GET` | `/api/movies/{id}` | Movie details by TMDB ID |

**Query parameters:**

| Parameter | Endpoint | Rules |
|-----------|----------|-------|
| `page` | `/api/movies`, `/api/movies/search` | Integer >= 1, default: 1 |
| `q` | `/api/movies/search` | 2–100 characters, trimmed |

**Movie object shape:**
```json
{
  "id": 550,
  "title": "Fight Club",
  "original_title": "Fight Club",
  "overview": "A ticking-time-bomb insomniac...",
  "poster_url": "https://image.tmdb.org/t/p/w500/pB8BM7pdSp6B6Ih7QZ4DrQ3PmJK.jpg",
  "backdrop_url": "https://image.tmdb.org/t/p/original/...",
  "vote_average": 8.4,
  "vote_count": 26280,
  "release_date": "1999-10-15",
  "release_year": "1999",
  "genre_ids": [18],
  "original_language": "en",
  "popularity": 61.4
}
```

### Error responses

| HTTP Status | When |
|-------------|------|
| `400` | Invalid input (e.g. search query too short) |
| `401` | Missing or invalid JWT token |
| `404` | Movie not found in TMDB |
| `429` | Rate limit exceeded (login: 5 req/min; TMDB upstream) |
| `502` | TMDB API unreachable or returned an unexpected error |
| `503` | TMDB rate limit exceeded upstream |

---

## Project Structure

```
.
├── config/
│   ├── jwt/                        # RSA keys (gitignored)
│   └── packages/
│       ├── cache.yaml              # APCu pool for TMDB responses
│       ├── lexik_jwt_authentication.yaml
│       ├── nelmio_cors.yaml
│       ├── rate_limiter.yaml       # Login brute-force protection
│       └── security.yaml
├── docker/
│   └── nginx/default.conf
├── Dockerfile                      # PHP 8.4-FPM image
├── docker-compose.yml
├── src/
│   ├── Auth/
│   │   ├── AuthProviderInterface.php   # Swap out for DB/OAuth later
│   │   ├── StubAuthProvider.php
│   │   └── UserProvider.php
│   ├── Controller/
│   │   ├── AuthController.php
│   │   ├── MovieController.php
│   │   └── SpaController.php           # Catch-all → serves Vue SPA
│   ├── DTO/
│   │   ├── MovieDTO.php
│   │   └── MovieListDTO.php
│   ├── Exception/
│   │   ├── AuthenticationException.php
│   │   └── TmdbApiException.php
│   ├── Repository/
│   │   └── MovieRepository.php
│   └── Service/Tmdb/
│       ├── TmdbClientInterface.php
│       ├── HttpTmdbClient.php          # Real HTTP calls to TMDB
│       ├── CachedTmdbClient.php        # Decorator: adds APCu caching
│       └── TmdbResponseMapper.php
├── assets/                         # Vue 3 source (Vite entry)
│   ├── app.js
│   ├── App.vue
│   ├── api/client.js               # Axios + JWT interceptors
│   ├── components/
│   │   ├── MovieCard.vue
│   │   ├── MovieList.vue
│   │   ├── Pagination.vue
│   │   └── SearchBar.vue
│   ├── pages/
│   │   ├── HomePage.vue
│   │   ├── LoginPage.vue
│   │   └── NotFoundPage.vue
│   ├── router/index.js
│   ├── stores/
│   │   ├── auth.js                 # JWT persist in localStorage
│   │   └── movies.js
│   └── style.css
├── tests/
│   ├── Unit/
│   └── Integration/
└── plan/
    ├── PLAN.md                     # Architecture and code reference
    └── STEPS.md                    # Step-by-step implementation checklist
```

---

## Configuration

All configuration is driven by environment variables. Copy `.env.example` to `.env` and adjust.

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_ENV` | Symfony environment (`dev` / `prod`) | `dev` |
| `APP_SECRET` | Symfony secret key | — |
| `APP_PORT` | Host port mapped to Nginx | `8080` |
| `TMDB_API_KEY` | TMDB Read Access Token (v4 Bearer) | — |
| `TMDB_CACHE_TTL` | Cache TTL for TMDB responses (seconds) | `300` |
| `AUTH_USERNAME` | Stub login username | `admin` |
| `AUTH_PASSWORD` | Stub login password | — |
| `JWT_SECRET_KEY` | Path to RSA private key | `%kernel.project_dir%/config/jwt/private.pem` |
| `JWT_PUBLIC_KEY` | Path to RSA public key | `%kernel.project_dir%/config/jwt/public.pem` |
| `JWT_PASSPHRASE` | RSA key passphrase (auto-generated) | — |
| `CORS_ALLOW_ORIGIN` | Allowed CORS origin regex | `^https?://(localhost\|127\.0\.0\.1)(:[0-9]+)?$` |

> `.env` is gitignored. Only `.env.example` is committed to the repository.

---

## Running Tests

```bash
# All tests
docker compose exec app ./vendor/bin/phpunit

# Unit tests only
docker compose exec app ./vendor/bin/phpunit tests/Unit

# Integration tests only
docker compose exec app ./vendor/bin/phpunit tests/Integration

# With code coverage (requires Xdebug or PCOV)
docker compose exec app ./vendor/bin/phpunit --coverage-html coverage/
```

Frontend tests (Vitest):

```bash
npm run test
```

---

## Architecture Notes

### Authentication provider

`AuthProviderInterface` decouples the login logic from the controller. The current `StubAuthProvider` uses hardcoded credentials from `.env`. To switch to a database-backed provider, create a new class implementing `AuthProviderInterface` and update the alias in `config/services.yaml`:

```yaml
App\Auth\AuthProviderInterface:
    alias: App\Auth\DatabaseAuthProvider   # was: StubAuthProvider
```

No other files need to change.

### TMDB caching

`CachedTmdbClient` is a decorator over `HttpTmdbClient`. It stores responses in an APCu cache pool (`tmdb.cache`) with a configurable TTL (`TMDB_CACHE_TTL`). For production, swap the adapter in `config/packages/cache.yaml` to Redis:

```yaml
framework:
    cache:
        app: cache.adapter.redis
        default_redis_provider: "%env(REDIS_URL)%"
```

### JWT tokens

Tokens are signed with a 4096-bit RSA key pair and expire after 1 hour (`token_ttl: 3600`). On expiry the Axios response interceptor catches the 401 and triggers a client-side logout. For production consider adding refresh tokens (`gesdinet/jwt-refresh-token-bundle`).

---

## Security Checklist

- [x] TMDB API key never sent to the browser
- [x] JWT signed with RSA (asymmetric, 4096-bit)
- [x] Login rate-limited: 5 attempts / minute / IP
- [x] CORS restricted to configured origins
- [x] `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` headers set by Nginx
- [ ] `httpOnly` cookie for JWT (currently `localStorage` — acceptable for internal tools)
- [ ] Refresh token rotation for long-lived sessions

---

## Useful Commands

```bash
# Start / stop
docker compose up -d
docker compose down

# Symfony console
docker compose exec app php bin/console <command>

# Composer
docker compose exec app composer <command>

# Check container logs
docker compose logs -f app
docker compose logs -f web

# Regenerate JWT keys
docker compose exec app php bin/console lexik:jwt:generate-keypair --overwrite

# Clear Symfony cache
docker compose exec app php bin/console cache:clear
```
