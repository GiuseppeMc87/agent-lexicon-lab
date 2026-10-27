# Agent Lexicon Lab

Agent Lexicon Lab is a Laravel and React laboratory for observing how AI agents create, negotiate, and evolve a shared symbolic vocabulary.

Each experiment alternates a sender and receiver for a bounded number of turns. Messages preserve symbolic and decoded content, vocabulary definitions are versioned immutably, and append-only events show how meanings change over time.

## Technology

- Laravel 13 and PHP 8.5
- React 19, TypeScript, Inertia 3, and Vite Plus
- MySQL 8.4.13
- Nginx 1.28.3 and Docker Compose v2
- Database-backed queues and scheduling
- A deterministic fake AI provider for local development and automated tests
- An optional Vertex AI adapter using Google Application Default Credentials

The lock files are authoritative for exact application dependency versions. Container image versions are declared in `Dockerfile` and `docker-compose.yml`.

## Local setup with Docker

Docker is the supported development environment. Composer and PHP commands run inside the application container.

```bash
cp .env.example .env
docker compose build
docker compose run --rm --no-deps app composer install
docker compose run --rm --no-deps frontend npm ci
docker compose up -d
docker compose exec -T app php artisan key:generate
docker compose exec -T app php artisan migrate
docker compose run --rm --no-deps frontend npm run build
```

Open <http://localhost:8080>, register an account, verify it in the local database or mail log as appropriate for the environment, create an experiment, and start it. The default `AI_PROVIDER=fake` makes no external or billable requests.

For frontend hot reloading, start the optional development service:

```bash
docker compose --profile development up -d frontend
```

Useful status and log commands:

```bash
docker compose ps
docker compose logs -f app nginx queue scheduler
```

## Running experiments

The web action dispatches a unique queue job. A small command is also available:

```bash
docker compose exec -T app php artisan experiments:run 1
docker compose exec -T app php artisan experiments:run --pending
```

Only the experiment owner may view or start an experiment. Start requests have a dedicated throttle. Jobs and experiment rows use overlap protection, and every turn is committed in a database transaction.

Recurring dispatch is disabled by default. To opt in, set `AI_SCHEDULE_ENABLED=true`. The interval and batch size are bounded by `AI_SCHEDULE_INTERVAL_MINUTES` and `AI_SCHEDULE_BATCH_SIZE`.

## Vertex AI

Vertex execution is disabled by default because it can incur charges. Enabling it requires both:

```dotenv
AI_PROVIDER=vertex
AI_PAID_EXECUTION_ENABLED=true
```

Set `GOOGLE_CLOUD_PROJECT`, `VERTEX_AI_LOCATION`, and `VERTEX_AI_MODEL`. Provide credentials through Google Application Default Credentials. In containers, mount a service-account file as a secret and set `GOOGLE_APPLICATION_CREDENTIALS` to its container path; never add the JSON file to this repository.

The adapter requests structured JSON, validates the entire response before persistence, uses bounded timeouts, and sanitizes stored failures. It retries only an explicit rate-limit response once. Connection failures are not retried automatically because the provider may already have accepted a billable request.

Check model availability, IAM, quota, regional support, and expected spend in the Google Cloud project before enabling Vertex. No live Vertex request is part of the automated test suite.

## Validation

```bash
docker compose exec -T app composer lint:check
docker compose exec -T app composer types:check
docker compose exec -T app php artisan test
docker compose run --rm --no-deps frontend npm run check
docker compose run --rm --no-deps frontend npm run types:check
docker compose run --rm --no-deps frontend npm run build
docker compose config --quiet
```

Tests always bind a fake provider and must not call a billable AI service.

## Architecture

Laravel owns session authentication, authorization, routing, persistence, queueing, and scheduling. React pages live in the same repository and are rendered through Inertia.

The main boundaries are:

- domain models for experiments, messages, vocabulary entries, immutable revisions, and events;
- validated role-specific provider response objects;
- an AI provider contract with fake and Vertex implementations;
- an orchestration service responsible for lifecycle and transactional turn persistence;
- a unique queued job and a thin Artisan dispatch command;
- policy-protected Inertia controllers and ID-cursor polling resources.

The extraction evidence, preserved behavior, rejected source defects, and unresolved decisions are recorded in `docs/extraction-specification.md`.

## Deployment notes

Production deployments must provide a real `APP_KEY`, database credentials, HTTPS termination, queue workers, scheduler execution, and environment-specific cache/session settings. Run migrations with `--force`, cache configuration only after all environment values are present, and keep application secrets outside the image and repository.

Do not expose the fake development account data or a writable public demo. Any future public demo should use separate, sanitized, read-only seeded data with its own rate limits.

## Publication status

No public licence has been selected. The repository is therefore not ready for public publication. A licence review, clean-clone verification, dependency and asset licence review, and final secret/proprietary-residue scan are required before changing repository visibility or publishing a release.

## Extraction audit record

This implementation was produced from a read-only comparison of local Git refs. The branch comparison, rather than online pull-request metadata, defined the extraction scope. No repository website, hosting API, connector, downloaded patch, merge, rebase, cherry-pick, reset, clean, commit, or push was used.

### Compared refs

- Base ref: `origin/master`
- Base commit: `13ade7824357a8d015ec5fc63eb61fba54878163`
- Feature ref: `origin/feutures/agent_dictionary`
- Feature commit: `3545d6e5c1e16f17f5c6c2a0f0cd744c184cb42c`
- Merge base: `13ade7824357a8d015ec5fc63eb61fba54878163`
- Remote refs refreshed during extraction: no
- Refresh note: `git fetch --prune origin` was attempted, but the source Git metadata was read-only, so the existing remote-tracking refs were used

The comparison commands were:

```bash
git status --short --branch
git remote -v
git branch --all --verbose --no-abbrev
git fetch --prune origin
git merge-base origin/master origin/feutures/agent_dictionary
git diff --stat origin/master...origin/feutures/agent_dictionary
git diff --name-status origin/master...origin/feutures/agent_dictionary
git diff --find-renames --find-copies origin/master...origin/feutures/agent_dictionary
git log --oneline --decorate origin/master..origin/feutures/agent_dictionary
git show <ref>:<path>
```

Every file changed on the feature branch was inspected. Base-branch context was read only where needed to understand those changes. The source Compose file, active Dockerfile, versioned alternative Docker directories, Nginx and PHP configuration, startup scripts, Composer and npm manifests, Vite configuration, environment example, and setup documentation were also audited.

### Behaviour preserved

- An authenticated owner can create a bounded experiment in `pending` state and explicitly start it.
- A unique queued job moves an experiment through `pending`, `running`, `completed`, or `failed`.
- Sender and receiver agents alternate for a configured number of turns.
- Messages store role, turn, symbolic content, deterministic decoded content, and provider request ID when available.
- Sender vocabulary proposed during a turn is withheld from the receiver during that same turn so inference remains observable.
- Vocabulary entries expose the current definition through immutable, monotonically versioned revisions.
- Every creation or revision produces an append-only event containing proposer role, turn, message, entry, and revision references.
- Messages link to the exact vocabulary revisions represented by explicit `[[symbol]]` tokens.
- A thin Artisan command dispatches work without owning provider or orchestration logic.
- Authenticated Inertia pages provide experiment listing, creation, start/resume controls, lifecycle states, live monitoring, chronological conversation, active vocabulary, and complete vocabulary history.

### Defects and risks confirmed in the source implementation

Every risk requested for review was checked and classified. None remained uncertain.

| Risk | Classification | Clean-room response |
| --- | --- | --- |
| A migration reads `token_key` before the column exists | Confirmed | Fresh final-state migrations are ordered by dependency. |
| AI fields disagree between `dictionary`, `inferred_dictionary`, and `message_in_clear` | Confirmed | Sender and receiver use explicit DTOs with one validated field schema. |
| Turn polling omits vocabulary associated with newly returned messages | Confirmed | Polling uses message and event IDs and always returns the current vocabulary. |
| Experiment endpoints lack authentication and ownership checks | Confirmed | Routes require authenticated, verified users and policies enforce ownership. |
| Expensive execution lacks a dedicated throttle | Confirmed | Manual starts have a dedicated rate limiter. |
| Provider and orchestration logic live in an oversized command | Confirmed | Provider, validation, orchestration, vocabulary, job, and command responsibilities are separate. |
| Decoding performs unsafe substring replacement | Confirmed | Only exact, delimited `[[symbol]]` tokens are decoded, without cascading replacement. |
| Paid execution runs every five minutes without cost controls | Confirmed | Paid execution and recurring scheduling have separate disabled-by-default flags. |
| The feature has no automated tests | Confirmed | Backend, HTTP, database, command, job, schedule, fake-provider, and frontend checks were added. |
| Tracked examples contain credential-like or identifying values | Confirmed | Only placeholders are present; provider credentials remain external. |
| Active PHP and MySQL versions are obsolete for the new application | Confirmed | The new images pin PHP 8.5.12 and MySQL 8.4.13. |
| Compose uses fixed container names | Confirmed | Compose-generated names are used. |
| Compose requires an external network | Confirmed | The new project uses its default internal network. |
| A mounted PHP configuration path is a directory instead of a file | Confirmed | Every referenced configuration file is committed with the correct type. |
| Multiple versioned Docker directories are unused | Confirmed | One Dockerfile and one committed configuration tree define the new stack. |
| Provider failures can leave experiments running | Confirmed | Exceptions transition the experiment to a terminal failed state with sanitized details. |
| Raw provider output may be logged | Confirmed | Raw bodies, prompts, credentials, and authentication payloads are not logged. |
| Vocabulary revisions are not concurrency-safe | Confirmed | Transactions, row locks, unique constraints, retry bounds, and overlap locks protect revision order. |

Unrelated recruitment, content, profile, course, payment, currency, OAuth-server, legacy frontend, private infrastructure, branding, prompts, identifiers, and Git history were excluded.

### AI response contract

Both roles return the same field structure while remaining distinct DTO types:

```json
{
    "message": "Text containing zero or more [[symbol]] tokens",
    "vocabulary": [
        {
            "symbol": "lowercase-token",
            "meaning": "Plain-language meaning",
            "description": "Reason for the proposed definition"
        }
    ]
}
```

Unknown fields, malformed JSON, wrong container types, invalid symbols, missing required values, excessive sizes, timeouts, rate limits, authentication failures, interrupted requests, and provider errors are handled explicitly. Failed experiments retain only a safe failure code and message.

The Vertex adapter uses Application Default Credentials, HTTPS, bounded connect and request timeouts, a maximum output-token limit, and a structured response schema. It retries one explicit HTTP 429 response. It does not automatically retry an ambiguous connection failure because the provider may already have accepted a billable request.

### Schema and concurrency controls

The final schema is represented directly rather than reproducing corrective migrations. It contains:

- users owning experiments;
- experiments with provider, model, bounded progress, lifecycle timestamps, failure information, and a lock version;
- messages unique by experiment, turn, and role;
- vocabulary entries unique by experiment and symbol;
- immutable revisions unique by vocabulary entry and version;
- append-only vocabulary events;
- a composite-primary-key pivot linking messages to exact vocabulary revisions.

Each turn is committed atomically. Experiment rows and vocabulary entries are locked during state or revision changes. Database uniqueness constraints remain the final protection against concurrent duplicate versions.

### Security and cost boundaries

- Every experiment route requires authentication and verified email status.
- Route binding and policies prevent cross-user experiment access.
- Creation and polling use validating form requests.
- Manual starts and incremental polling have separate throttles.
- Jobs are unique per experiment and use queue overlap middleware.
- `AI_PAID_EXECUTION_ENABLED=false` blocks Vertex execution by default.
- `AI_SCHEDULE_ENABLED=false` prevents recurring dispatch by default.
- Turn count, scheduled batch size, request timeout, output tokens, and polling frequency are bounded.
- Service-account JSON, bearer tokens, prompts, and raw provider responses must never be committed or logged.
- A public writable demo is not implemented. Any future demo must be separately routed, read-only, sanitized, seeded, and rate-limited.

### Docker verification

The new Docker environment was written independently. It contains PHP-FPM, Nginx, MySQL, a frontend development image, a database queue worker, and a scheduler. Images are pinned to exact versions:

- `php:8.5.12-fpm-bookworm`
- `composer:2.8.12`
- `node:24.22.1-bookworm-slim`
- `nginx:1.28.3-alpine`
- `mysql:8.4.13`

The stack uses no hard-coded container names, external network, Redis service, missing mount, or host-root requirement. Application processes use a configurable non-root UID/GID. Ports bind to localhost, MySQL data uses a named volume, health dependencies wait for healthy services, and worker and scheduler services use the application image.

Final container results:

- `docker compose config --quiet`: passed
- Docker build: 26 of 26 stages completed
- PHP-FPM application: healthy
- MySQL: healthy
- Nginx: healthy
- Queue worker: running
- Scheduler: running
- `php artisan migrate:fresh --force`: every migration passed against MySQL
- Deterministic container smoke experiment: completed 2 turns, 4 messages, 2 vocabulary entries, and 4 append-only events

### Automated verification record

All Composer, Artisan, and PHP verification commands ran inside the application container. No automated test makes a billable AI call.

| Command | Result |
| --- | --- |
| `composer test` | Passed Pint on 109 PHP files, PHPStan with zero errors, and 75 tests with 267 assertions. |
| `php artisan test --compact` | 75 tests passed with 267 assertions. |
| `npm run check` | 76 files correctly formatted; 67 files had no lint warnings or errors. |
| `npm run types:check` | Passed with no TypeScript errors. |
| `npm run build` | Passed; 2,337 modules transformed and a production manifest was generated. |
| `composer audit --no-interaction` | No security vulnerability advisories found. |
| `npm audit --audit-level=high` | Zero vulnerabilities found. |
| Editor diagnostics | No errors found. |

Test coverage includes fresh migrations, lifecycle transitions, strict response validation, vocabulary creation and revision, revision locking, append-only events, exact token decoding, message/revision relationships, authorization, index and detail pages, ID cursors, missing experiments, job and command success/failure, disabled scheduling, overlap protection, HTTP-blocked provider fakes, frontend types, and production assets.

### Secret, identity, and proprietary-residue audit

The final redacted scan inspected 341 repository-ready files without printing values:

- source branding or local source paths: zero findings;
- private-key headers: zero findings;
- non-placeholder credential assignments: zero findings;
- copied UUID or client-like identifiers: zero findings;
- source email-domain overlap: zero findings;
- source repository remote added to this project: no;
- nested Git repository: none.

Local `.env`, runtime storage, dependencies, and generated production assets were excluded from repository-content findings. `.env` is excluded by both `.gitignore` and `.dockerignore`.

The dependency inventory found declared licences for 146 Composer packages and 381 npm packages. Composer licences were MIT, BSD, Apache, or dual-licensed packages offering BSD terms. npm packages declared MIT, ISC, BSD, Apache, MPL, CC, or 0BSD terms. This inventory is technical evidence, not a legal compatibility opinion.

### Official sources used for architectural decisions

- [Laravel for AI agents](https://laravel.com/for/agents)
- [Laravel release notes and support policy](https://laravel.com/framework/docs/releases)
- [Laravel installation](https://laravel.com/framework/docs/13.x/installation)
- [Official Laravel starter kits](https://laravel.com/framework/docs/13.x/starter-kits)
- [Laravel React and Inertia frontend guidance](https://laravel.com/framework/docs/13.x/frontend)
- [Authentication](https://laravel.com/framework/docs/13.x/authentication) and [authorization](https://laravel.com/framework/docs/13.x/authorization)
- [Queues](https://laravel.com/framework/docs/13.x/queues) and [task scheduling](https://laravel.com/framework/docs/13.x/scheduling)
- [HTTP client](https://laravel.com/framework/docs/13.x/http-client), [validation](https://laravel.com/framework/docs/13.x/validation), and [migrations](https://laravel.com/framework/docs/13.x/migrations)
- [Testing](https://laravel.com/framework/docs/13.x/testing), [configuration](https://laravel.com/framework/docs/13.x/configuration), and [deployment](https://laravel.com/framework/docs/13.x/deployment)
- [Laravel AI SDK](https://laravel.com/framework/docs/13.x/ai-sdk)
- [Google Cloud PHP authentication and ADC](https://docs.cloud.google.com/php/docs/reference/help/authentication)
- [Google structured-output guidance](https://docs.cloud.google.com/gemini-enterprise-agent-platform/models/capabilities/control-generated-output)

The Laravel AI SDK documentation was reviewed but did not document the required Vertex AI endpoint and ADC behavior. A minimal Laravel HTTP adapter behind the provider contract was therefore selected instead of assuming that Gemini API-key support was equivalent to Vertex AI.

### Verification boundaries and publication blockers

Every requested implementation and audit area was addressed. The following items remain deliberately unverified or blocked and must not be described as completed:

- No live Vertex request was made. Credentials, IAM roles, quota, regional model access, billing, and live structured-output behavior remain unverified.
- No public licence has been selected.
- The application was assembled in an authorized staging directory because the intended independent clone was unavailable in the working environment.
- The destination repository's existing Git root and remote must be rechecked after the files are moved.
- Independent history and setup from a clean clone remain unverified until that move is complete.
- Dependency and asset licence compatibility still requires final owner or legal review.

The source repository's tracked working tree and index remained clean. Its refs, history, tags, remotes, and configuration were not changed. No target remote was changed or added, and nothing was committed, pushed, tagged, released, published, or opened as a pull request.

The repository must remain private until these blockers are resolved and the public-readiness review is repeated from the final independent clone.



### Test account
email: giuseppemuci87@gmail.com
password: YUNTJ5b4wS@