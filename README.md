# media-app

[![CI](https://github.com/okarabudka/media-app/actions/workflows/ci.yml/badge.svg)](https://github.com/okarabudka/media-app/actions/workflows/ci.yml)

Movie search on top of [The Movie Database (TMDB)](https://www.themoviedb.org/). A Symfony JSON API proxies and
caches TMDB and handles login; a Vue 3 single-page app shows popular movies, searches as you type and pages through
the results. The TMDB token stays on the server, and the session token never becomes readable by JavaScript.

![Movie list rendered with sample data](docs/screenshot-movies.webp)

<sub>Screenshot with **sample data**: fictional titles from a local stand-in for TMDB (`TMDB_BASE_URI`) and generated
placeholder posters, not live TMDB results.</sub>

## Why it exists

It started as a take-home style task: a login-protected app for browsing and searching movies. The goal here is to
solve it the way a production feature would be solved, not just make it work: a clear API contract, security
decisions that are written down, a third-party API that is cached and isolated behind an interface, and tests that
run without network or credentials.

## What you get

**A backend that protects the upstream API.** Symfony 8.1 on PHP 8.5. The browser only ever talks to `/api`; Symfony
calls TMDB with the server-side token, caches successful answers in Redis and translates TMDB failures into clear
HTTP errors. Search input is validated before it reaches TMDB.

**Login with a replaceable provider.** `AuthProviderInterface` decides who may log in. The included
`StubAuthProvider` accepts one account from the environment and rejects everything while it is not configured.
A database or OAuth provider replaces it without touching controllers or security config.

**A session that scripts cannot steal.** After login the API sets a JWT (LexikJWTAuthenticationBundle) in an
`httpOnly`, `SameSite=Strict` cookie. Login is rate limited per client IP and per username from that IP.

**A small, tested SPA.** Vue 3 with Pinia, vue-router, axios and Tailwind CSS, built by Vite and served through
Symfony. Search waits for a 400 ms pause and at least two characters; late answers of older searches are dropped.

**Quality checks from the first commit.** PHPUnit unit and functional tests, PHPStan at level max with the Symfony and
PHPUnit extensions (tests included), PHP CS Fixer, container/YAML/Twig lint, ESLint and Vitest. CI runs all of them,
starts the Docker stack for a smoke test and scans the history with gitleaks.

## Architecture

```mermaid
flowchart LR
    B["Vue 3 SPA<br/>Pinia · vue-router · axios"] -- "/api/* + auth cookie" --> S
    subgraph S["Symfony API"]
        A["AuthController<br/>rate limiter · JWT cookie"]
        M["MovieController<br/>validated query DTOs"]
        C["CachedTmdbClient"] --> H["HttpTmdbClient"]
        M --> C
    end
    C <--> R[("Redis")]
    H -- "Bearer TMDB token" --> T["TMDB API v3"]
    B -. "poster images" .-> I["image.tmdb.org"]
```

- `src/Auth` — `AuthProviderInterface`, `StubAuthProvider`, the user provider for JWTs and the cookie factory.
- `src/Tmdb` — `TmdbClientInterface`, the HTTP client, the caching decorator (`#[AsDecorator]`) and the response
  mapper that turns TMDB JSON into `MovieDTO` / `MovieListDTO`.
- `src/EventSubscriber` — every error under `/api` becomes an RFC 9457 problem response; no stack traces.
- `assets/` — the Vue app; `tests/frontend/` — its Vitest suites.

## API

| Method | Path                            | Auth | Description                                         |
|--------|---------------------------------|------|-----------------------------------------------------|
| POST   | `/api/auth/login`               | —    | JSON `{username, password}`; sets the auth cookie   |
| POST   | `/api/auth/logout`              | —    | Clears the auth cookie                              |
| GET    | `/api/auth/me`                  | ✓    | `{username}` of the current session                 |
| GET    | `/api/movies?page=`             | ✓    | Popular movies                                      |
| GET    | `/api/movies/search?q=&page=`   | ✓    | Search; `q` 2–100 characters after trimming         |
| GET    | `/api/movies/{id}`              | ✓    | One movie                                           |

`page` is 1–500 (TMDB serves no more). Errors use `application/problem+json`:

| Situation                                   | Status |
|---------------------------------------------|--------|
| Invalid `q` or `page`, malformed login body | 400 (with `violations`) |
| Wrong credentials, missing or expired session | 401  |
| Unknown movie                               | 404    |
| Login body that is not JSON                 | 415    |
| Login attempts from one client IP above 5 per minute for one username or 20 in total | 429 (with `Retry-After`) |
| TMDB rejects the token, fails or is unreachable | 502 |
| TMDB rate limit, or `TMDB_API_KEY` not set  | 503    |

## How to use it

Requirements: Docker with Docker Compose, make and openssl.

Quickest way to try it, without a TMDB token and without network access to TMDB: run `make demo` instead of
`make up` below. The movie endpoints then serve fictional sample data from `fixtures/demo/movies.json` (26 titles;
search and details work, no posters). If `AUTH_USERNAME` or `AUTH_PASSWORD` in `.env` is empty, it fills in the user
`demo` with a random password and tells you where to find it. `make up` switches back to TMDB.

With a TMDB token:

```bash
git clone https://github.com/okarabudka/media-app.git && cd media-app
make up
```

The first `make up` creates `.env` from `.env.example` with a generated `APP_SECRET` and `JWT_PASSPHRASE`, builds the
images, installs Composer dependencies and generates the JWT key pair in `config/jwt/` (git-ignored). Then fill in
`.env`:

```dotenv
TMDB_API_KEY=<your TMDB API Read Access Token>
# Example values for a local demo only — choose your own.
AUTH_USERNAME=demo
AUTH_PASSWORD=change-me-locally
```

Open http://localhost:8080 and log in. If port 8080 is taken, set `APP_PORT` in `.env` and run `make up` again,
or pass it for one run: `make up APP_PORT=9000`. `PROD_PORT` works the same way for `make prod-up`.
`make up` also starts the Vite dev server (port `VITE_PORT`, default 5173) with hot reload; Symfony includes its
scripts automatically.

<img src="docs/screenshot-login.webp" alt="Login page" width="420">

### Without a TMDB token

Everything except the movie data works without `TMDB_API_KEY`: login, session, logout, validation and all tests.
The movie endpoints answer `503` with "The movie database is not configured: set TMDB_API_KEY on the server.", which
the page shows instead of the list. The test suites never need a token: PHPUnit uses a mock HTTP client with JSON
fixtures and Vitest mocks axios.

To click through the UI with data but no token, use `make demo` (see above). Alternatively, point `TMDB_BASE_URI` at any server that answers the TMDB paths
`movie/popular`, `search/movie` and `movie/{id}` with JSON shaped like `tests/Fixtures/tmdb/`, and set
`TMDB_API_KEY` to any non-empty value. That is how the screenshot above was made.

### Production-like stack

```bash
make prod-up    # http://localhost:8081 (PROD_PORT)
make prod-down
```

`compose.prod.yaml` builds the `app` and `web` targets of `docker/php/Dockerfile`: Composer without dev packages and
an authoritative class map, OPcache without timestamp checks, assets compiled by Vite in a Node stage (`npm ci
--ignore-scripts` from the lock file) and served by Nginx with immutable cache headers. No code is mounted and no dev
server runs. It reuses `.env` and the JWT keys from `make up`; the keys are mounted read-only and handed to the
PHP-FPM workers on start.

### Day-to-day work

| Target            | What it does                                                          |
|-------------------|-----------------------------------------------------------------------|
| `make up`         | Create `.env` on first run, start all containers, install, JWT keys   |
| `make down`       | Stop and remove containers                                            |
| `make prod-up`    | Production-like stack with compiled assets on `PROD_PORT`             |
| `make prod-down`  | Stop the production-like stack                                        |
| `make test`       | PHPUnit (unit + functional, no network, no TMDB token)                |
| `make lint`       | PHP CS Fixer, PHPStan, container/YAML/Twig lint, composer validate    |
| `make check`      | `lint` and `test`, the backend part of CI                             |
| `make fix`        | Fix code style                                                        |
| `make front-lint` | ESLint (installs `node_modules` from the lock file first if needed)   |
| `make front-test` | Vitest (jsdom, axios mocked)                                          |
| `make build`      | Production build of the frontend into `public/build`                  |
| `make lock`       | Regenerate `package-lock.json` (see below)                            |
| `make help`       | List all targets                                                      |

`bin/smoke-test [base-url]` checks a running stack end to end: the SPA shell and its script, login, session,
validation, the TMDB-not-configured answer and logout. CI runs it against both stacks.

### CI

| Job        | What it checks                                                                              |
|------------|---------------------------------------------------------------------------------------------|
| `backend`  | composer validate, container/YAML/Twig lint, PHP CS Fixer, PHPStan (max), PHPUnit            |
| `frontend` | `npm ci --ignore-scripts`, ESLint, Vitest, production build, no TMDB API host in the bundle |
| `docker`   | `make up` with the Vite dev server and `make prod-up`, each followed by `bin/smoke-test`     |
| `secrets`  | gitleaks over the full history                                                              |

### Frontend dependencies

`package.json` pins exact versions, `.npmrc` disables install scripts, and every install (`make`, Docker, CI) is an
`npm ci --ignore-scripts` from the committed `package-lock.json`. To change dependencies, edit `package.json` and run
`make lock`: it rewrites the lock file in a `node:22-alpine` container with `npm install --package-lock-only
--ignore-scripts --before=<14 days ago>`, so a release published in the last two weeks is never picked up.
Dependabot uses the same 14-day cooldown.

## Security notes

- **TMDB token.** Only in the server environment; sent per request to TMDB and masked in error messages. Neither API
  responses nor the JS bundle contain it.
- **Session cookie.** The JWT is in an `httpOnly` cookie, so an XSS bug cannot read it. It is `SameSite=Strict` and
  `Secure` on HTTPS (or always, with `JWT_COOKIE_SECURE=1`). The API ignores `Authorization` headers.
- **CSRF.** Cookie authentication brings CSRF into scope. `SameSite=Strict` keeps browsers from sending the cookie
  on any cross-site request. Login accepts only `application/json` bodies, which an HTML form cannot send and which
  trigger a CORS preflight from scripts; that blocks login CSRF. CORS allows credentials only for the origins in
  `CORS_ALLOW_ORIGIN`. The API changes no data besides the session itself; if it ever does, add a CSRF token.
- **Logout.** Clears the cookie. A stateless JWT cannot be revoked before it expires (1 hour); a stolen token would
  stay valid until then. Short TTLs or a deny list are the next step if that matters.
- **Brute force.** 5 attempts per minute per client IP and username, 20 per minute per IP, stored in Redis. A
  successful login resets the per-username counter. There is no limit per account across all IPs, so an attack
  spread over many addresses is only slowed per address.
- **Content-Security-Policy.** nginx allows scripts, styles, fonts and requests only from the app's own origin,
  images additionally from `image.tmdb.org` (`docker/nginx/csp.conf`). The development stack uses a looser policy
  for the Vite dev server and Symfony's error pages (`docker/nginx/csp.dev.conf`).
- **Fail closed.** No configured account means no login; no TMDB token means a 503, never a stack trace.

## Status

Maintained. Built in November 2026.

## License

MIT, see [LICENSE](LICENSE).
