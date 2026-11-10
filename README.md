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

## Shared semantic-language experiment scope

> **Project thesis:** A multilingual semantic compiler and routing system that delegates work to small, jurisdiction- and task-specific reasoning models.

The long-term research target is not merely to abbreviate English messages or build one narrow model. It is to test whether multilingual natural-language content can be compiled into a shared, compact semantic language and routed to the most appropriate specialist. Each specialist can remain narrow while the complete system becomes broadly capable by adding experts for new domains, jurisdictions, and tasks.

### Proposed architecture

```text
user's natural language
    -> 1. semantic compiler
    -> compact typed symbols
    -> 2. domain, jurisdiction, date, task, and risk router + matter plan
    -> 3. orchestrated micro-agent team + authoritative versioned knowledge
    -> typed semantic answer with evidence and confidence
    -> 4. deterministic target-language renderer
    -> 5. optional constrained readability model
    -> user-facing natural language
```

1. **Semantic compiler:** The language-specific front end identifies entities, relationships, actions, time, quantities, negation, certainty, responsibility, and conditions, then emits a canonical typed representation. Exact dictionary or `str_replace`-style substitution is a useful fast path only after the intended meaning is known. Raw word replacement alone changes the alphabet but preserves ambiguity, grammar, and word order. Compact symbols should be native tokens in the specialist model's tokenizer; visually short strings are not necessarily token-efficient. Grammar-based interlingua research demonstrates reversible language-specific parsing and generation through shared abstract syntax, particularly in controlled domains. [Grammatical Framework](https://aclanthology.org/2020.cl-2.6/)
2. **Router and matter orchestrator:** A typed policy or small routing model selects the domain, jurisdiction, effective date, task, risk level, required sources, and one or more specialists. The orchestrator converts the request into a shared objective and matter plan, assigns bounded tasks, records dependencies, and synthesises only verified outputs. Deterministic rules should handle known cases; learned routing should handle ambiguous cases with confidence, abstention, and escalation. Routing research shows that model selection can reduce cost, but incorrect routing remains a first-class failure mode. [RouteLLM](https://arxiv.org/abs/2406.18665)
3. **Collaborative specialist team:** The router may call one small expert or assemble several micro-agents, each trained for a bounded responsibility and operating on the compact language. Each agent retrieves authoritative, versioned sources rather than relying only on memorised law, policy, or procedure, and returns typed conclusions, supporting evidence, effective dates, and confidence. Agents do not need to understand every natural language or unrelated domain directly.
4. **Deterministic renderer:** A grammar- and template-driven decoder converts the typed result into the target language while preserving entities, dates, quantities, conditions, responsibility, legal modality, uncertainty, and citations. It should not reinterpret the specialist's conclusion.
5. **Constrained readability model:** This optional model improves fluency only. It must not add, remove, or alter claims and should be followed by a semantic consistency check. Where deterministic rendering is sufficiently natural, this stage should be omitted to reduce cost and risk.

#### Legal matter team example

Jurisdictional separation is a routing boundary, not a one-agent limit. A legal matter can be handled like a large firm's matter team:

- a **legislation-in-force agent** retrieves statutes and regulations, geographical extent, commencement, amendments, and the point-in-time text applicable to the matter;
- a **precedent and application agent** retrieves analogous judgments and separates binding holdings from obiter, factual analogy, exceptions, and adverse treatment;
- a **facts and evidence agent** creates a provenance-backed chronology, distinguishes alleged, admitted, and proved facts, and identifies missing evidence and burdens;
- a **procedure and remedies agent** checks forum, deadlines, required steps, available remedies, and local procedural rules;
- opposing **claimant/applicant and defendant/respondent agents** construct the strongest grounded arguments and identify adverse authority or distinguishing facts;
- an independent **verification agent** checks every material claim against its source, provision or paragraph, jurisdiction, authority level, operative date, and later treatment, and can veto unsupported synthesis;
- the **matter orchestrator** reconciles verified findings against the shared objective without inventing a new legal conclusion. A qualified lawyer remains the final decision-maker.

For an England-and-Wales implementation, the legislation agent should consume official point-in-time legislation and effects data from [legislation.gov.uk](https://www.legislation.gov.uk/openapi), while the case-law agent should use approved judgments and metadata from [The National Archives Find Case Law](https://caselaw.nationalarchives.gov.uk/about-find-case-law). The equivalent authoritative services should be selected for every supported jurisdiction.

New law should normally update the evidence layer, not trigger nightly model-weight retraining. A safer update pipeline is: pull official sources; store immutable snapshots and checksums; normalise them into a versioned legal graph or index; run change, citation, and regression checks; obtain human review for high-impact changes; and publish the new evidence snapshot atomically. Periodic retraining or fine-tuning can improve reasoning and tool use after curated evaluation, but embedding every legal change into weights is harder to audit, reproduce, and roll back.

Case relevance should not be learned from arbitrary repetition weights. Ranking should expose legally meaningful factors such as jurisdiction, court hierarchy and binding status, operative date, subsequent treatment, cited provision, procedural posture, and factual similarity. A separate agent should actively search for contrary, distinguished, or overruled authority.

Early legal-agent research supports multiple perspectives but does not prove that every agent team is superior. One study found comparable aggregate performance with distinct answers and cases solved by multi-agent methods that monolithic baselines missed, and vice versa; another reflective specialist design improved abstention and reduced fabricated or misattributed factors in its tested scenarios. [Investigating Multi-Agent Deliberation in Law](https://arxiv.org/abs/2606.30906), [Reflective Multi-Agent Legal Argument Generation](https://arxiv.org/abs/2506.02992)

Multiple role prompts using the same underlying model are not automatically independent experts: they may repeat or amplify the same error. Agents therefore need separate evidence scopes, typed contracts, independent retrieval, an adversarial challenge round, and a verifier with authority to block synthesis. The complete team must be compared with a single-agent baseline on accuracy, unsupported-claim rate, routing error, latency, compute, energy, and human-review time.

The encoders and renderers are the only components that require natural-language capability. Specialist reasoning models should learn the compact language from the beginning. The compact language must be compositional and typed rather than a collection of arbitrary abbreviations, and symbol definitions must be immutable within a version.

### Why this could outperform one enormous model

One general model spends capacity learning every supported language, domain, jurisdiction, writing style, and unrelated body of knowledge. This architecture attempts to separate shared language handling from specialist reasoning and activate only the capability needed for the current request.

- **Specialisation can improve data and compute efficiency:** Branch-Train-Merge independently trained experts for domains including legal and scientific text. Its 64-domain, 22.4-billion-parameter aggregate matched a conventional Transformer trained with 2.5 times more compute, and the reported gains depended on genuine domain specialisation rather than random data splits. [Branch-Train-Merge](https://arxiv.org/abs/2208.03306)
- **Task routing can produce smaller deployable networks:** Google's Task-level Mixture-of-Experts extracted per-task subnetworks instead of serving every expert for every request. In multilingual translation experiments it reported 1.9 times greater peak throughput across 30 language pairs and 2.6 times greater throughput at 200 language pairs while remaining competitive with token-level expert routing. [Task-level Mixture-of-Experts](https://research.google/pubs/beyond-distillation-task-level-mixture-of-experts-for-efficient-inference/)
- **External routing can avoid unnecessary premium-model calls:** RouteLLM and FrugalGPT demonstrate that learned routing or cascades can choose between models according to task difficulty and cost. FrugalGPT reported matching its strongest individual model with substantially lower cost in its evaluated workloads, although those savings are benchmark- and pricing-dependent and must not be assumed for this project. [RouteLLM](https://arxiv.org/abs/2406.18665), [FrugalGPT](https://arxiv.org/abs/2305.05176)
- **Jurisdiction is a routing boundary, not a one-agent limit:** A legal request can activate a coordinated matter team for legislation-in-force, precedent, facts and evidence, procedure and remedies, adversarial analysis, and verification without activating every foreign legal system. The agents share one objective but retain bounded responsibilities and independent evidence checks. Narrower training still does not guarantee truth: legal-model performance varies across jurisdictions, courts, time periods, and cases, so authoritative retrieval, citations, effective dates, abstention, and professional review remain necessary. [Large Legal Fictions](https://arxiv.org/abs/2401.01301)
- **Small experts may be deployable near the user:** Bounded specialist models are better candidates for customer-controlled or low-resource hardware, reducing network dependence and exposure of sensitive inputs. Whether a particular expert fits and performs adequately must be demonstrated on the target device, not inferred from parameter count alone. [MobileLLM](https://arxiv.org/abs/2402.14905)
- **Experts can be updated independently:** A changed statute, medical protocol, or operational policy can update the relevant knowledge store or expert without retraining every language and unrelated domain. This is a proposed lifecycle advantage; experiments must measure update cost, compatibility, and regression risk.
- **A shared semantic layer can remove repeated surface variation:** Multiple languages and phrasings can map to one operational meaning, allowing specialist training to focus on decisions and relationships rather than repeatedly learning every linguistic expression. The saving exists only if semantic compilation preserves the required meaning and the encoder, renderer, validation, and routing costs do not consume it.

The comparison is therefore not “small model versus large model” in isolation. It is the complete routed system versus a strong general-model baseline on accuracy, hallucination rate, tokens, training compute, inference cost, latency, memory, energy, update cost, and failure handling.

### Experiment success criteria

An experiment is successful only if it demonstrates all of the following:

- equivalent meaning survives encoding and decoding;
- the router selects the correct domain, jurisdiction, effective date, task, and specialist, or safely abstains;
- the selected specialist or micro-agent team can complete the task using only the compact representation and authorised knowledge sources;
- collaboration improves the result after counting orchestration, duplicated work, verification, retries, and correlated-error risk;
- the semantic answer retains evidence, provenance, uncertainty, and applicable dates;
- the representation is smaller in actual model tokens, not merely visible characters;
- the saving remains after counting compiler, router, every specialist agent, orchestration, renderer, optional readability model, vocabulary/schema, retrieval, validation, retries, and fallback overhead;
- the result is cheaper or more efficient than direct multilingual processing without unacceptable latency or error increases.

Experiments should begin with controlled, repetitive domains such as maintenance coordination before testing regulated domains. They must measure semantic fidelity, multilingual parity, routing accuracy, source-grounded task accuracy, token counts, latency, failure rate, and fallback behaviour. A symbolic conversation that is shorter on screen but changes a term's meaning is a failure, not a successful compression result.

This scope does not assume that every human meaning can be represented losslessly or that one universal semantic language will replace natural languages. The research question is whether a verified shared representation and routed specialist system can deliver broader practical coverage through a collection of narrow experts while using less total computation and preserving the decisions and relationships in the source content.

### Potential commercial value

If the approach achieves verified semantic fidelity and lower end-to-end cost, its strongest commercial opportunity is specialised AI infrastructure rather than a universal replacement for large language models.

#### Potential benefits and the conditions behind them

- **Lower compute, energy, cooling, and hardware demand:** If specialists achieve the required quality, routing work away from an oversized general model could reduce calculations, model-weight and KV-cache memory, accelerator count, electricity use, and cooling demand per resolved task. This is a hypothesis to prove, not a consequence of shorter text or parameter count alone: sequence length, batching, quantisation, utilisation, hardware, retries, and serving strategy all matter. [Energy Considerations of Large Language Model Inference](https://aclanthology.org/2025.acl-long.1563/) found that serving choices materially changed energy use in its tested workloads, so this project must benchmark the complete routed stack.
- **Lower latency:** Fewer reasoning tokens and a smaller model may reduce prefill and decode time. The encoder, decoder, validation, and queueing overhead could offset that gain, so latency must be measured end to end at realistic concurrency.
- **Private and on-premise deployment:** A compact domain model may fit more easily on customer-controlled infrastructure, reducing data transfer and cloud dependence. This is commercially valuable for hospitals, councils, financial services, and regulated organisations, but it does not remove the need to secure and update the encoder and decoder.
- **One reasoning model for multiple languages:** Shared semantic representations could allow one domain model to serve several languages through language-specific adapters or encoders. Related multilingual research has demonstrated shared representation spaces and parameter reuse across many languages, but this project must prove parity for its own semantic language rather than assume it. [Google Universal Speech Model](https://arxiv.org/abs/2303.01037)
- **More consistent and auditable business decisions:** Typed entities, relationships, obligations, deadlines, and workflow states can make decisions easier to inspect than unconstrained prose. This benefit depends on schema validation, immutable vocabulary versions, provenance, and tests for negation, uncertainty, and responsibility; a compact code without those controls is not auditable.
- **Domain-specific products:** The strongest initial markets are repetitive workflows with controlled vocabularies and explicit decisions: maintenance coordination, insurance claims, logistics, invoice processing, finance operations, legal intake, and customer support. These domains provide measurable success criteria and repeated concepts; open-ended conversation is a weaker first market.
- **Reduced model-training cost:** One compact domain model could replace several separately trained language-specific task models. This saving must include encoder/decoder training, parallel or translated data, ontology maintenance, retraining after schema changes, and multilingual quality evaluation. Distillation and pruning already provide independent routes to smaller models, so the semantic layer must outperform those baselines rather than receive credit for model compression alone. [Compact Language Models via Pruning and Knowledge Distillation](https://arxiv.org/abs/2407.14679)
- **Licensing and platform revenue:** The licensable assets could include the semantic-language specification, encoders and decoders, domain ontologies, validators, evaluation suites, and compact reasoning models. A defensible product would sell measurable workflow outcomes and deployment controls, not merely a list of abbreviations.
- **Infrastructure savings for AI providers:** If independent tests show lower total cost per successful task, providers could use the architecture to improve margins or offer cheaper plans. This claim requires production-like measurements and comparison with strong baselines including ordinary multilingual models, better tokenisers, caching, batching, quantisation, distillation, and semantic deduplication. [Deduplicating Training Data Makes Language Models Better](https://arxiv.org/abs/2107.06499)

#### Environmental and physical-resource impact

AI is physical infrastructure, not only software. The International Energy Agency estimated that data centres consumed about 415 terawatt-hours of electricity in 2024, around 1.5% of global electricity consumption, and projects roughly 945 terawatt-hours by 2030 in its base case. Accelerated servers, driven mainly by AI adoption, account for almost half of the projected increase. Cooling, storage, networking, power conversion, and backup infrastructure add to server demand. [IEA: Energy demand from AI](https://www.iea.org/reports/energy-and-ai/energy-demand-from-ai)

Model size also creates a direct memory and accelerator burden. As an illustrative deployment calculation, AWS estimates that FP16 weights alone require approximately 14 GB for a 7-billion-parameter model and 140 GB for a 70-billion-parameter model, before KV cache, activations, framework overhead, and concurrency. Models that exceed one accelerator's memory require additional devices or model partitioning, increasing power, cooling, networking, and infrastructure requirements. [AWS: Right-sizing an inference system](https://docs.aws.amazon.com/prescriptive-guidance/latest/gen-ai-inference-architecture-and-best-practices-on-aws/right-sizing-and-auto-scaling.html)

The environmental boundary extends beyond electricity:

- data centres consume water directly for cooling and indirectly through electricity generation;
- semiconductor fabrication consumes water and energy;
- accelerators, servers, memory, cooling equipment, and grid infrastructure require minerals and manufactured materials;
- rapid hardware replacement creates embodied emissions and electronic waste.

The United Nations Environment Programme therefore recommends lifecycle assessment covering energy, water, mineral consumption, emissions, infrastructure production, and electronic waste rather than reporting training electricity alone. [UNEP: Environmental impact of the full AI lifecycle](https://wedocs.unep.org/handle/20.500.11822/46288)

If specialists achieve equivalent task quality, activating a few small agents instead of one oversized general model could reduce calculations, resident model weights, KV-cache memory, accelerator count, electricity, and cooling per resolved task. At sufficient scale, avoiding or delaying additional accelerator capacity could also reduce semiconductor manufacturing, embodied carbon, water use, critical-material demand, and electronic waste. Task-specific smaller models have produced substantial energy reductions in controlled experiments, but those results are workload-specific rather than a guaranteed conversion from parameter count to energy. [UNESCO: Smarter, Smaller, Stronger](https://unesdoc.unesco.org/ark:/48223/pf0000394521)

The micro-agent architecture can also increase consumption if it runs unnecessary agents in parallel, keeps many models resident, retries failed routes, retrains too frequently, or makes usage grow because each request becomes cheaper. Environmental improvement must therefore be measured for the complete system per successfully resolved task at equivalent quality, not inferred from the size of one component.

Environmental experiments should report:

- wall-socket kilowatt-hours for the compiler, router, every agent, retrieval, renderer, validation, retries, networking, and cooling;
- GPU-seconds, accelerator count and utilisation, peak VRAM, system RAM, KV-cache memory, throughput, and latency;
- direct cooling water and electricity-related water using location-specific factors;
- location-based and market-based carbon-dioxide-equivalent emissions;
- amortised training, development, hardware-manufacturing, and end-of-life impacts;
- accuracy, unsupported-claim rate, and human-review time alongside every resource measurement.

#### Commercial validation requirements

The likely product is a multilingual semantic-computing platform: business communication is converted into a typed intermediate language, routed to one small specialist or an orchestrated micro-agent team, checked for fidelity and contradictions, and returned in the user's language. The first commercial claim should remain narrow and measurable:

> For defined multilingual workflows, reduce total inference cost while preserving task meaning, operational accuracy, privacy, and auditability.

Validation must compare the full pipeline against direct multilingual processing and report:

- tokens, latency, peak VRAM and RAM, GPU-seconds, accelerator utilisation, wall-socket energy, water, and emissions per successful task;
- compiler, router, every specialist agent, retrieval, renderer, vocabulary/schema, validation, retry, orchestration, and fallback costs;
- semantic fidelity, multilingual parity, task accuracy, and high-risk error rates;
- model-update, ontology-versioning, monitoring, and support costs;
- whether the system remains useful when the input is ambiguous or outside the domain.

Shorter prompts or visible abbreviations alone are not a commercial result. The strongest first proof of value is a repetitive, expensive workflow with a controlled vocabulary and explicit decisions. Smaller models are not automatically better reasoners: recent distillation work finds that reasoning capability can be difficult to transfer to small models, so each domain must be evaluated rather than assumed to benefit. [Small Models Struggle to Learn from Strong Reasoners](https://arxiv.org/abs/2502.12143)

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

| Risk                                                                                   | Classification | Clean-room response                                                                                  |
| -------------------------------------------------------------------------------------- | -------------- | ---------------------------------------------------------------------------------------------------- |
| A migration reads `token_key` before the column exists                                 | Confirmed      | Fresh final-state migrations are ordered by dependency.                                              |
| AI fields disagree between `dictionary`, `inferred_dictionary`, and `message_in_clear` | Confirmed      | Sender and receiver use explicit DTOs with one validated field schema.                               |
| Turn polling omits vocabulary associated with newly returned messages                  | Confirmed      | Polling uses message and event IDs and always returns the current vocabulary.                        |
| Experiment endpoints lack authentication and ownership checks                          | Confirmed      | Routes require authenticated, verified users and policies enforce ownership.                         |
| Expensive execution lacks a dedicated throttle                                         | Confirmed      | Manual starts have a dedicated rate limiter.                                                         |
| Provider and orchestration logic live in an oversized command                          | Confirmed      | Provider, validation, orchestration, vocabulary, job, and command responsibilities are separate.     |
| Decoding performs unsafe substring replacement                                         | Confirmed      | Only exact, delimited `[[symbol]]` tokens are decoded, without cascading replacement.                |
| Paid execution runs every five minutes without cost controls                           | Confirmed      | Paid execution and recurring scheduling have separate disabled-by-default flags.                     |
| The feature has no automated tests                                                     | Confirmed      | Backend, HTTP, database, command, job, schedule, fake-provider, and frontend checks were added.      |
| Tracked examples contain credential-like or identifying values                         | Confirmed      | Only placeholders are present; provider credentials remain external.                                 |
| Active PHP and MySQL versions are obsolete for the new application                     | Confirmed      | The new images pin PHP 8.5.12 and MySQL 8.4.13.                                                      |
| Compose uses fixed container names                                                     | Confirmed      | Compose-generated names are used.                                                                    |
| Compose requires an external network                                                   | Confirmed      | The new project uses its default internal network.                                                   |
| A mounted PHP configuration path is a directory instead of a file                      | Confirmed      | Every referenced configuration file is committed with the correct type.                              |
| Multiple versioned Docker directories are unused                                       | Confirmed      | One Dockerfile and one committed configuration tree define the new stack.                            |
| Provider failures can leave experiments running                                        | Confirmed      | Exceptions transition the experiment to a terminal failed state with sanitized details.              |
| Raw provider output may be logged                                                      | Confirmed      | Raw bodies, prompts, credentials, and authentication payloads are not logged.                        |
| Vocabulary revisions are not concurrency-safe                                          | Confirmed      | Transactions, row locks, unique constraints, retry bounds, and overlap locks protect revision order. |

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

| Command                           | Result                                                                                    |
| --------------------------------- | ----------------------------------------------------------------------------------------- |
| `composer test`                   | Passed Pint on 109 PHP files, PHPStan with zero errors, and 75 tests with 267 assertions. |
| `php artisan test --compact`      | 75 tests passed with 267 assertions.                                                      |
| `npm run check`                   | 76 files correctly formatted; 67 files had no lint warnings or errors.                    |
| `npm run types:check`             | Passed with no TypeScript errors.                                                         |
| `npm run build`                   | Passed; 2,337 modules transformed and a production manifest was generated.                |
| `composer audit --no-interaction` | No security vulnerability advisories found.                                               |
| `npm audit --audit-level=high`    | Zero vulnerabilities found.                                                               |
| Editor diagnostics                | No errors found.                                                                          |

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
