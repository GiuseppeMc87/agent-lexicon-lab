# Agent Lexicon Lab extraction specification

## Evidence boundary

This specification is derived from a read-only comparison of local Git refs. No pull-request page, GitHub API, connector, or downloaded patch was used.

- Base ref: `origin/master` at `13ade7824357a8d015ec5fc63eb61fba54878163`
- Feature ref: `origin/feutures/agent_dictionary` at `3545d6e5c1e16f17f5c6c2a0f0cd744c184cb42c`
- Merge base: `13ade7824357a8d015ec5fc63eb61fba54878163`
- Remote refs refreshed during this run: no; the environment made `.git/FETCH_HEAD` read-only

The comparison used `git merge-base`, three-dot `git diff` statistics and name/status output, rename/copy detection, the feature-only log, and `git show <ref>:<path>` for file inspection.

## Selected platform versions

- Laravel Framework 13.39.0
- PHP 8.5.12
- React 19.3.1
- Inertia React 3.9.1 and Inertia Laravel 3.5.2
- Node.js 24.22.1
- TypeScript 5.9.3
- MySQL 8.4.13
- Composer 2.8.12
- Google Auth for PHP 1.55.2

The Composer and npm lock files select the application package versions. The recorded runtime versions come from the built containers.

## Source infrastructure findings

The root source `docker-compose.yml` relies on the root `Dockerfile`, because its alternative Dockerfile line is commented out. The active build therefore uses PHP 8.1. The versioned `docker/7.4`, `docker/8.0`, `docker/8.1`, and `docker/8.2` directories are not selected by that Compose project. Its database image is MySQL 5.7.22. The source also requires an external shared network, declares fixed container names, and bind-mounts `php/local.ini` as a file even though that path is a directory.

## Risk verification matrix

| Risk requested for verification                                                    | Classification | Local evidence                                                                                                           |
| ---------------------------------------------------------------------------------- | -------------- | ------------------------------------------------------------------------------------------------------------------------ |
| Migration references `token_key` before creating it                                | Confirmed      | The feature migration queries the column before the corrective migration adds it.                                        |
| Response fields disagree (`dictionary`, `inferred_dictionary`, `message_in_clear`) | Confirmed      | Prompts, parsing, and consumed fields do not define one consistent contract.                                             |
| Polling omits vocabulary for newly returned messages                               | Confirmed      | The turn cursor applies the previous turn boundary to vocabulary data.                                                   |
| Experiment endpoints are unauthenticated                                           | Confirmed      | Feature API routes do not require authentication or ownership authorization.                                             |
| Throttling is insufficient                                                         | Confirmed      | Only a broad 300 requests-per-minute API throttle was observed; costly execution has no dedicated limit.                 |
| Orchestration and provider logic are concentrated in an oversized command          | Confirmed      | The feature command is 611 lines and owns authentication, HTTP, parsing, orchestration, and persistence.                 |
| Token decoding uses unsafe substring replacement                                   | Confirmed      | Ordinary substrings and cascading replacements can be decoded unintentionally.                                           |
| Five-minute paid execution lacks cost controls                                     | Confirmed      | The recurring schedule is unconditional and has no paid-execution feature flag.                                          |
| Automated tests are missing                                                        | Confirmed      | The feature branch adds no test file.                                                                                    |
| Credential-like documentation or environment values exist                          | Confirmed      | Tracked source examples contain non-placeholder, identifying, credential-like values. Values were not copied or printed. |
| PHP or MySQL versions are obsolete for the new project                             | Confirmed      | The active source images are PHP 8.1 and MySQL 5.7.22.                                                                   |
| Container names are hard coded                                                     | Confirmed      | Every source Compose service declares a fixed container name.                                                            |
| An external Docker network is mandatory                                            | Confirmed      | The shared network is declared `external: true`.                                                                         |
| Mounts reference missing or invalid file types                                     | Confirmed      | `php/local.ini` is mounted as a file but exists as a directory.                                                          |
| Alternative Docker configurations are unused                                       | Confirmed      | None of the four versioned Docker directories is referenced by the active root Compose build.                            |

None of the requested risks remained uncertain after local inspection.

## Behaviour to preserve

- An authenticated user can create a bounded experiment in a pending state and explicitly start it.
- A queued experiment alternates sender and receiver agents for a configured number of turns.
- Every agent message stores its role, turn, symbolic content, and deterministic decoded content.
- Agents may propose new symbols or revise meanings for existing symbols.
- The receiver sees the sender's new symbolic message but does not receive vocabulary proposed by the sender during that same turn. This makes inference observable.
- Vocabulary has a current definition derived from immutable, versioned revisions.
- Vocabulary creation and revision produce append-only events with proposer role and turn.
- Messages link to the exact vocabulary revisions referenced by their symbolic tokens.
- Experiments expose pending, running, completed, and failed lifecycle states.
- An Artisan command dispatches queued work; the command does not contain orchestration or provider logic.
- The application provides experiment index, detail, live updates, active vocabulary, and vocabulary history views.

## Confirmed defects that must not be reproduced

- A source migration reads `token_key` before adding that column.
- Provider prompts require `dictionary`, while consumer code also expects `inferred_dictionary` and `message_in_clear`.
- Turn-based polling filters vocabulary using the previous turn and omits vocabulary belonging to newly returned messages.
- Experiment JSON endpoints are public.
- The only observed throttle is a broad API limit of 300 requests per minute; costly execution has no dedicated limit.
- A 611-line command combines provider authentication, HTTP, orchestration, persistence, parsing, and console output.
- Decoding performs substring replacement, so symbols can be replaced inside ordinary words or through cascading substitutions.
- A paid AI experiment is scheduled every five minutes without a feature flag or cost guard.
- The feature branch adds no automated tests.
- Tracked environment and documentation files contain non-placeholder credential-like and identifying values.
- Active source containers use PHP 8.1 and MySQL 5.7, both unsuitable for this new application.
- Source Compose configuration uses hard-coded container names and a mandatory external network.
- A PHP configuration bind mount names a directory where a file is required.
- Several versioned Docker directories are not used by the root Compose project.
- Provider or parsing failures can leave an experiment in `running` indefinitely.
- Raw model output may be logged after a parsing failure.
- Vocabulary version creation is not protected by a transaction and row lock.

## Unrelated functionality to exclude

Recruitment, developer profiles, articles, courses, payments, currencies, Passport, Stripe, legacy Vue/React assets, source-specific infrastructure, source branding, private hostnames, identifiers, prompts, documentation wording, and Git history are outside the extraction.

## Domain model

- **User** owns experiments.
- **Experiment** belongs to a user and records lifecycle, model, bounded turn count, current turn, timing, and sanitized failure information.
- **Message** belongs to an experiment and is unique by experiment, turn, and role.
- **Vocabulary entry** belongs to an experiment and identifies one canonical symbol.
- **Vocabulary revision** belongs to an entry, is immutable, has a monotonically increasing version, and records meaning, description, proposer role, turn, and source message.
- **Vocabulary event** records creation or revision and is append-only.
- **Message vocabulary revision** links a message to every exact revision referenced through a delimited symbolic token.

## AI contracts

Sender and receiver have separate validated response DTOs but share deliberate field names:

```json
{
    "message": "Text containing zero or more [[symbol]] tokens",
    "vocabulary": [
        {
            "symbol": "letters_digits_or_hyphens",
            "meaning": "plain-language meaning",
            "description": "why this definition is proposed"
        }
    ]
}
```

The sender contract asks for a symbolic message and optional vocabulary proposals. The receiver contract asks it to infer meaning from the visible conversation and prior dictionary, then reply with the same response shape. JSON syntax, fields, types, limits, and symbol format are validated before persistence.

Provider failures, malformed output, missing fields, wrong types, timeouts, rate limits, and interrupted requests terminate the run safely. Logs and stored failure messages must not include credentials, authentication payloads, raw provider bodies, or prompts.

## Jobs and scheduling

- One unique queued job runs one experiment.
- Experiment and job overlap locks prevent concurrent runs.
- Each turn persists atomically.
- Paid execution has a bounded maximum turn count and a dedicated start throttle.
- Recurring dispatch is absent unless an explicit environment feature flag is enabled.
- The disabled-by-default schedule dispatches pending experiments and prevents overlap across workers.
- HTTP generation retries are restricted to explicit transient provider responses. Ambiguous connection failures are not automatically retried because a request may already have incurred cost.

## UI requirements

- Use the official React 19, TypeScript, Inertia 3 starter-kit structure in the Laravel monolith.
- Require session authentication for every experiment route.
- Provide responsive index and detail pages with clear pending, running, completed, and failed states.
- Show chronological sender/receiver messages with separate symbolic and decoded views.
- Show the active dictionary and complete revision/event history.
- Poll incrementally using message and event primary-key cursors.
- Provide accessible loading, empty, retry, and polling-error states.

## Security boundaries

- Policies enforce experiment ownership for viewing and execution.
- Form requests authorize and validate creation and polling input.
- Credentials come only from environment configuration or a mounted secret used by Google Application Default Credentials.
- No service-account JSON, access token, prompt body, or provider authentication response is committed or logged.
- Vertex requests use HTTPS, bounded timeouts, structured output, response-size limits, and sanitized exceptions.
- A public demo is not in scope. If added later, it must be read-only, separately routed, sanitized, seeded, and rate-limited.

## Assumptions and unresolved decisions

- Vertex AI model and region remain environment-configurable because availability depends on the user's Google Cloud project and region.
- Live Vertex authentication, quota, model access, billing, and structured-output compatibility cannot be verified without explicit approval for a billable call.
- Schedule interval defaults to five minutes only when the schedule feature flag is explicitly enabled.
- No public licence is selected. Licence choice remains a publication blocker.
- The application is being assembled in an authorized staging directory because the intended target clone is not present in this environment. Its independent Git history and remote must be reverified after the user moves the completed files.
