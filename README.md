# DrupalConf Backend

A conference management platform where **Drupal 11 serves as a headless CMS backend** and a separate
Next.js app is the public frontend. The backend exposes content over JSON:API, packages its whole
content model as a Drupal **recipe**, and is built to modern standards: PSR-4 / PHP 8.3, DDEV,
enforced coding standards + static analysis, PHPUnit, and GitHub Actions CI.

## Two-repository architecture

In production this is **two separate repositories**:

| Repository | Stack |
|---|---|
| **drupalconf-backend** (this repo) | Drupal 11 · JSON:API · custom modules · recipe · Composer · DDEV |
| **drupalconf-frontend** | Next.js · TypeScript · JSON:API client · SCSS |

The [`front-end-example/`](front-end-example/) folder is a **reference** kept in this repo so the
backend's API shape and the frontend's data needs stay aligned — it is not the deployed frontend.
The frontend points at the backend via `NEXT_PUBLIC_API_BASE_URL` (see
[`front-end-example/.env.example`](front-end-example/.env.example)), and the backend's CORS allowlist
must include the frontend origin (see [Security](#security)).

## Backend features

- **Headless CMS** — content exposed via **read-only** JSON:API (`jsonapi_extras`) under `/api/jsonapi`.
- **Recipe-based build** — the entire content model (7 content types, fields, paragraphs, taxonomies,
  JSON:API config, path patterns, the content editor role) ships as `recipes/drupalconf` and rebuilds
  from scratch (see [Getting started](#getting-started)).
- **Custom module suite** — `drupalconf_api` (contact form + settings), `drupalconf_reservations`
  (the reservations feature), `drupalconf_default_content` (seeds paragraph pages/blogs/menu).
- **Custom entity** — a `Reservation` content entity backing email-only session reservations with
  capacity enforcement and a privacy-preserving lookup.
- **Standard decoupled stack** — `decoupled_router` for alias→entity resolution and
  `jsonapi_menu_items` for menus, rather than bespoke endpoints.
- **Security** — read-only JSON:API, scoped CORS, attendee data shielded from JSON:API, and
  flood/honeypot protection on the public write endpoints.

See [docs/API.md](docs/API.md) for the full endpoint reference.

## Development

### Prerequisites

- [DDEV](https://ddev.readthedocs.io/) (Docker-based local environment)
- PHP 8.3+
- Composer

### Getting Started

The site is built from the **DrupalConf recipe** (`recipes/drupalconf`), which installs
the content model (content types, fields, paragraphs, taxonomies, JSON:API config, path
patterns, the content editor role) and seeds sample content:

```bash
ddev start
ddev composer install
ddev drush site:install minimal -y
ddev drush recipe ../recipes/drupalconf -y
ddev drush cr
```

The recipe imports the data content (conferences, sessions, speakers, sponsors, venues,
taxonomy) via Drupal core's default-content system. The paragraph-based pages (home, about,
contact, blog) and the main menu are created by the `drupalconf_default_content` module on
the `RecipeAppliedEvent`, because core default-content cannot represent paragraph
(`entity_reference_revisions`) fields.

### Refresh Command

A custom DDEV command that updates your local project and site to match the current working copy. Composer install is always run regardless of the option chosen.

```bash
ddev refresh [option]
```

| Option | Description |
|---|---|
| `all` / `` (default) | Runs all operations |
| `back` | Builds back-end resources |
| `front` | Builds front-end resources |
| `conf` / `drupal` | Rebuilds the site from the DrupalConf recipe |
| `help` | Prints the help message |

When the `conf` option runs, it:

1. Reinstalls Drupal (`drush site:install minimal`)
2. Applies the DrupalConf recipe (`drush recipe ../recipes/drupalconf`)
3. Rebuilds caches (`drush cr`)
4. Generates a one-time login link

> **Note:** This project is built from the recipe, not config sync. It is in active
> development and is rebuilt from scratch rather than migrated, so there is no
> `drush cim` / config-sync step and no incremental update hooks.

## Quality gates

All four run in CI and can be run locally:

```bash
ddev composer validate --strict --no-check-all   # composer.json sanity
ddev exec vendor/bin/phpcs                         # Drupal + DrupalPractice coding standards
ddev exec vendor/bin/phpstan analyse               # static analysis (level 6)
ddev exec vendor/bin/phpunit                       # PHPUnit test suite
```

Config lives in [phpcs.xml.dist](phpcs.xml.dist), [phpstan.neon.dist](phpstan.neon.dist), and
[phpunit.xml.dist](phpunit.xml.dist), all scoped to custom code under `web/modules/custom` and
`web/themes/custom`.

## CI/CD

[GitHub Actions](.github/workflows/ci.yml) runs on every push and pull request:

- **Lint & static analysis** — `composer validate`, PHPCS (Drupal standards), PHPStan (level 6).
- **Tests** — PHPUnit against a MariaDB service.

## Security

- **JSON:API is read-only** — all writes go through the dedicated, hardened endpoints; write methods
  on JSON:API return `405`.
- **CORS is scoped** to the configured frontend origin(s) in `web/sites/default/services.yml`
  (no wildcard, no credentials). Override the origin per environment for a real deployment.
- **Attendee data is shielded** — the `Reservation` entity's access handler denies view to
  non-admins, so reservations never surface through JSON:API.
- **Public write endpoints** (contact, reservations) use a honeypot, flood/rate limiting, and the
  reservation lookup is privacy-preserving (it discloses only whether reservations exist and emails
  the details to the address).

> **Note on email:** transactional email (reservation confirmations and lookups) is implemented with
> Drupal's mail API but is **not wired to a real transport** — on this portfolio site messages are
> captured by the local mail catcher, never delivered externally.

> **Known issue:** a `guzzlehttp/psr7` advisory (CVE-2026-48998 / -49214) is currently unresolvable —
> no Drupal 11.3 release permits the patched psr7 yet — so its two advisory IDs are documented under
> `config.audit.ignore` in `composer.json`, to be cleared on the next core update.
