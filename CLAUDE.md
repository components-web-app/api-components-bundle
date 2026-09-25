# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository. It holds the rules and traps that still apply. The history of how each was found is in git, the issues and `CHANGELOG.md`, so it does not belong here.

## Working Principles

### Principle of least exposure
Only expose API fields and serialization groups when they are concretely needed by a consumer. Add serialization only when there is a real, tested requirement for it.

### No code comments
Do not write explanatory comments in code. The behaviour and the reason for a change belong in the test that covers it — a named Behat scenario or PHPUnit test method — not in prose beside the implementation. Remove narrative comments from any file you touch. Keep file licence headers and type-carrying annotations (`@var`, `@param`, `@return`, `@template`) that static analysis or the framework needs.

### Public readability is reachability from a live route
> *"If a user should be able to load that component because the route that it is part of exists and is available now to the public. Simple as that in the theory of it."* — Daniel

A resource is publicly readable **if and only if** it is reachable from a Route that **exists** and is **live now**. Test every security decision against that rule, not against what the voters currently do. "Live now" is `RouteVoter`'s job (it folds in `liveAt` and `route_security`), so `is_granted('read_route', $route)` is the complete answer for one route; reachability is the question of *which* routes reach a resource.

Both directions are enforced (#225): a routeless page with a live routed descendant **is** public; a page nothing routes to is **not**, however it is reached in the object graph.

### Abstention is a decision, not a neutral one
An abstaining voter has not "stayed out of it": under `AffirmativeStrategy`, all-abstain is a denial (`allowIfAllAbstainDecisions` defaults to `false`). This has caused four bugs:

- `RouteVoter::supports()` returned false when `route_security` was unconfigured, so every routed page 401'd for everyone (#224).
- `ComponentVoter` granted when all three sub-votes abstained, so a component in an unrouted page was public (#225).
- `SiteConfigParameterVoter::supports()` needs a `SiteConfigParameter`. A plain `security:` on a `Post` runs with no object, so every voter abstains and even admins are denied. Use `securityPostDenormalize:` (#232).
- `voteByPageTemplate` abstains only for a `Page` with no page data at all, so a routeless *template* page and a routeless *plain* page are not interchangeable in a regression test (#225).

**Before relying on a voter, state what happens when it abstains**, and write the scenario that pins it. `supports()` returning false is abstention, not denial; never put a configuration check there.

### TDD process
1. Explain what we're about to do and why, with a proposed test (Behat scenario or PHPUnit test)
2. Agree on whether the test is correct (Daniel is the author and has deep system knowledge — expect discussion)
3. Write or adjust the test, watch it fail, then write the code to make it pass
4. Keep CLAUDE.md current throughout, but record **rules and traps**, not the story of the change
5. Add a line to `CHANGELOG.md` under **Unreleased** in the same PR (see below)
6. After committing, log user-facing changes in the **Pending Documentation Review** table in `/Users/danielwest/Documents/GitHub/_CWA/docs/CLAUDE.md`

### Changelog and releases
`CHANGELOG.md` is maintained continuously and is the source of every release's notes.

- **Every PR that changes behaviour, config, dependencies or tooling adds a line under `## Unreleased`**, in the matching `### Breaking` / `### Added` / `### Changed` / `### Fixed` / `### Tooling` group. One short line, ending with a link to the PR (`[#123](https://github.com/components-web-app/api-components-bundle/pull/123)`), or to the commit for a direct push.
- **Tagging a release:** in the release commit, rename `## Unreleased` to `## [<tag>](https://github.com/components-web-app/api-components-bundle/compare/<previous-tag>...<tag>) - YYYY-MM-DD` and add a fresh empty `## Unreleased` above it. Then push the tag.
- Pushing a tag runs `.github/workflows/release.yml`, which extracts that section with `.github/scripts/changelog-section.sh` and creates the GitHub release (a pre-release when the tag contains `-`) or replaces an existing release's notes. **It fails if the changelog has no section for the tag**, so a tag cannot be released without one.

---

## Overview

`components-web-app/api-components-bundle` is a Symfony bundle providing the API layer for the CWA (Components Web App) framework: a component-driven page structure over API Platform, route generation, security, file uploads and real-time push via Mercure.

- **CWA Nuxt Module** (`@cwa/nuxt`), the front end: `/Users/danielwest/Documents/GitHub/_CWA/cwa-nuxt-3-module`. Keep shared concepts in sync (serialization groups, resource types, nested page conventions).
- **CWA docs site**: `/Users/danielwest/Documents/GitHub/_CWA/docs` (`content/4.api/` bundle, `content/5.nuxt-module/` module).
- **CWA template**: `components-web-app`. All traffic enters through the API pod's Caddy, which proxies to Nuxt and caches HTML in Souin. API Platform routes are imported under `/_api`.

## Commands

```bash
php -d memory_limit=256M vendor/bin/phpunit
php -d memory_limit=256M vendor/bin/behat
php -d memory_limit=-1 -d pcov.enabled=1 vendor/bin/behat --profile=default-coverage   # writes build/logs/behat/clover.xml
vendor/bin/php-cs-fixer fix                                                              # before every commit; CI blocks on CS
vendor/bin/phpstan analyse                                                               # level 5 against phpstan-baseline.neon

php tests/Functional/app/bin/console -e test doctrine:database:create
php tests/Functional/app/bin/console -e test doctrine:migrations:migrate --no-interaction
php tests/Functional/app/bin/console -e test doctrine:schema:validate
```

Prefer Behat scenarios for API behaviour and unit tests for pure logic. **Infection scores from PHPUnit coverage only**, so a class reached only by Behat gets no mutation scrutiny; give new classes unit tests too. The covered-MSI gate is 80.

## Testing and CI traps

- **Behat coverage:** `CoverageContext` writes Clover XML straight to `build/logs/behat/clover.xml`. Do not reintroduce `.cov` + `phpcov merge` (current phpcov cannot read the format installed here). Populate the filter with `Filter::includeFiles()` over individual files; `includeFile()` on a directory records nothing. From php-code-coverage 14, report writers take `$coverage->getReport()`. CI fails under 1000 covered statements.
- **`Assert::*` fatals in Behat contexts** on failure (PHPUnit's `Exporter` needs a TextUI Configuration Behat never bootstraps). Throw plain exceptions in step definitions.
- **Do not boot a kernel in PHPUnit tests.** Debug boot registers Symfony's ErrorHandler, PHPUnit reports Risky, and Infection's initial run fails on Risky, which breaks the mutation gate. Load config files into a bare `ContainerBuilder` and assert on definitions instead; it also avoids autoconfiguration masking a missing tag.
- **The first Behat scenario of a run can execute against the previous container** after a service definition change. When a red/green check disagrees with an isolated run, run it twice.
- **The Behat app runs the Varnish xkey purger (space-joined), production runs Souin (`', '`, chunked at 1500).** Never assert an exact purge or tag header; use `ProfilerContext::collectPurgedTags()` / `collectResponseTags()`, which split on `/[,\s]+/`.
- **The test app imports routes with no prefix.** `RoutePrefixContext` boots a second kernel with a prefix (`the API routes are imported under the prefix :prefix`) for the scenarios that need `/_api`.
- **Test doubles with static switches** (`HubStub`, `MockClientCallback`, `UnreachableDatabaseMiddleware`) are set by a Given step and cleared in `ProfilerContext`'s before/after hooks. Static because the kernel can reboot between requests. Logs are asserted through the `app.monolog.test_handler` Monolog `TestHandler`, because the harness runs with `debug: false` and the profiler's logger collects nothing.
- **Newly covering a large declarative file costs MSI**, because its mutants start counting. Answer with assertions on the wiring, not exclusions.
- **PHPUnit in CI is `simple-phpunit`**, using `SYMFONY_PHPUNIT_VERSION` from `phpunit.xml.dist` / `phpunit.coverage.xml.dist`. Keep it on the same major as `phpunit/phpunit` in `composer.json`.
- **PHPStan baseline:** fixing a baselined finding means regenerating with `vendor/bin/phpstan analyse --generate-baseline phpstan-baseline.neon`; a stale entry fails the job. `src/Resources/skeleton` is excluded.
- **Infection is not a Composer dependency** (it needs `justinrainbow/json-schema ^6`, the behatch fork needs `^5`). CI downloads the signed phar at `INFECTION_VERSION`; PHPUnit 13.3 needs 0.34.2+. `--only-covered` no longer exists.
- **Behat on Symfony 8:** `behat/behat` 3.x and `mink-extension` 2.x cap six Symfony components at `^7.0`, which pins the test environment's http-kernel, framework-bundle and friends to 7.4. Bundle code is 8.x-compatible. Watch for stable behat 4 / mink-extension 3.

### CI must not depend on network downloads it can avoid

A gate that fails when a host is slow gets re-run until a real failure is waved through. Check whether it is already on disk or can be committed.

| Fetch | Fix |
|---|---|
| Infection's signing key from a keyserver | committed at `.github/infection-signing-key.asc` (#239) |
| PHPUnit XSD from `schema.phpunit.de` (Infection validates the config via libxml) | `phpunit*.xml.dist` point at `vendor/phpunit/phpunit/phpunit.xsd` (#272) |
| `behatch/contexts` fork via the GitHub REST API (68 calls per cold resolve, repository-wide 1,000/h limit already authenticated) | `"no-api": true` on the repository so Composer uses `git clone` (#272) |

`Could not authenticate` from Composer means any 401/403 from GitHub, including rate limiting. Reproduce with `composer update -vvv` against empty `COMPOSER_CACHE_DIR`/`COMPOSER_HOME`. To prove a step makes no network calls, run it under `sandbox-exec` with `network-outbound` denied.

---

## Architecture

### Core entities (`src/Entity/Core/`)

| Entity | Role |
|--------|------|
| `Route` | Maps a public URL path to a `Page` or `PageData`. The publication mechanism. |
| `AbstractPage` | Base of `Page` and `AbstractPageData`. Holds `$parentPage`, `$parentPageData`, `$title`, `$metaDescription`, and `$route` (the owning side: FK is `page.route_id` / `abstract_page_data.route_id`). |
| `AbstractPageData` | Project page data (e.g. `ConferenceData`) extends this. `$page` is the `Page` template to render. |
| `Page` | A page template. Holds a `Layout` and `ComponentGroup`s. |
| `Layout` | Page shell (header, footer). |
| `ComponentGroup` | Ordered `ComponentPosition`s in a page, layout or component. |
| `ComponentPosition` | A slot holding one component, or a dynamic `pageDataProperty` resolved from page data. |

### Page hierarchy and routes

- **Routes are the publication mechanism.** Page data is editable by IRI before it has a Route; a Route is created when it goes public. So hierarchy lives on `AbstractPage` (`$parentPage` / `$parentPageData`), never on `Route`: a parent must be settable before either page has a URL.
- **Parent = nested, always.** There is no `$nested` flag. Two FKs, because `AbstractPage` is a mapped superclass and cannot be an FK target. An `Assert\Expression` forbids setting both. `AbstractPage::validateNoCircularParent()` guards cycles on the API, but fixtures, `CwaFixtureBuilder` and SQL bypass it, so every hierarchy walk carries its own visited-id set.
- **An ancestor with no Route is skipped, never a boundary.** This applies to liveness (#224), reachability (#225), children listings and path cascades (#256). `getParentPageRoute()` returning null means "keep walking".
- **Route generation** (`RouteGenerator::create()`): slugify the title, prefix the parent route's path, suffix to resolve conflicts, set the route on the page. **It refuses (`UnroutedParentException`, 422 on `POST /_/routes/generate`) when a parent exists but has no route** (#245): the child would squat the parent's natural path. Explicit route creation is unaffected.
- **Path concatenation is recommended, not required.** Rendering depth comes from the manifest, not from the URL.
- `PATCH /_/routes/{id}` with `cascadeChildPaths: true` rewrites descendant paths and creates redirects. `GET /_/routes/{id}/children` is admin-only and unfiltered by publication. Both walk page-to-page, so a routed grandchild under an unrouted page is included. The children walk needs a visited set; the cascade does not (its prefix check terminates it, and a visited set would be unreachable code Infection reports).

### Route publication — `liveAt` (#224, #234)

`Route.liveAt` (admin-only): past = live, future = scheduled, `null` = draft with the URL reserved. The constructor and the column's `CURRENT_TIMESTAMP` default keep existing routes live; going draft is explicit.

- **Effective date = the latest `liveAt` in the chain** of routed ancestors, resolved at request time by `RouteLiveResolver` and memoised per request. A routed ancestor with `null` gates everything below it. Nothing is stored. Admins read it from `_metadata.effectiveLiveAt`.
- **Collections** filter on it through `RouteAncestorGateResolver`: one native recursive CTE returning gated route ids, applied as `NOT IN` so pagination and `totalItems` stay right (`PublishableExtension`'s shape). One self-reference with `LEFT JOIN` + `COALESCE` over both parent types, because PostgreSQL rejects two recursive branches. `UNION` (not `ALL`) terminates cycles. Table and column names come from `ClassMetadata`, so the table prefix is honoured. Verified identical on SQLite, MySQL 8, MariaDB 10.11 and PostgreSQL 16; MySQL 5.7 and MariaDB < 10.2 cannot run it.
- **The extension and the voter answer different questions, deliberately.** SQL answers which rows an anonymous caller may list; `RouteVoter` also applies `route_security`, which is per-token and cannot go into SQL.
- **Public status for a gated route is 404.** `RouteVoter` still returns false; `UnpublishedRouteExceptionListener` rewrites 401/403 to 404 on the main request. The `NotFoundHttpException` **must not carry the original as `previous`**, or the firewall's `ExceptionListener` finds it and converts back to 401. A component reachable only through a gated route stays **401**, because `ComponentVoter` uses a sub-request the rewrite skips; `route_schedule.feature` pins that boundary.
- Redirects to a gated target still return the redirect, but `RouteNormalizer` does not reflect the target's page IRIs, and gated nodes are pruned from `redirectedFrom` for non-admins.
- **The cache cap uses a global `MIN(liveAt)`** (`RouteRepository::findNextLiveAt()`). Every effective transition is some route's own date, so it can only expire early. That it is global is the cascade guarantee: a go-live changes navigation on pages that never reference the route.
- **Never put `#[Silverback\Publishable]` on `Route`.** It is a draft/published pair and would add twin FKs and rewrite item queries. The shared scheduling predicate is `Utility\PublicationDate`.

### Route reachability (#225)

A Route reaches its own page **and every ancestor** through `parentPage`/`parentPageData`. `RouteReachabilityResolver` finds, for a routable with no route, any descendant route that passes `RouteVoter` (two-repository `findBy`, visited-id set, early exit, memoised). Used by `RoutableVoter` (which also checks page data using a `Page` as template) and `ComponentVoter::voteByRoute`.

- `ComponentVoter::voteByRoute` abstains (public) for a component in **no** page and denies for a component in pages none of which are reachable, falling through to `routable_security` so admins still see it. Denying both broke 86 scenarios with unplaced components.
- Reachability is not denormalised. A column cannot express `route_security`, and an edge table was built and rejected (maintained state, rebuild command, upgrade step). A routeless page is absent from `GET /_/pages`.

### Manifest — `GET /_/resource_manifest/{id}`

`{id}` starting with `/` resolves a Route by path; a UUID resolves a `Page` or `AbstractPageData` (admin/draft). Owned by the `ResourceManifest` DTO so `RoutingPrefixResourceMetadataCollectionFactory` does not collide with entity routes. It is a performance requirement for admin as well as public rendering: it replaces 4+ serial round trips with one parallel batch.

- **Shape:** `resource_iris` is an array indexed by rendering depth (root first); each element is a tree `{ "iri": string, "children": [...] }` (#197). Depths split on `parentPage`/`parentPageData`. No per-node metadata; the front end derives types from IRIs (#198 closed).
- Built by `ResourceManifestNormalizer` with `ManifestDepthGroupTrait`. Blank nodes (`/.well-known/genid/`) and `/_/resource_metadatas` are excluded via `ManifestIriFilterTrait::shouldSkipIri()`, shared with `CwaTagCollector` so the body and the tag header cannot disagree.
- **A relation is embedded only when the related resource has a field in `Route:manifest:read`.** `Route.page/pageData`, `AbstractPageData.page`, `AbstractPage.route/parentPage/parentPageData`, `Page.componentGroups`, `Layout.componentGroups` (#306) and `AbstractComponent.componentGroups` (#309) carry it.
- **`Layout::getComponentGroups()` keeps `readableLink: false`** so `GET /layouts` returns bare IRIs (the module needs that). `LayoutManifestNormalizer` expands them only in the manifest context. **A layout is listed once, at the shallowest depth using it**, because the module maps IRI → depth and a deeper duplicate would get the wrong `path` header. Only layouts are deduplicated: a shared template `Page` appears per depth because its dynamic positions resolve per depth.
- `pageDataProperty` positions resolve during manifest generation through the `cwa_current_page_data` context key set by `PageDataNormalizer`. Draft components are excluded for anonymous callers.
- The manifest body contains only IRIs; each follow-up fetch is voter-checked.

### Caching

Resources are fetched and cached **individually**. Never embed related data outside the manifest; return IRIs. A write purges that resource's tags, not everything referencing it.

**Tag grammar, three shapes** (none contains a comma or space, so all are separator-safe):
1. A **resource IRI** (`/…`, or an absolute URL under `ABS_URL`).
2. A **singleton flag**: only `cwa-html`, the rendered-HTML tag. `HttpCachePurger::RENDERED_HTML_TAG` is a cross-repo contract with the module's `RENDERED_HTML_SURROGATE_KEY`; a mismatch fails silently.
3. A **grouping key** `<kind>:<resource-iri>`. The only one is `manifest:<entity-iri>`.

- **Manifest tags (#227):** `CwaTagCollector` is registered as `api_platform.http_cache.tag_collector` (the primary id AP references; bundle id and FQCN are aliases). Defining it replaces AP's default for every resource, so its default branch reproduces AP's behaviour exactly (keyed by IRI), minus blank nodes and resource metadata. For a manifest it emits only `manifest:<AbstractPage IRI>` per depth, detected by `CwaTagCollector::MANIFEST_CONTEXT_KEY` (not `$context['operation']`, which JSON-LD's normalizer unsets on the DTO → resource transition). Keyed by the page entity, not the Route, so path- and UUID-addressed manifests share it. **A component content edit purges no manifest**; membership changes do. `ManifestKeyResolver` walks upward from a written `Route`/`AbstractPage`/`Layout`/`ComponentGroup`/`ComponentPosition` (breadth-first, visited set, `WeakMap` memo); its type switch is the trigger list. A page data manifest also carries its template page's key, so one template write drops all of them. A content edit on a `pageDataProperty`-bound component still purges, because `PropagateUpdatesListener` gathers the owning page data.
- **Cache-safety headers (#200):** `CacheHeadersEventListener` marks authenticated responses for `http_cache.personalised_resource_classes` (default `Route`, `ResourceManifest`, `ComponentPosition`, plus any Publishable) as `private, no-store`. Anonymous responses stay `public`. The gate is an authenticated token, not a cookie. No `Vary: Cookie`.
- **Scheduled expiry (#227):** the same listener caps `s-maxage`/`max-age` at the next global `liveAt` for `http_cache.scheduled_expiry_resource_classes` (default `Route`, `RoutableInterface`, `ResourceManifest`), and at the response's `Expires` when sooner (RFC 9111 gives `s-maxage` precedence, so `Expires` alone is ignored by shared caches). It runs at `POST_RESPOND - 1` because `PublishableEventListener` writes `Expires` at `POST_RESPOND`. Publishable collections get no `Expires`. A scheduled go-live is not a write, so no tag can fire for it; expiry is the only mechanism.
- **Rendered HTML (#232):** a write to a class in `http_cache.purge_rendered_html_classes` (default `SiteConfigParameter`, matched with `is_a`) adds `cwa-html` once per flush. Listed classes must be association-free, because associated resources are also collected. A purge fires once per Doctrine flush; do not build a debounce.
- **Explicit purges:** `silverback:api-components:purge-rendered-html` / `POST /_/rendered_html/purge` send only `cwa-html` (#243), never touching collected state. `silverback:api-components:purge-http-cache` / `POST /_/http_cache/purge` flush the whole cache via Souin's `PURGE <invalidation-url>/flush` (#290). Only a `SouinPurger` can flush; otherwise the command exits 0 and the endpoint returns 501. **Build the flush URL as `rtrim(url, '/') . '/flush'`**, never relative. There is no API-only flush, because HTML is built from API data. Both endpoints are `ROLE_ADMIN`, `input: false`, `output: false`, 204, and need `read: true` (otherwise `PlaceholderAction` has no `$data`). Console commands are the deploy path, run with `kubectl exec` in the API pod, since Souin's admin API is bound to localhost there. They accept any Content-Type, so they rely on the JWT cookie's `SameSite=Lax` against CSRF.
- **Purge failures (#311):** after a write, a failed purge is logged at error and swallowed, and later propagators (Mercure) still run. `reset()` runs in a `finally`. An explicit purge fails loudly: exit 1, or 502. Catch only HttpClient's `ExceptionInterface` and AP's `RuntimeException` (tag too long), never `\Throwable`. `SurrogateKeysPurger` throws inside `purge()` itself, because the discarded response's destructor runs there.
- **Route write tags (#313):** `<route import prefix>/_/routes` plus `…/_/routes/<path>` (e.g. `/_api/_/routes//my-route`), and the old path's IRI on a path change. The tags are identical from HTTP and CLI. A sub-directory deployment needs `framework.router.default_uri` for CLI purges to match.
- Response tag headers are unbounded; the limit is the proxy's buffer (a 502 at the proxy, never truncation).

### Mercure

- `symfony/mercure` ^0.7.1 || ^0.8 and the bundle are runtime requirements (#270). `PublishableAwareHub` mirrors `Debug\TraceableHub`: implements `RemoteHubInterface` and forwards every method including 0.8's `getProtocolVersion()`/`getCookieName()`.
- **A failed publish after commit is logged, not a 500** (#283). Per update, catch only Mercure's `RuntimeException` and HttpClient's `ExceptionInterface`. An invalid JWT (`InvalidArgumentException`) is configuration and still throws.
- Subscribe topic templates come from the operation's registered route path (so they include the import prefix), with a trailing `.{_format}` converted back to `{._format}` (#304).

### Security and users

- **User email links** (#316): `RefererUrlResolver` uses `Origin`, else `Referer`, reduced to `scheme://host[:port]`, only if it matches `user.email_links.allowed_origins` (anchored by the bundle as `{\A(?:p)\z}i`), else `default_origin`, else the link is refused. The request host is not implicitly trusted. Request input may only be a `RelativeUrlPath`. **Never build an outbound link from a request header without an allow-list.**
- **A refused email link depends on which kind of email it is** (#326). Where the email is the job (password reset, both resends), the request is a 400 that changes nothing: build the email before flushing the token it carries. After a completed write (every email from `UserEventListener::postWrite()`), the write keeps its status and `UserMailer::afterWrite()` logs the refusal at error; the verify and change-email emails serve both kinds, so the listener must call their `…AfterWrite` variants. Only `UnparseableRequestHeaderException` is caught.
- Actions calling `UserDataProcessor::findUserByUsername()` must catch its `InvalidArgumentException` and return 404; it throws rather than returning null.
- **Code typed against `UserRepositoryInterface` may call only what it declares** (#266). The `@method` tags are not a contract. `loadUserByIdentifier()` matches username *or* email, so check the returned username.
- `user:create` throws `ValidationFailedException` rather than writing an invalid or duplicate user (#254). There is no DB unique constraint on username or email.
- Security routes: one path each. A duplicate path fails nothing and one route silently never runs; check with `debug:router`.
- `GET /_/health` (#312) is a plain Symfony route (not an AP operation, which would add JSON-LD, cache headers and tags), `no-store`, 200 or 503 with the DBAL message logged at warning and never returned. It is imported by `routing/all.php`.

### Forms

- `/submit` has PATCH and POST only. **POST is a full submit** (`clearMissing = true`) and can succeed (#276). **PATCH is partial and validate-only**, never firing `FormSuccessEvent` (#251), because the module's live validation PATCHes every field.

### Uploads

- Stored as `<slug>-<token>.<ext>` with a `fileExists()` regeneration loop (#194); data-URI uploads keep a UUID name.
- Each `UploadableField` needs a **distinct `property:`**; `UploadableAttributeReader` throws when two share one (#199). Imagine runs only on raster images.
- `requiredOnPublish` adds a class-level `RequiresUploadedFile` in the groups publishing validates: `{ShortName}:published`, or the class's custom `#[Publishable(validationGroups:)]`, which replace it (#193, #252).
- `urlGenerator: 'public'` asks the Filesystem (honouring the tag's `public_url` config) and falls back to `api` only on `UnableToGeneratePublicUrl` (#257).
- A missing source image skips that imagine filter with a warning (#299). A Behat reproduction must warm the `_acb_file_info` cache first.
- **Deleted-file markers are keyed per resource** in a `WeakMap` on `UploadableFileManager`, and transferred draft → published on merge. A property-name key leaked across requests in worker mode and deleted unrelated files.
- **Never delete a stored file before its replacement is in place, and never delete a path the resource still references.** `mergeDraftIntoPublished()` snapshots paths, copies, then calls `deleteOrphanedFiles()`. `copyFilepath()` keeps the prefix and returns the original path when the source is missing.
- `CwaFixtureBuilder` persists a `File` set on an uploadable fixture via `persistFiles()`, gated on `isConfigured()` (#195).

### Components

- `#[Silverback\ExplicitAllowOnly]` (#196): the type may only be placed in a group whose `allowedComponents` lists its collection IRI, enforced on both direct and dynamic positions. Exposed as `explicitAllowOnly: true` on the Hydra `supportedClass` (a locked contract; absent means false).
- `allowedComponents` matches by class-level (collection) IRI.
- `ComponentPosition` moves repair `sortValue` collisions only where they occur, never by renumbering, because every change is a Mercure update (#278).
- `ComponentPosition.component` is `ON DELETE SET NULL`, deliberately, so dynamic positions survive their fallback. Static positions are removed by `ComponentPositionEventListener` on an API delete only.
- `createdAt` is restored in `TimestampedNormalizer::denormalize()` (#213). `OBJECT_TO_POPULATE` is not always an instance of `$type` (a `PersistentCollection` when denormalizing a collection property), so gate on `instanceof`.

### Orphaned resource report (#190)

| Endpoint | Purpose |
|---|---|
| `POST /_/orphaned_resources/scan` | Refresh. `ROLE_ADMIN`, no body, 202. Dispatches `ScanOrphanedResourcesMessage` on `messenger.default_bus`. **It runs synchronously unless the application routes the message to an async transport**, like the mailer's `SendEmailMessage`; with no bus at all the processor runs the handler itself. |
| `GET /_/orphaned_resources` | `ROLE_ADMIN`. The last stored report: `generatedAt` plus IRI lists `componentGroups`, `componentPositions`, `components`. **404 until a scan has stored one.** `private, no-store`. |

- **It reports, it never deletes.** Deletion is the normal `DELETE` per IRI, whose listeners cascade. Do not call `OrphanedResourceHelper` from the scan.
- **An orphan is anything not linked into the CWA tree:** a group with no page, layout or component owner; a position with neither a component nor a `pageDataProperty`; a component in no position and in no page data component property. An application's own Doctrine relations to a component do not count as use. **A draft is never listed**; its published version is judged by its own usage.
- **`OrphanedResourceDetector` is three DQL queries, whatever the site size.** Page data component properties come from Doctrine metadata (owning single-join-column associations on `AbstractPageData` subclasses to an `AbstractComponent`, not inherited) and drafts from each publishable class's own association. Each becomes a `NOT IN` subquery in the component query, never a new query. Names come from Doctrine, so the table prefix is honoured.
- **Never hydrate the orphaned components.** A published component's inverse one-to-one `draftResource` is loaded eagerly, so hydration costs one query per orphan. The query selects `c.id` and a `CASE WHEN c INSTANCE OF … ELSE … END` type (deepest class first; DQL requires the `ELSE`), and IRIs come from `getReference()`, which queries nothing.
- Verified identical on SQLite, MySQL 8.0.46, MariaDB 10.11.19 and PostgreSQL 16.15 on a fixture with each orphan kind, drafts and page data. About 20 ms and 3 queries for 5,000 components, 1,000 groups and 5,000 positions on SQLite.
- **The report lives in `cache.app`** (`OrphanedResourceReportStore`) as plain arrays, never on a shared service, so it needs no `kernel.reset`.
- **The GET must stay `private, no-store`.** A new scan is a cache write, not a Doctrine write, so nothing purges a shared-cache copy.
- The POST needs `read: true` like the purge endpoints. Anonymous POST 401 in the test app is the firewall's; the `@loginUser` 403 is the operation's own security.

### Filters (#289, #237)

Route, Layout and Page declare `QueryParameter`s: `search` = `FreeTextQueryFilter(new OrFilter(new PartialSearchFilter()))`, `order[:property]` = `SortFilter`, Page `isTemplate` = `ExactFilter` + `BooleanQueryValue` (casts to `'1'`/`'0'`; an array of booleans binds `false` as `''`). `OrSearchFilter` is removed.

- **Never use `orWhere()` in a filter.** It ORs against the whole accumulated WHERE and discards publication, draft and ancestor predicates. `OrFilter` must always be wrapped in `FreeTextQueryFilter`. Apply a filter's clauses as one `andWhere(Orx)`.
- `PartialSearchFilter` inner-joins nested properties, which removes rows inside an OR search, so `layout.reference` was left out of the Page search.

### API Platform and Symfony compatibility

- **API Platform `^4.4 || ^5.0`, with no version branches in code** (#287). Nothing in `src/` may use a 5-only API. Denormalization error statuses differ between versions, deliberately not normalised; a test asserting one must hold on both.
- All AP metadata is in PHP attributes; `prependApiPlatformConfig()` registers mapping directories. Serializer groups use `Symfony\Component\Serializer\Attribute\Groups`.
- `RoutingPrefixResourceMetadataCollectionFactory` prefixes `AbstractComponent` subclasses with `/component/`, `AbstractPageData` with `/page_data/`, and other bundle classes with `/_/`. It **combines** with an existing `routePrefix`, so never set one on such a class.
- The bundle prepends `use_symfony_listeners: true` (without it `GET /_api/me` 500s). `ApiPlatformCompilerPass` **appends** AP's default `exception_to_status` entries, read from AP's config tree, because the bundle's prepended map replaced them (#293). Appended, so an application's more specific mapping wins.
- `/me` keeps `@type: User` and `@context: /contexts/User`.
- 4xx/5xx from AP's exception handler are `application/problem+json`.
- Symfony 8 constraints need named arguments (`new Count(min: 1)`).
- Prefer `?string = null` with a nullable column, so null reaches `NotBlank` on every Symfony version.
- Set `$context['api_sub_level'] = true` when normalising a sub-object, or `PartialCollectionViewNormalizer` injects `view` into arrays whenever the URI has a query string.

### Dependency injection conventions

- Services use `silverback.api_components.*` ids with FQCN aliases, **except** AP state providers/processors referenced by class on an operation and `controller.service_arguments` actions. Those keep the **FQCN as primary id**, or AP throws `ProviderNotFoundException` and argument injection fails.
- **Never rely on autoconfiguration, registration order or decoration inference in a bundle.** Tag and order explicitly; several definitions use `->autoconfigure(false)`.
- **Services holding request-scoped state must be tagged `kernel.reset`** explicitly, or scope the state with a `WeakMap`. FrankenPHP worker mode keeps services alive across requests. Add each such service to `tests/DependencyInjection/ServicesResetterTest.php`.
- Optional collaborators (logger, purger) are wired as `new Reference(..., NULL_ON_INVALID_REFERENCE)`.
- **Config nodes:** never put `isRequired()` on a child of a node with `addDefaultsIfNotSet()` or `canBeDisabled()`. The default is inserted without finalising, so the check never runs (#214). Give the child a real default or make the parent required. Use `->validate()` for invalid combinations. Env placeholders skip node validators, so validate again at run time.
- `make:rename-component` generates through `doctrine.migrations.dependency_factory` and reads table names from `ClassMetadata`. **Never `LIKE`-match an IRI in a `json` column** (slashes are escaped). AP's `getIriFromResource()` returns a `/.well-known/genid/` blank node, not an exception, for a non-resource class.

---

## CwaFixtureBuilder

A fluent API over Doctrine fixtures. `AbstractCwaScaffold` implements `FixtureInterface` and is auto-tagged; implement `build(CwaFixtureBuilder $cwa)`. Unit tests: `tests/Fixture/CwaFixtureBuilderTest.php`. Fixture loads must keep purge-and-reload.

```
CwaFixtureBuilder
  ->layout(ref, uiSuffix, ?uiClassNames): LayoutBuilder      (deduped by ref; prepends 'CwaLayout')
  ->page(ref, uiSuffix, layout, ?route, ?routeName, isTemplate=false, ?Closure, ?uiClassNames): PageBuilder  (prepends 'CwaPage')
  ->pageData(AbstractPageData, ?template, ?route, ?routeName, ?Closure): PageDataBuilder
  ->component(AbstractComponent): ComponentBuilder
  ->getRoute(routeName): Route
  ->redirect(path, to: routeName, ?name): static             (a redirect Route to a named route; registered by its own name)
  ->afterRoutes(Closure(CwaFixtureBuilder)): static          (runs once, after routes exist and before positions are created)
LayoutBuilder    ->group(name, allow: [], ?Closure, ?locationReference): GroupBuilder, ->uiClassNames(...)
PageBuilder      ->title(), ->metaDescription(), ->uiClassNames(), ->group(name, ?Closure, ?locationReference, allow: []), ->liveAt(?DateTimeImmutable), ->nested(Closure), ->getRoute()
PageDataBuilder  ->liveAt(?DateTimeImmutable), ->nested(Closure), ->onRoutesCreated(Closure(array<PageBuilder>)), ->getRoute()
ComponentBuilder ->uiComponent(suffix), ->uiClassNames(...), ->group(name, allow: [], ?Closure)
GroupBuilder     ->add(AbstractComponent, ?sort), ->pageDataPosition(pageDataClass, propertyName, ?sort)
```

- **Routes:** an explicit `route:` creates that path. With no `route:`, a non-template page is generated from its title, and a template page gets none. A nested page is generated under its parent, and **a parent with no route throws `UnroutedParentException`**, so pass an explicit `route:`. A top-level entity with no route and no title is left as a draft.
- **Flush phases:** persist layouts/pages/page data → flush → groups → flush → `RouteGenerator` breadth-first, parents first (`nested()` closures run here), then `liveAt` and redirects → flush → `onRoutesCreated` and `afterRoutes` callbacks → flush → positions and components → final flush. `getRoute()` is valid only after route generation, which is why anything referencing a route (a navigation link, a draft) is set in `afterRoutes`. Each phase flushes only when it did something; the unit tests pin those counts, so do not add an unconditional flush.
- `PageBuilder::group()` takes `allow:` last so existing positional closure calls keep working.
- `onRoutesCreated` may only mutate entities already persisted (set on the page data before `->pageData()` so phase one cascades them); it never calls `persist()`.
- The builder handles timestamping, persisting, dedup of layouts/pages and groups, position sort values (×10), bidirectional links and parent propagation. `allow:` takes class names and resolves collection IRIs.

### `generate-fixtures` (#189, #321)

`silverback:api-components:generate-fixtures` writes a scaffold that reloads as the same site. `features/fixtures/generate_fixtures_round_trip.feature` is the authority: it builds a site with the builder, generates, purges, loads the generated file and compares. Add a scenario there for anything new the generator must carry.

- The generated code uses numbered `$c[]` / `$g[]` variables and named arguments, never positional arguments after named ones.
- Component and page data fields come from Doctrine metadata, not public properties. Each is written through its public property, else its setter. Identifiers, timestamps and uploadable storage fields are skipped; values equal to the property default are left out.
- Stored files are copied to `assets/` beside the output file, without their token, and loaded back through `new File(...)`.
- Route references and drafts go in `$cwa->afterRoutes()`. A draft is linked with its publishable association and passed to `$cwa->persist()`.
- `allowedComponents` IRIs are mapped back to component classes through the IRI converter.
- A group whose reference is not `<name>_<location>` is emitted with a `locationReference`, and its positions only once.
- Only non-live `liveAt` values (future or null) are emitted.
- Anything it cannot express is listed in the command output ("could not be reproduced"), never exported with `var_export`. Keep it that way: a scaffold that silently drops content is worse than one that says so.
- Read entities through `initializeObject()` first: `ClassMetadata::getFieldValue()` does not initialise a lazy object, so a lazily loaded component reads as empty.

> **The bundle strips mapped superclasses from its JOINED roots' discriminator maps** (#323). `AbstractComponent` and `AbstractPageData` declare no discriminator map, so Doctrine's `addDefaultDiscriminatorMap()` includes every subclass the driver knows, including an application's `#[ORM\MappedSuperclass]` base, which has no table. `MappedSuperclassDiscriminatorMapListener` runs on `loadClassMetadata`, which Doctrine dispatches after building the default map, and removes those classes from `discriminatorMap` and `subClasses`. It detects a mapped superclass the way Doctrine's own `peekIfIsMappedSuperclass()` does: it loads the candidate through the mapping driver into a fresh `ClassMetadata`. That works for every driver and never goes back through the metadata factory, which would recurse into the root still being loaded. `isTransient()` cannot tell them apart, because it is false for both entities and mapped superclasses. Abstract `#[ORM\Entity]` bases have tables and stay in the map. `AbstractDummyAppComponent` is a mapped superclass so the round-trip feature covers this.

---

## Open issues

- **#186 — page-level `publishedAt` on `AbstractPage`. Do not start this** (Daniel, 2026-08-14). Component permission inheritance, the front-end draft/live UX and the hero-component state conflict are unresolved, and building the API side first would decide them. `liveAt` (#224) complements it but cannot express "draft page on a live URL".
- **#222 — config guards still carrying the #214 pattern:** `user.class_name`, `refresh_token.*`, `publishable.permission`, `refresh_token.options.class`. Each fix may force configuration on existing applications (a BC break). Verify each empirically before deciding.
- `make:page-data --properties a b` (space-separated) fails in Symfony's input binding before the maker runs; comma-separated and repeated options work.
- Uploads: no field-level "generic file vs image" flag yet (#199 item 3).
- Component cloning in the module (cwa-nuxt-module #157) must respect `explicitAllowOnly`.
- Not verified: what Souin's flush does on a shared Redis storer (template #85).
