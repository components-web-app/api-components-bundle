# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Working Principles

### Principle of least exposure
Only expose API fields and serialization groups when they are concretely needed by a consumer. Add serialization only when there is a real, tested requirement for it.

### No code comments
Do not write explanatory comments in code. The behaviour and the reason for a change belong in the test that covers it — a named Behat scenario or PHPUnit test method — not in prose beside the implementation. Remove narrative comments from any file you touch. Keep file licence headers and type-carrying annotations (`@var`, `@param`, `@return`, `@template`) that static analysis or the framework needs.

### Public readability is reachability from a live route
> *"If a user should be able to load that component because the route that it is part of exists and is available now to the public. Simple as that in the theory of it."* — Daniel

A resource is publicly readable **if and only if** it is reachable from a Route that **exists** and is **live now**. That is the yardstick for every decision in the security area — test a proposed rule against it rather than against what the voters currently do. "Live now" is `RouteVoter`'s job and already folds in both the `liveAt` publication gate and `route_security`, so `is_granted('read_route', $route)` is the complete per-request answer for one route; reachability is the question of *which* routes reach a resource.

The rule cuts both ways, and both directions are enforced (#225): a routeless page with a live routed descendant **is** public, because a visitor on the descendant's URL needs it to render; a page nothing routes to is **not** public, however it is reached in the object graph.

### Abstention is a decision, not a neutral one

A voter or guard that abstains has not "stayed out of it" — the access-decision strategy decides what abstention means, and in this codebase it has meant opposite things in different places. This has produced four separate bugs, each found by accident:

- **`RouteVoter::supports()`** returned false when `route_security` was unconfigured, so every voter abstained and `AffirmativeStrategy::decide()` denied via `allowIfAllAbstainDecisions` (default `false`) — every routed page 401 for everyone, silently (#224).
- **`ComponentVoter`** returned true when all three sub-votes abstained, so a component in a page nothing routes to was public (#225).
- **`SiteConfigParameterVoter::supports()`** requires `$subject instanceof SiteConfigParameter`. A plain `security:` expression on a `Post` runs before denormalization with no object, so every voter abstains and the write is denied — including for admins. `securityPostDenormalize:` is required instead (#232).
- **`voteByPageTemplate`** abstains only when a `Page` has no page data at all, which is why a routeless *template* page was already protected while a routeless *plain* page leaked its components — the two shapes are not interchangeable in a regression test (#225).

**Before relying on a voter, state what happens when it abstains**, and write the scenario that pins it. If a guard is meant to deny, returning `null` is not how to do it; if it is meant to stay silent, confirm the strategy agrees. `supports()` returning false is abstention, not denial — the most common way to get this wrong is to put a configuration check there.

### TDD process
All feature work follows this cycle:
1. Explain what we're about to do and why, with a proposed test (Behat scenario or PHPUnit test)
2. Agree on whether the test is correct (Daniel is the author and has deep system knowledge — expect discussion)
3. Write or adjust the test, then write the code to make it pass
4. Keep CLAUDE.md current throughout — update mid-task if the design shifts, not just at the end
5. After committing, log any user-facing changes in the **Pending Documentation Review** table in `/Users/danielwest/Documents/GitHub/_CWA/docs/CLAUDE.md` so the docs project can decide whether to document them

---

## Overview

`components-web-app/api-components-bundle` is a Symfony bundle that provides the API layer for the CWA (Components Web App) framework. It exposes a flexible, component-driven page structure via API Platform, handles route generation, security, file uploads, and real-time push via Mercure.

Companion project: **CWA Nuxt Module** (`@cwa/nuxt`) — the frontend that consumes this API. Local source at `/Users/danielwest/Documents/GitHub/_CWA/cwa-nuxt-3-module`. The two projects must be kept in sync on shared concepts (serialization groups, resource types, nested page conventions).

**CWA documentation site** — covers the API bundle, Nuxt module, and template in one place. Local source at `/Users/danielwest/Documents/GitHub/_CWA/docs` (Nuxt Content). Sections: `content/4.api/` for bundle docs, `content/5.nuxt-module/` for module docs. Update this site when adding user-facing features.

## Commands

```bash
# Unit tests
php -d memory_limit=256M vendor/bin/phpunit

# Integration tests (Behat)
php -d memory_limit=256M vendor/bin/behat

# Behat with code coverage (needs pcov; writes build/logs/behat/clover.xml)
php -d memory_limit=-1 -d pcov.enabled=1 vendor/bin/behat --profile=default-coverage

# Database setup for tests
php tests/Functional/app/bin/console -e test doctrine:database:create
php tests/Functional/app/bin/console -e test doctrine:migrations:migrate --no-interaction
php tests/Functional/app/bin/console -e test doctrine:schema:validate
```

Behat features live in `features/`. PHPUnit tests in `tests/`. Behat coverage is more extensive than unit — prefer adding Behat scenarios for new API behaviour, unit tests for pure logic.

**Behat coverage convention**: `CoverageContext` (registered in the `default-coverage` profile) writes Clover XML **directly** to `build/logs/behat/clover.xml`, which Codecov consumes as-is. Do not reintroduce an intermediate `.cov` + `phpcov merge` step — current `phpcov` releases only read php-code-coverage's newer serialization format, which the version installed here cannot write. The filter must be populated with individual file paths (`Filter::includeFiles()` over a file iterator); `Filter::includeFile()` given a directory silently records nothing. CI fails the Behat job if the report has fewer than 1000 covered statements.

## Architecture

### Core entities (`src/Entity/Core/`)

| Entity | Role |
|--------|------|
| `Route` | Maps a public URL path to a `Page` or `PageData`. The publication mechanism — a page has no public URL until a Route exists for it. |
| `AbstractPage` | Base class for `Page` and `AbstractPageData`. Holds `$parentPage`, `$parentPageData`, `$title`, `$metaDescription`, and a `$route` back-reference. |
| `AbstractPageData` | Extends `AbstractPage`. Project-specific page data entities (e.g. `ConferenceData`) extend this. Adds `$page` — the `Page` template to render. |
| `Page` | A named page template entity. Holds a `Layout` reference and `ComponentGroup` references. |
| `Layout` | A named layout entity (wraps a page in a shell — header, footer, etc.). |
| `ComponentGroup` | An ordered list of `ComponentPosition`s within a page or layout. |
| `ComponentPosition` | A slot holding one component instance. |

### Route lifecycle (important)

**Routes are the publication mechanism.** A `PageData` entity exists and is editable via the admin before it has a `Route`. Draft pages are accessed via internal admin URLs using the entity IRI directly. A `Route` is created only when the page is ready to go public.

This is why parent/child hierarchy lives on `AbstractPage` (via `$parentPage`/`$parentPageData`) and NOT on `Route`:
- You need to set the parent relationship during drafting, before either the parent or child has a public URL
- `RouteGenerator` reads `getParentPageRoute()` (computed from `$parentPage?->getRoute() ?? $parentPageData?->getRoute()`) at route-generation time to construct the correct prefixed path
- Moving hierarchy to `Route` would mean you can't establish parent/child until both pages are already published

### Route publication — `liveAt` and the effective date (#224)

A Route's *existence* is no longer the whole publication signal. `Route.liveAt` (nullable, admin-only via `#[ApiProperty(security:)]`) adds three states:

| `liveAt` | Meaning |
|---|---|
| a past date | live |
| a future date | scheduled — publicly invisible until that moment, then live with no further action |
| `null` | draft / taken offline, URL reserved |

> **Migration note.** Applications generate their own migrations. `route.live_at` is the only column this feature adds. An application that already generated a migration against an earlier build of #224 also created `route.effective_live_at`, which no longer exists — regenerate, or hand-write a migration dropping it. No released version carried it, so no BC promise is broken.

**Existing behaviour is preserved by defaults, not by semantics.** `Route::__construct()` sets `liveAt` to now and the ORM column carries `options: ['default' => 'CURRENT_TIMESTAMP']`, so an application's generated migration backfills existing rows and every existing call site (`RouteGenerator`, `POST /_/routes`, `CwaFixtureBuilder`, fixtures, `DoctrineContext`) still produces a live route. Going draft is an explicit act.

**The effective date is inherited down the page hierarchy**, resolved by `RouteLiveResolver::resolveEffectiveLiveAt()` (`src/Helper/Route/RouteLiveResolver.php`):

- Walk `Route → page/pageData → parentPage ?? parentPageData` upwards.
- An ancestor **that has a Route** contributes that route's `liveAt`. If it is `null`, the whole chain is not live.
- An ancestor **with no Route contributes nothing and is skipped** — it is not a publication gate. `getParentPageRoute()` returning null means "keep walking", never "not live". This matters: the nested-pages design rests on a parent being editable before it has a public URL, so a routed child under an unrouted template page must stay live.
- Effective = the **latest** date collected, including the route's own. A parent at 2999 gates a child at 2000; a child at 2999 gates itself under a live parent.
- The walk is cycle-safe with a visited-id set, mirroring `AbstractPage::validateNoCircularParent()`. Do not rely on that validator for protection — fixtures, `CwaFixtureBuilder` and direct SQL all bypass it.

**The effective date is resolved at request time, never stored.** `liveAt` is the only persisted value: what an admin authored on that route. The effective answer is derived by the walk above, memoised per request, and asked for by `RouteVoter` when it gates and by `MetadataNormalizer` when an admin needs to display it.

An earlier implementation denormalised it into a `Route.effectiveLiveAt` column maintained by an `onFlush` listener. That was removed: the codebase already answers "is this reachable" by traversing at request time (`ComponentVoter` has always done so), and a second, inconsistent mechanism for the same class of question was not worth the maintained state. The column's only real justification had been a flat SQL predicate for collection filtering, which the recursive CTE below supplies without any stored state.

**Admins read the effective date from `_metadata`, not a property.** `ResourceMetadata::$effectiveLiveAt` (group `cwa_resource:metadata`) carries it, resolved from the same memo the voter uses so the chain is walked once. It is derived state, so it is not a mapped column and not writable. It is admin-only by construction rather than by an added check: a non-admin cannot retrieve a non-live route at all.

**Collections filter on the inherited date too (#234).** `RouteExtension` and `RoutableExtension` once tested a route's own date only, so an anonymous `GET /_/routes` listed a route whose own date had passed but whose parent was still scheduled. That mattered more than an ordinary listing inconsistency because `GET /_/routes` is the **sitemap source**: it does not fetch what it lists, it publishes it, so every affected URL went to search engines as a soft-404. The module could not filter them either — `liveAt` and `_metadata.effectiveLiveAt` are both admin-only, so an anonymous sitemap build has no signal at all.

`RouteAncestorGateResolver` (`src/Helper/Route/RouteAncestorGateResolver.php`) closes it with **one native recursive CTE** returning the ids of routes gated by an ancestor; `andWhereNotGated()` then applies `NOT IN` against that id set. This is `PublishableExtension`'s shape — exclude a set, so pagination and `totalItems` stay correct — with the id set coming from one extra query instead of a DQL subquery, because unbounded ancestor recursion is the one thing DQL cannot express.

- **The predicate is the simple form of the rule, not a re-derivation of the effective date.** Effective is the *latest* date in the chain, so it is active exactly when *every* date in the chain is non-null and past. The CTE therefore only has to find chains containing one non-active routed ancestor; the route's own date stays with `PublicationDate::andWhereActive()`.
- **The CTE walks parent pointers, not routes.** Anchor row per route = its page's/page data's parent pointer; each iteration replaces the pointer with the grandparent's. A `LEFT JOIN` to both `page` and `abstract_page_data` with `COALESCE` keeps it to **one** self-reference, which PostgreSQL requires — two recursive branches (one per parent type) is the obvious shape and PostgreSQL rejects it.
- **An ancestor with no Route is still skipped**, per #224: the final join only gates on ancestors that actually have a route. The `#[ORM\OneToOne]` is owned by `AbstractPage`, so the FK is `page.route_id` / `abstract_page_data.route_id` — there is no `page_id` on the `route` table.
- **Cycles terminate** by `UNION` (distinct), not `UNION ALL` — a repeated pointer row is dropped and the recursion ends. Mirrors the visited-id set in `RouteLiveResolver`.
- **No per-request memoisation and no cached state.** The resolver holds nothing between calls, so it needs no `kernel.reset` tag and cannot leak across requests under worker mode.
- Table and column names come from `ClassMetadata` (`getTableName()`, `getSingleAssociationJoinColumnName()`), so the `_acb_` table prefix and any application override are honoured rather than hardcoded.

> **Portability was verified, not assumed.** The exact generated statement was run against **SQLite 3.43.2 / 3.53.4 (the harness), MySQL 8.0.46, MariaDB 10.11.19 and PostgreSQL 16.15** on identical fixtures covering flat, one-level, two-level, unrouted-ancestor, null-`liveAt`-ancestor and cyclic hierarchies. All four returned the identical gated set, so **no platform branch is needed** and none exists. Recursive CTEs require SQLite 3.8.3+, MySQL 8.0+, MariaDB 10.2+ or PostgreSQL — the bundle's whole supported range. **Not exercised:** MySQL 5.7 and MariaDB 10.0/10.1, which have no CTE support at all and on which this query cannot run.

> **Extension and voter answer different questions — the asymmetry is deliberate.** The query extension answers *which rows may this anonymous caller see*; the voter answers *may this caller read this resource*. The CTE does **not** replace the per-request traversal in `RouteVoter`, and must not try to: `RouteVoter` folds in `route_security`, which is per-token and path-matched and cannot go into SQL. Both are needed, they overlap on the `liveAt` chain, and that overlap is the cost of the split.

**The cache cap uses `MIN(liveAt)`** (`RouteRepository::findNextLiveAt()`). That is safe without the column: an effective date is always the *latest* date in a chain, so every effective transition is some route's own `liveAt`, and the minimum over own dates is never later than the earliest real transition. It can expire a cached response slightly early, never too late.

**That the minimum is global, not per-resource, is the cascade guarantee — not a shortcut.** An anonymous response is capped to the next go-live moment whether or not it references the scheduled route, which is exactly what a navigation bar needs: the route going live changes pages that never mention it, and no surrogate key can express that because a scheduled transition is not a write. Which responses get the cap is the config list `http_cache.scheduled_expiry_resource_classes` (default `[Route, RoutableInterface, ResourceManifest]`); see **#227** below.

### Route reachability — which routes make a resource public (#225)

Publication answers *is this route live*; reachability answers *which routes reach this resource*. Together they are the invariant above.

A Route reaches its own page **and every ancestor of that page** through `parentPage`/`parentPageData`. That is the whole rule: rendering `/conference/programme` requires the parent's template, groups and components, so the child's Route is what makes them public.

**Resolution is by traversal at request time**, matching how `ComponentVoter` has always answered the same question — no denormalised state, no maintained table, no upgrade step. `RouteReachabilityResolver` asks, for a routable with no route of its own, whether any descendant has a Route that passes `RouteVoter`. Descendants are found with the two-repository `findBy(['parentPage' => …])` / `findBy(['parentPageData' => …])` pattern that `RouteChildrenStateProvider` already uses, so no inverse collection is added to the mapping. The walk is cycle-safe with a visited-id set and early-exits on the first granted route; results are memoised per request.

Consumers:
- `RoutableVoter` — grants when the resource's own route passes `RouteVoter`, or when the resolver finds a reaching route that does; for a `Page` it also checks the `AbstractPageData` instances using it as a template, since a template is reached through its page data rather than through the hierarchy.
- `ComponentVoter::voteByRoute` — the same question for each page a component sits in.
- `RoutableExtension` — the joined route's own liveness predicate, plus the inherited gate from #234. A routeless page is absent from `GET /_/pages`, which is where it was before #225: reachability-by-descendant is a voter answer, and it needs `route_security`, so it stays out of SQL.

**Why not denormalise it.** An "earliest live date among reaching routes" column would have made the collection filter trivial, but it cannot express `route_security`, which is per-token and path-matched — an anonymous visitor would be granted a page reachable only via `/user-area/...`. Storing the *edges* instead was tried and rejected for a different reason: it introduced a maintained join table, a rebuild command and an upgrade step for a question the existing architecture already answers by traversal.

**Public status is 404, not 401/403.** `RouteVoter` still returns `false`, so the voter chain is untouched; `RouteStateProvider` / `ResourceManifestStateProvider` flag the request and `UnpublishedRouteExceptionListener` (main request + cacheable method only) rewrites 401/403 to 404.

> The rewritten `NotFoundHttpException` **must not** carry the original exception as `previous`. Symfony's firewall `ExceptionListener` walks the `getPrevious()` chain looking for an `AccessDeniedException`/`AuthenticationException`, finds it, and converts the response straight back to 401.

**Known and accepted boundary:** a component reachable only via a gated route still returns **401**, not 404. `ComponentVoter` reaches the route through a `SUB_REQUEST` (`ComponentVoter.php`), which the rewrite deliberately skips so the voter keeps seeing the 403 it knows how to swallow. Do not extend the rewrite to components — it would break that chain. `features/main/route_schedule.feature` pins the 401 so the boundary is explicit.

**Redirects still point at a gated target.** `/old` → `/launch` returns 200 with `redirectPath: /launch`; the client follows it and gets the 404 at the destination. What `RouteNormalizer` no longer does is reflect the gated target's `page`/`pageData` IRI onto the redirecting route, which would have let an anonymous client render the unpublished page without following the redirect. Gated nodes are also pruned from the `redirectedFrom` tree on `/routes/{id}/redirects` for non-admins.

**`#[Silverback\Publishable]` must never be applied to `Route`.** The attribute is a draft/published *pair* — `PublishableListener` would add unusable `publishedResource`/`draftResource` FKs to the `route` table, `PublishableExtension::applyToItem` would rewrite the item query and collide with `RouteStateProvider`'s path lookup, and `?published=`, the `Route:published:*` groups and `_metadata.publishable` would all appear on a resource with no twin. Only the scheduling *predicate* is shared, via `Silverback\ApiComponentsBundle\Utility\PublicationDate` (`isActive()` + `andWhereActive()`), which `PublishableExtension` and `PublishableStatusChecker` now both use.

### Route generation (`src/Helper/Route/RouteGenerator.php`)

`RouteGenerator::create()` is called when a `Page`/`PageData` gets its route generated:
1. Slugifies `$title` to produce a path segment
2. Calls `getParentPageRoute()` — if non-null, prepends the parent route's path
3. Resolves name/path conflicts with a numeric suffix
4. Creates or updates the `Route` entity and calls `setRoute()` on the `PageData`

### Caching architecture

Resources are designed as **individual, piecemeal, independently cacheable entities**. The API does not bundle data into large grouped responses. Each resource (Route, Page, Layout, ComponentGroup, Component, etc.) is fetched and cached separately. When a resource changes, only that resource's cache entry is invalidated — not anything that merely references it.

Consequences for all design decisions:
- **Never embed related resource data** — always return IRIs. The consumer follows the IRI in a separate request.
- **Serialization groups should expose the minimum needed** — a reference to a related resource is an IRI, not an object.
- **For nested pages specifically**: the child's manifest returns `resource_iris` as an array of arrays grouped by depth (index 0 = root/shallowest, last index = the requested page). Each inner array is fetched in parallel. Parent and child manifests are cached and invalidated independently — a change to the parent layout does not invalidate the child's manifest cache.

### API Platform resource configuration

All API Platform resource metadata lives in **PHP attributes** on the entity/DTO classes — there are no XML config files under `src/Resources/config/api_platform/`. Mapping is registered by directory in `SilverbackApiComponentsExtension::prependApiPlatformConfig()`, which adds directory paths (not individual files) to `api_platform.mapping.paths`.

Serialization groups use `Symfony\Component\Serializer\Attribute\Groups` (the `Annotation` namespace was removed in Symfony 7.4).

### `RoutingPrefixResourceMetadataCollectionFactory`

This factory (`src/ApiPlatform/Metadata/Resource/RoutingPrefixResourceMetadataCollectionFactory.php`) auto-prefixes routes for all bundle resources:

- Subclasses of `AbstractComponent` → `/component/`
- Subclasses of `AbstractPageData` → `/page_data/`
- Any other class in the `Silverback\ApiComponentsBundle\` namespace → `/_/`

**AP4 4.x behaviour**: the factory **combines** the auto-prefix with any `routePrefix` already on the operation — it does not override. Do not set `routePrefix` on a class that the factory already handles, or you will get a double prefix (e.g. `/_/_/`).

### AP4 4.x / Symfony 8.x compatibility notes

- **`application/problem+json`** is the correct Content-Type for any 4xx/5xx response generated by AP4's exception handler (`rfc_7807_compliant_errors: true` is the default in AP4 4.x). Only responses serialised normally (non-exception path) carry `application/ld+json`.
- **Constraint constructors** (`Count`, `Length`, `NotBlank`, etc.) must use named arguments in Symfony 8.x — the array-options style (`new Count(['min' => 1])`) throws `TypeError`. Use `new Count(min: 1, minMessage: '...')`.
- **`api_sub_level` context**: when normalising a sub-object (e.g. `ResourceMetadata` inside `MetadataNormalizer`), set `$context['api_sub_level'] = true`. Without it, `PartialCollectionViewNormalizer` injects a `"view": {"@type": "PartialCollectionView"}` entry into any array property whenever the request URI has query parameters, turning the array into a JSON object.
- **Symfony 8.2 null-for-typed-string**: Symfony 8.2 converts a null value for a non-nullable typed string property into a proper validation violation rather than a raw TypeError. Prefer `?string = null` (nullable PHP type + nullable ORM column) so null passes through deserialization to the `#[Assert\NotBlank]` validator consistently across all Symfony versions. AP4's `AbstractItemNormalizer` reads serializer metadata (including the ORM `nullable` flag), so `?string` with `nullable: false` on the ORM column still triggers the TypeError path.
- **Behat / Symfony 8.x**: The `behat/behat` and `friends-of-behat/symfony-extension` packages do not yet support Symfony 8.x (their constraints cap at `^7.0`). This holds `symfony/console`, `symfony/event-dispatcher`, `symfony/property-access`, `symfony/http-kernel`, and the full security stack at 7.x in the test environment. The production bundle code is Symfony 8.x-compatible; only the test tooling is blocked. Watch for a `behat/behat` 4.x stable release or updated `friends-of-behat/symfony-extension` to unblock.
- **Service ID convention — two mandatory exceptions**: All bundle services use stable `silverback.api_components.*` string IDs with FQCN class-name aliases. Two categories **must** keep the FQCN as the primary service ID (with the string ID as alias) because they are looked up by class name at runtime:
  1. **AP4 state providers** tagged `api_platform.state_provider` and referenced as `provider: SomeClass::class` on an operation — AP4 builds its `CallableProvider` service locator keyed by tagged service ID. If the service ID is a string (not the FQCN), AP4 throws `ProviderNotFoundException`.
  2. **Controller action services** tagged `controller.service_arguments` — Symfony's `RegisterControllerArgumentLocatorsPass` keys argument locators by service ID. Routes resolve controllers by FQCN; if the ID is a string the locator can't be matched and `__invoke` method argument injection fails.
  Pattern: `->set(SomeClass::class)->...->tag(...)` then `->alias('silverback.api_components.*', SomeClass::class)->public()`. All other services use the reverse.

### Serialization groups

The module fetches resources using the `Route:manifest:read` normalization context (endpoint: `GET /_/resource_manifest/{id}`). This group controls what the Nuxt module sees.

Key current group assignments:
- `Route`: `page`, `pageData` → `Route:manifest:read`
- `AbstractPageData`: `page` (the Page template IRI) → `Route:manifest:read`
- `AbstractPage`: `route`, `parentPage`, `parentPageData` → `Route:manifest:read`

### API endpoints

| Endpoint | Purpose |
|----------|---------|
| `GET /_/routes/{path}` | Resolve a path to a Route resource |
| `GET /_/resource_manifest/{id}` | Unified manifest endpoint — `{id}` starting with `/` resolves to a Route by path; a UUID resolves to a `Page` or `AbstractPageData` entity. Returns `{ "resource_iris": string[][] }`. |
| `POST /routes/generate` | Auto-generate a Route for a Page/PageData |
| `GET /routes/{id}/redirects` | Follow the redirect chain for a Route |
| `PATCH /_/routes/{id}` | Accepts optional `cascadeChildPaths: true` — when `path` changes, walks direct children and updates their route paths (prefixing with the new parent path), creating redirects from old to new paths. |
| `GET /_/routes/{id}/children` | Returns the recursive child tree for a route (admin-only). Each node: `{ "route": IRI, "path": string, "children": [] }`. Not filtered by publication — an admin needs to see scheduled children. |

---

## Feature: Nested Sub-Pages

> **Status: API layer fully complete and tested, including the unified manifest endpoint for both public routes and admin/draft entity access.**
> Companion plan: see `## Planned Feature: Nested Sub-Pages` in the CWA Nuxt Module CLAUDE.md (`/Users/danielwest/Documents/GitHub/_CWA/cwa-nuxt-3-module/CLAUDE.md`).

### What we want

Pages support sub-pages. A conference page at `/best-conference-ever` renders a tab bar and a `<NuxtPage />` slot; child pages (`/best-conference-ever/programme`, etc.) fill that slot. Structure is admin-manageable and reusable across projects.

### Data model (`AbstractPage`)

`AbstractPage` (base of both `Page` and `AbstractPageData`) has two fields for hierarchy:

- `$parentPage: ?Page` — parent is a `Page` entity (mutually exclusive with `$parentPageData`)
- `$parentPageData: ?AbstractPageData` — parent is any `AbstractPageData` subclass (mutually exclusive with `$parentPage`)

**There is no `$nested` boolean.** Having a parent means the page is nested inside it — the relationship itself is the signal. There is no valid "parent for URL purposes only, renders standalone" use case. Parent = nested, always.

`$parentPage` and `$parentPageData` cannot be `?AbstractPage` (mapped superclass — no Doctrine FK target). Two separate FK columns, one to `Page` and one to `AbstractPageData` (which has a JOINED inheritance discriminator map), mirrors the existing `Route.$page`/`Route.$pageData` pattern.

A validation constraint (`Assert\Expression`) ensures both cannot be set simultaneously.

`getParentPageRoute(): ?Route` is a computed helper (no DB column) returning `$parentPage?->getRoute() ?? $parentPageData?->getRoute()`. Used by `RouteGenerator` to prefix paths. Returns null gracefully when the parent is still in draft (no public Route yet).

### How the manifest carries parent resources

`$parentPage`, `$parentPageData`, and `$route` (on `AbstractPage`) all carry `#[Groups(['Route:manifest:read'])]`. When a child Route is normalised for the manifest, the parent entity is embedded inline, and inside it the parent's own route is embedded.

`RouteNormalizer` walks the normalised structure and emits `resource_iris` as an **array of arrays grouped by depth**: index 0 = root/shallowest resources, last index = the resources for the requested page. The `parentPage`/`parentPageData` fields are the depth boundaries — everything reachable without crossing those fields belongs to the same depth group. Circular references resolve to IRI strings via AP4's circular-reference handler; the walker only processes arrays, so string IRIs are left as-is.

For one level of nesting (PageData-based):
```json
{
  "resource_iris": [
    ["/_/routes//conference", "/_/abstract_page_data/parent-uuid", "/_/pages/parent-template-uuid"],
    ["/_/routes//conference/programme", "/_/abstract_page_data/child-uuid", "/_/pages/child-template-uuid"]
  ]
}
```

For a flat (non-nested) page, `resource_iris` has one inner array. The module always iterates by depth group.

### Route path concatenation — recommended, not required

`RouteGenerator` prefixes a child's generated path with the parent's path (e.g. parent `/conference` + `programme` → `/conference/programme`). This produces clean, hierarchical public URLs and is the default behaviour.

Path concatenation is **not a rendering constraint**. The module's `<CwaPage />` component is data-driven — it reads the `resource_iris` depth groups from the manifest to determine rendering depth, not the URL structure. A child page at URL `/programme` with `parentPageData` set would still render nested inside the parent, because the manifest's depth grouping carries the correct structure. Concatenated paths are preferred for SEO and UX, but the rendering mechanism does not depend on them.

### Rendering and routing — `<CwaPage />`

The module uses a single `<CwaPage />` mechanism for all rendering contexts, both public routes and admin/draft access. The rendering depth is determined entirely from `resource_iris` depth groups (or from walking the `parentPage`/`parentPageData` chain on individually fetched resources). There is no URL-segment-depth dependency.

This means:
- **Public routes**: manifest delivers `resource_iris` groups; `<CwaPage />` renders the stack from root to deepest leaf, with keepalive preserving ancestor layers when only the deepest layer changes
- **Admin/draft**: `GET /_/resource_manifest/{uuid}` delivers the same `resource_iris: string[][]` structure for any `Page` or `AbstractPageData` UUID, collapsing what would otherwise be 4+ serial round trips into one parallel batch

### What is complete ✓

1. **`$parentPage` and `$parentPageData` on `AbstractPage`** — `Assert\Expression` constraint, getters/setters, computed `getParentPageRoute()`, ORM attributes on both `Page` and `AbstractPageData`
2. **`$nested` removed from `AbstractPage`** — property, getter, setter, ORM mapping, and schema entry all removed. Parent = nested, always.
3. **`$route`, `$parentPage`, `$parentPageData` in `Route:manifest:read`** — parent sub-tree IRIs appear in `resource_iris` automatically via the normalizer walk
4. **Behat tests** — `features/main/route.feature`: nested PageData and nested Page manifests both tested; `features/main/page.feature`: create with parentPage (201), create with parentPageData (201), both set (422), PATCH to set parentPage (200), flat PageData manifest (200), nested PageData manifest (200), nested Page manifest (200)
5. **`/_/resource_manifest/{id}` unified endpoint** — `ResourceManifest` DTO (`src/ApiResource/ResourceManifest.php`) with `ResourceManifestStateProvider` resolving route paths (starts with `/`) or UUIDs (Page then AbstractPageData). `ResourceManifestVoter` delegates access control to `RouteVoter` or `AbstractRoutableVoter`. `ResourceManifestNormalizer` produces `{ "resource_iris": string[][] }` using the shared `ManifestDepthGroupTrait`.
6. **`ManifestDepthGroupTrait`** (`src/Serializer/Normalizer/Trait/ManifestDepthGroupTrait.php`) — `buildDepthGroups`, `collectCurrentDepth`, `shouldSkipIri` extracted and shared between `RouteNormalizer` and `ResourceManifestNormalizer`
7. **`pageDataProperty` component IRIs in manifests** — `PageDataNormalizer` injects `cwa_current_page_data` into the serialization context when `Route:manifest:read` is active. `ComponentPositionNormalizer.normalizeForPageData()` reads this context key and resolves `pageDataProperty` positions during manifest generation without requiring an HTTP `path` header. `ManifestDepthGroupTrait.collectCurrentDepth()` now also collects string IRI values from non-blank-node subresources (AP4 returns component IRIs as strings when `AbstractComponent` has no `Route:manifest:read` fields). Blank node resources (`/.well-known/genid/...`) are excluded from string IRI collection to avoid leaking internal metadata IRIs (e.g. `pageDataMetadata`). Behat test in `features/main/route.feature` covers `resource_iris[0][5]` matching a DummyComponent IRI.
8. **`Layout.componentGroups` returns IRI strings** — AP4 reads `readableLink` from getter methods; `Layout` overrides `getComponentGroups()` with `#[ApiProperty(readableLink: false, writableLink: false)]`. Behat test in `features/main/layout.feature`.

### Design decisions

- **No `$nested` boolean** — parent = nested, full stop. The presence of `$parentPage`/`$parentPageData` is the complete signal.
- **Two FK properties, not one** — `AbstractPage` is a mapped superclass with no discriminator map; `?AbstractPage` cannot be a Doctrine FK target. `?Page` + `?AbstractPageData` mirrors `Route.$page`/`Route.$pageData`.
- **`getParentPageRoute()` is computed** — no DB column; used by `RouteGenerator` only; returns null safely when the parent has no route yet.
- **Route concatenation is recommended, not required** — `RouteGenerator` prefixes child paths for clean URLs and SEO, but the module's `<CwaPage />` renders depth from manifest data, not URL structure.
- **`resource_iris` is `string[][]`, not `string[]`** — depth-grouped, root first. The module reads the array index as the rendering depth without any client-side traversal.
- **Single rendering mechanism** — `<CwaPage />` uses a manifest in both public and admin/draft contexts. Both contexts use the same `/_/resource_manifest/{id}` endpoint — route path for public, UUID for admin/draft. The chain walk (`parentPage`/`parentPageData`) is a fallback only. No URL-depth dependency.
- **Unified manifest endpoint, not per-entity operations** — `/_/resource_manifest/{id}` is owned by the `ResourceManifest` DTO (not by `Route`, `Page`, or `AbstractPageData`). The state provider distinguishes route paths (start with `/`) from UUIDs at runtime. This avoids URL conflicts from `RoutingPrefixResourceMetadataCollectionFactory` auto-applying `/_/` to all bundle-namespace classes.
- **Hierarchy on AbstractPage, not Route** — Routes are the publication mechanism. Hierarchy must be settable before either page has a public URL.
- **Manifest for admin is a performance requirement, not an optimisation** — without it, rendering a page requires 4+ serial round trips (page → groups → positions → components). The manifest collapses this to one parallel batch. Both contexts must have manifests.

---

## Feature: CwaFixtureBuilder

> **Status: Implemented and tested (unit tests in `tests/Fixture/CwaFixtureBuilderTest.php`).**

A fluent builder API that lets developers scaffold CWA website structure (layouts, pages, component groups, components, routes) in Doctrine fixture code with minimal boilerplate. The Doctrine Fixtures Bundle handles execution; this feature adds the ergonomic PHP API on top.

The design was refined against real fixtures from `components-web-app` (`HomePageFixture`, `BlogArticlesFixture`, `BlogCollectionPageFixture`, `NestedPageDataFixture`). All patterns those fixtures use must be expressible in the builder API.

### Real-world patterns the builder must cover

Derived from studying the components-web-app fixture classes:

| Pattern | Example from fixtures |
|---|---|
| Layout with nav group, restricted to one component type | `addAllowedComponent(NavigationLink::class IRI)` |
| Nav bar populated AFTER routes are created | Routes generated by `RouteGenerator.create()`, then `addNavigationLink(..., $parent->getRoute())` |
| Template page (no route, `isTemplate: true`) | `createPage(..., isTemplate: true)` — shared template for multiple PageData instances |
| Page with static components | `HtmlContent`, `Image`, `Collection`, `Form` added to a ComponentGroup |
| Page with `pageDataProperty` positions | `position->setPageDataProperty('introContent')` — slot resolved at render time from PageData |
| Multiple `pageDataProperty` positions on same template | `image` and `htmlContent` positions on blog template |
| Publishable components | `$component->setPublishedAt(new \DateTime())` |
| Draft component linked to published version | `$draft->setPublishedResource($published)` |
| PageData with custom properties (component references) | `BlogArticleData.htmlContent`, `NestedPageData.introContent` |
| PageData with explicit route | `$route = createRoute('/blog-articles/blog-article-0', ..., pageData: $articleData)` |
| PageData auto-routed via `RouteGenerator` | `RouteGenerator::create($pageData)` — slugifies title, prefixes with parent path |
| **Page** as child of **PageData** parent | `$childPage->setParentPageData($parentPageData)` + `RouteGenerator::create($childPage)` |
| **PageData** as child of **PageData** parent | `$childPd->setParentPageData($parentPd)` + `RouteGenerator::create($childPd)` |
| ComponentGroup.addAllowedComponent | Restricts admin to one type; takes class collection IRI |
| Routes shared across fixtures by name | `createRoute('/blog', 'blog-page')` deduped by Doctrine fixture reference |
| Collection component | `$c->setPerPage(8)->setResourceIri(IriConverter->getIriFromResource(BlogArticleData::class, ...))` |

### Dream developer API

```php
class AppScaffold extends AbstractCwaScaffold
{
    public function build(CwaFixtureBuilder $cwa): void
    {
        // Layout: create the nav group (empty — nav links added after routes exist)
        $navGroup = $cwa->layout('main', 'CwaLayoutPrimary')
            ->group('top', allow: [NavigationLink::class]);

        // Home page
        $cwa->page('home', 'PrimaryPageTemplate', layout: 'main', route: '/', routeName: 'home-page', fn(PageBuilder $page) =>
            $page->title('Welcome to CWA')->metaDescription('...')
                 ->group('primary', fn(GroupBuilder $g) => $g
                     ->add((new HtmlContent())->setHtml('...')->setPublishedAt(new \DateTime()))
                     ->add(new Image())  // no publishedAt = draft
                 )
        );

        // Blog collection page
        $cwa->page('blog-list', 'PrimaryPageTemplate', layout: 'main', route: '/blog-articles', routeName: 'blog-page', fn(PageBuilder $page) =>
            $page->title('Blog')
                 ->group('primary', fn(GroupBuilder $g) => $g
                     ->add($this->buildCollection($cwa, BlogArticleData::class, perPage: 8))
                 )
        );

        // Populate nav bar now that routes exist
        $navGroup->add((new NavigationLink())->setLabel('Home')->setRoute($cwa->getRoute('home-page'))->setPublishedAt(new \DateTime()));
        $navGroup->add((new NavigationLink())->setLabel('Blog')->setRoute($cwa->getRoute('blog-page'))->setPublishedAt(new \DateTime()));

        // Blog article template (isTemplate: true, pageDataProperty positions, no route)
        $cwa->page('blog-template', 'BlogPageTemplate', layout: 'main', isTemplate: true, fn(PageBuilder $page) =>
            $page->group('primary', fn(GroupBuilder $g) => $g
                ->pageDataPosition(BlogArticleData::class, 'image')      // dynamic — resolved from BlogArticleData.image at render time
                ->pageDataPosition(BlogArticleData::class, 'htmlContent')
            )
        );

        // Blog article instances (PageData, explicit route per item)
        for ($i = 0; $i < 10; $i++) {
            $article = (new BlogArticleData())->setTitle("Blog Article $i");
            $article->htmlContent = (new HtmlContent())->setHtml("...{$i}...")->setPublishedAt(new \DateTime());
            $cwa->pageData($article, template: 'blog-template', route: "/blog-articles/blog-article-$i");
        }

        // Topic template (isTemplate: true, pageDataProperty for per-instance intro content)
        $cwa->page('topic-template', 'NestedTopicTemplate', layout: 'main', isTemplate: true, fn(PageBuilder $page) =>
            $page->group('primary', fn(GroupBuilder $g) => $g
                ->pageDataPosition(NestedPageData::class, 'introContent')
            )
        );

        // Topic PageData instances with child Page sub-pages
        foreach ([1 => 'Topic One', 2 => 'Topic Two'] as $num => $title) {
            $intro = (new HtmlContent())->setHtml("Intro for $title")->setPublishedAt(new \DateTime());
            $topicPd = (new NestedPageData())->setTitle($title);
            $topicPd->introContent = $intro;

            $topicBuilder = $cwa->pageData($topicPd, template: 'topic-template');
            // No route arg → RouteGenerator called automatically: /topic-one, /topic-two

            // Child Pages (parentPageData set automatically by builder; route prefixed via RouteGenerator)
            $topicBuilder->nested(function(CwaFixtureBuilder $child) use ($cwa, $topicPd, $navGroup, $title) {
                $child->page('topic-chapter-1', 'NestedSubPageTemplate', layout: 'main', fn(PageBuilder $page) =>
                    $page->title('Chapter One')
                         ->group('primary', fn(GroupBuilder $g) => $g
                             ->add((new HtmlContent())->setHtml('...')->setPublishedAt(new \DateTime()))
                         )
                );
                $child->page('topic-chapter-2', 'NestedSubPageTemplate', layout: 'main', fn(PageBuilder $page) =>
                    $page->title('Chapter Two')
                         ->group('primary', fn(GroupBuilder $g) => $g
                             ->add((new HtmlContent())->setHtml('...')->setPublishedAt(new \DateTime()))
                         )
                );
            });

            // Add nav link for this topic (route now exists after RouteGenerator ran)
            $navGroup->add((new NavigationLink())->setLabel($title)->setRoute($topicPd->getRoute())->setPublishedAt(new \DateTime()));
        }
    }
}
```

### Integration — `AbstractCwaScaffold` IS the fixture

```php
abstract class AbstractCwaScaffold implements FixtureInterface
{
    public function __construct(private CwaFixtureBuilder $cwa) {}

    public function load(ObjectManager $manager): void
    {
        $this->build($this->cwa->withManager($manager));
    }

    abstract public function build(CwaFixtureBuilder $cwa): void;
}
```

Register `AppScaffold` as a service; it's ready to use as a Doctrine fixture with no extra boilerplate.

### Builder shape

```
CwaFixtureBuilder
  ->layout(ref, uiSuffix, ?uiClassNames): LayoutBuilder  (deduped by ref; prepends 'CwaLayout' to uiSuffix)
  ->page(ref, uiSuffix, layout, ?route, ?routeName, isTemplate=false, ?Closure, ?uiClassNames): PageBuilder  (prepends 'CwaPage' to uiSuffix)
  ->pageData(AbstractPageData, ?template, ?route, ?routeName, ?Closure): PageDataBuilder
  ->component(AbstractComponent): ComponentBuilder
  ->getRoute(routeName): Route                              (look up a named route already created)

LayoutBuilder
  ->group(name, allow: [], ?Closure): GroupBuilder          (returns the GroupBuilder; same name = same group)
  ->uiClassNames(string ...$classes): self

PageBuilder
  ->title(string): self
  ->metaDescription(string): self
  ->uiClassNames(string ...$classes): self
  ->group(name, ?Closure): GroupBuilder
  ->nested(Closure): void                                   (Closure receives CwaFixtureBuilder with parent context)
  ->getRoute(): ?Route                                      (route after builder flushes RouteGenerator)

PageDataBuilder
  ->nested(Closure): void                            (Closure receives CwaFixtureBuilder with parent context)
  ->onRoutesCreated(Closure): self                   (Closure receives array<PageBuilder> of direct child page builders; called after phaseThree so child route paths are available)
  ->getRoute(): ?Route

ComponentBuilder
  ->uiComponent(suffix): self                        (stores 'CwaComponent' + ShortClassName + 'Ui' + suffix)
  ->uiClassNames(string ...$classes): self
  ->group(name, allow: [], ?Closure): GroupBuilder

GroupBuilder
  ->add(AbstractComponent, ?sort): self              (sort defaults to insertion order × 10)
  ->pageDataPosition(pageDataClass, propertyName, ?sort): self      (creates ComponentPosition with pageDataClass and pageDataProperty set)
```

### Route auto-generation rules

| Situation | Result |
|---|---|
| `route: '/path'` explicit on `->page()` or `->pageData()` | creates Route with that exact path; optionally named `routeName:` |
| no `route:` on `->page()` + `isTemplate: true` | no Route created |
| no `route:` on `->page()` without template flag | RouteGenerator called from title (slug) |
| `->pageData(...)` inside `->nested()`, no route | RouteGenerator called → `/parent-path/slug-from-title` |
| `->pageData(...)` or `->page(...)` at top level, no route, no title | no Route created (draft) |

### Allowed components on groups

`->group('top', allow: [NavigationLink::class])` calls `ComponentGroup::addAllowedComponent()` with the class-level IRI obtained from `IriConverterInterface`. The builder handles the IRI lookup internally — callers pass PHP class names.

**`allowedComponents` matches by class-level (collection) IRI**, not per-instance — the validator compares a component's collection IRI against the group's list. This is the type-level allow mechanism that the planned `explicitAllowOnly` opt-in restriction builds on (see Open Issues → #196).

### Internal flush ordering

The builder manages persisting in the correct order. Roughly:

1. Persist all Layout, Page, and AbstractPageData entities (no relations yet)
2. `flush()` — entities get UUIDs
3. Create ComponentGroups (keyed by entity IRI + location name for deduplication)
4. `flush()`
5. Call `RouteGenerator::create()` for all auto-routed entities (parents before children — breadth-first)
6. `flush()` — routes now have paths
6.5. Call `onRoutesCreated` callbacks on any `PageDataBuilder` that registered one, passing the child `PageBuilder` instances tracked during `evaluateNested()`. The callback mutates already-persisted entity properties (e.g. sets `HtmlContent.html` with real child paths). Followed by a `flush()`.
7. Create ComponentPositions and nav-bar links (which may reference routes created in step 5)
8. Final `flush()`

`->getRoute(routeName)` and `PageDataBuilder/PageBuilder->getRoute()` are only valid after step 5 completes. The builder defers all closures to the correct phase internally. Closures registered against GroupBuilder via `->add()` or `->pageDataPosition()` are evaluated in phase 7. The `->nested()` closure is evaluated during phase 5 so parent routes exist before child routes are generated.

### `onRoutesCreated`

**Use case:** A `PageData` entity has a component whose content must reference child page URLs (e.g. an `HtmlContent` with links to the child pages). Child routes don't exist at entity-creation time, so the content must be set after phase 5.

```php
$intro = new HtmlContent();
$intro->setPublishedAt(new \DateTime());
$topicPageData->introContent = $intro;  // persisted in phaseOne via cascade

$topicBuilder = $cwa->pageData($topicPageData, template: 'nested-topic-template', routeName: 'topic-1');

$topicBuilder->nested(function (CwaFixtureBuilder $child) use ($chapters) {
    foreach ($chapters as $j => $chapter) {
        $child->page(sprintf('topic-1-chapter-%d', $j + 1), 'NestedSubPageTemplate', layout: 'main',
            configure: fn(PageBuilder $p) => $p->title($chapter['title'])->group('primary')->add(...)
        );
    }
});

$topicBuilder->onRoutesCreated(function (array $childBuilders) use ($intro) {
    $links = implode(' | ', array_map(
        fn(PageBuilder $b) => sprintf('<a href="%s">%s</a>', $b->getRoute()->getPath(), $b->getPage()->getTitle()),
        $childBuilders
    ));
    $intro->html = sprintf('<p>Introduction to Topic 1. Chapters: %s</p>', $links);
    // No persist() needed — entity is already managed; flush() in phaseThreePointFive picks it up
});
```

**Key constraint:** The `HtmlContent` (or any entity updated in the callback) must already be persisted before `onRoutesCreated` fires — i.e. set on the `PageData` entity before passing to `->pageData()` so phaseOne cascades it. The callback only mutates properties on already-managed entities; it does not call `persist()`.

### What the builder handles invisibly

- `TimestampedDataPersister->persistTimestampedFields($entity, true)` on entities that have the `#[Timestamped]` annotation — Layout, Page, AbstractPageData, ComponentGroup, Route, and `AbstractComponent` subclasses (guarded by `isConfigured()`).
- `$manager->persist()` for all entities
- Layout/Page deduplication by reference string (calling `->layout('main', ...)` twice returns the same LayoutBuilder)
- ComponentGroup deduplication by entity IRI + location name
- `ComponentPosition` wrapping and auto-incrementing sort values (× 10 so gaps can be filled)
- Bidirectional linking: `Route::setPage/setPageData`, `AbstractPageData::setPage`, `AbstractPage::setRoute`
- Parent context propagation through `->nested()` — `parentPage`/`parentPageData` set on all children
- `RouteGenerator::create()` called automatically for all auto-routed entities in parent-before-child order

---

### Design decisions

- **No `$nested` boolean** — parent = nested, full stop. The presence of `$parentPage`/`$parentPageData` is the complete signal.
- **Two FK properties, not one** — `AbstractPage` is a mapped superclass with no discriminator map; `?AbstractPage` cannot be a Doctrine FK target. `?Page` + `?AbstractPageData` mirrors `Route.$page`/`Route.$pageData`.
- **`getParentPageRoute()` is computed** — no DB column; used by `RouteGenerator` only; returns null safely when the parent has no route yet.
- **Route concatenation is recommended, not required** — `RouteGenerator` prefixes child paths for clean URLs and SEO, but the module's `<CwaPage />` renders depth from manifest data, not URL structure.
- **`resource_iris` is `string[][]`, not `string[]`** — depth-grouped, root first. The module reads the array index as the rendering depth without any client-side traversal.
- **Single rendering mechanism** — `<CwaPage />` uses a manifest in both public and admin/draft contexts. Both contexts use the same `/_/resource_manifest/{id}` endpoint — route path for public, UUID for admin/draft. The chain walk (`parentPage`/`parentPageData`) is a fallback only. No URL-depth dependency.
- **Unified manifest endpoint, not per-entity operations** — `/_/resource_manifest/{id}` is owned by the `ResourceManifest` DTO (not by `Route`, `Page`, or `AbstractPageData`). The state provider distinguishes route paths (start with `/`) from UUIDs at runtime. This avoids URL conflicts from `RoutingPrefixResourceMetadataCollectionFactory` auto-applying `/_/` to all bundle-namespace classes.
- **Hierarchy on AbstractPage, not Route** — Routes are the publication mechanism. Hierarchy must be settable before either page has a public URL.
- **Manifest for admin is a performance requirement, not an optimisation** — without it, rendering a page requires 4+ serial round trips (page → groups → positions → components). The manifest collapses this to one parallel batch. Both contexts must have manifests.
- **Builder returns GroupBuilder references** — rather than closures that are deferred, `->group()` on LayoutBuilder and PageBuilder returns a `GroupBuilder` that can be held as a PHP variable and populated at any point before the final flush. This naturally handles the "nav bar populated after routes exist" pattern without special deferred-closure machinery.
- **`->nested()` takes a Closure, not a return value** — nested entities must have their parent's route before their own route can be generated. The `->nested()` Closure is evaluated during phase 5 (route generation), after the parent's route is created. The builder does not return nested builders; side effects are registered against the outer builder state.

---

## Open Issues — Context for Future Work

### #227 — Scheduled expiry and the manifest grouping key ✓ **DONE (parts 1 and 2 of 3; part 3 declined)**

Raised from the `components-web-app` side after extending Souin caching from `/_api` to the rendered page HTML. Three parts. **Parts 1 and 2 are implemented here. Part 3 is declined — it was already covered.**

**Part 3 was declined on evidence, and it is worth knowing why.** The issue asks for a coarse `nav:<layout>` / `routes:collection` key that any route create/delete/go-live purges, because a route going live changes every page whose navigation now lists it — pages that may not reference the route at all. Neither half of that needs anything:

- `routes:collection` **already exists** as the plain IRI `/_/routes`. `HttpCachePurger::collectResource()` collects the `GetCollection` IRI for *every* written resource, and AP4's `AddTagsProcessor` puts the collection IRI on every collection response. A route write already purges it.
- A **scheduled** go-live is not a write, so no surrogate key of any kind can fire for it — `propagate()` is reachable only from `PropagateUpdatesListener::postFlush()`. Expiry is the only mechanism that can work, and it was already the right one: `RouteRepository::findNextLiveAt()` is a **global** `MIN(liveAt)`, not a per-resource lookup, so an anonymous Route/Routable response is capped to the next go-live moment **whether or not it references the scheduled route**. That *is* the cascade, and it has been there since #224.

**What was actually missing was narrower than the issue thought, and in two places.**

**1. `ResourceManifest` was excluded from the cap.** `capAtNextPublicationChange()` gated on `Route` or `RoutableInterface`; `ResourceManifest` is neither, and it is the endpoint the module renders a page from — the one response a front end would derive a page TTL from. The gate is now a config list, `silverback_api_components.http_cache.scheduled_expiry_resource_classes`, default `[Route, RoutableInterface, ResourceManifest]`, mirroring `personalised_resource_classes` node-for-node and matched with `is_a(..., true)` so subclasses and interface implementors both count.

**2. `Expires` was advisory, never enforced.** `PublishableEventListener::onPostRespond()` sets `Expires` from the **draft's** `publishedAt`, and does so *before* the `isGranted` early-return, so anonymous responses carry it — a front end can see a pending publication it is otherwise forbidden to see, which is what makes the whole approach viable. But RFC 9111 §4.2.1 gives `s-maxage` precedence, so every shared cache ignored it. `CacheHeadersEventListener` now also caps `s-maxage`/`max-age` to `$response->getExpires()` when that is sooner.

**Reading `Expires` off the response rather than asking about publishable is deliberate.** This listener stays the single owner of capping, `PublishableEventListener` needs no change, and any future source of `Expires` is covered without being named. `Response::getExpires()` returns a far-past date for an unparseable header, which fails the `> $now` test, so a malformed `Expires` cannot shorten a TTL. The two caps have different gates on purpose: the `liveAt` cap is restricted to the configured class list (one query per response, and only route-shaped responses depend on which routes are live), while the `Expires` cap applies to any successful anonymous response that carries one.

**The listener is now pinned to `EventPriorities::POST_RESPOND - 1`.** It reads a header `PublishableEventListener` writes at plain `POST_RESPOND`; before this it ran second only because it is registered later in `services.php`. **Do not rely on autoconfiguration or registration order in a bundle** — an application may disable autoconfiguration, and several bundle definitions already opt out with `->autoconfigure(false)`. Same lesson as the `kernel.reset` entry below: tag explicitly, order explicitly.

> **Publishable collections get no `Expires` at all.** `PublishableEventListener::onPostRespond()` early-returns on `CollectionOperationInterface`, so a collection containing a resource with a pending publication carries no transition signal and is capped only if its class is in the scheduled-expiry list. Out of scope for #227 and recorded so it is not rediscovered.

**Behat:** `features/main/cache_headers.feature` — a scheduled go-live capping a manifest that has **no relationship to the scheduled route** (the cascade proof, and the thing part 3 was asking for); a pending publication capping `s-maxage` and not only setting `Expires`; and a guard that a response with no pending transition keeps its configured lifetime. Two new steps: `the response shared max age should be at least :seconds` (`JsonContext`, beside the existing `at most`) and `there is a published resource with a draft set to publish in :seconds seconds` (`PublishableContext`, mirroring `the Route :path goes live in :seconds seconds`). Unit coverage of the precedence rules — soonest of the two wins in both directions, a past `Expires` does not cap, `max-age` is capped alongside `s-maxage`, a response with no directives is untouched — in `tests/EventListener/Api/CacheHeadersEventListenerTest.php`.

> While editing them, `JsonContext`'s shared-max-age steps were switched from `Assert::*` to plain exceptions. A failing `Assert::*` **fatals** under Behat with `assert(self::$instance instanceof Configuration)` — PHPUnit's failure-message `Exporter` needs its TextUI Configuration Registry, which Behat never bootstraps. Same trap recorded under #194; the step reported nothing useful when it failed.

**Part 1 — the manifest grouping key — ✓ DONE.** A manifest's `Surrogate-Key` used to be a superset of its own body: the body is IRIs and nothing else, so it changes only on **membership** change, yet every member IRI was a tag, so every content edit to any component in it dropped the manifest. It is now one grouping key per rendering depth and nothing else.

**`CwaTagCollector`** (`src/HttpCache/CwaTagCollector.php`) implements AP4's `TagCollectorInterface`, the only seam that can *remove* a tag. `AbstractItemNormalizer` guards every `$context['resources'][$iri] = $iri` site with `if ($this->tagCollector)`, and the service id `api_platform.http_cache.tag_collector` is referenced with `ignoreOnInvalid()` by four format configs and was **defined nowhere**, so the slot was free. It runs before the purger serialises, so it is header-name- and separator-agnostic. Seeding `_resources` on the request (as `UserEventListener::onPostRead()` does for `/me`) could not have worked: `SerializeProcessor` unions rather than overwrites, so it can only **add**.

**Defining that service replaces the default for every resource in the application**, so the default branch is a faithful reproduction of what `AbstractItemNormalizer` does without it — `$context['resources'][$iri] = $iri`, keyed by IRI so it stays idempotent across the several call sites that re-collect the parent's IRI while walking its properties. Two deviations only:

- **Un-purgeable noise is dropped everywhere** — `/.well-known/genid/…` blank nodes and `/_/resource_metadatas`. The predicate is `ManifestIriFilterTrait::shouldSkipIri()`, extracted from `ManifestDepthGroupTrait` (which still uses it) so the body and the header cannot disagree.
- **For a `ResourceManifest` operation, only `manifest:<entity-iri>` is emitted, per rendering depth**, and the member IRIs are suppressed entirely.

**Key per depth, not per manifest.** A manifest contains its ancestors' subtrees, so keying by the manifest would force a descendant walk on every write. Keying per depth means the purge side needs only the **upward** walk and the cascade to every descendant manifest is free — a descendant's manifest already carries the ancestor's key. `manifest:<entity-iri>` rather than the manifest's own URL, because the same manifest is addressable by route path *and* by UUID; a canonical value emitted on both responses lets one purge drop both cache entries. The entity is the `AbstractPage` at that depth — deliberately not the `Route`, whose IRI `RouteNormalizer` rewrites to a path-based form, so a route-derived key would not match the one a UUID-addressed manifest emits.

**`ManifestKeyResolver`** (`src/HttpCache/ManifestKeyResolver.php`) is the purge-side half: an upward walk from a written resource to the `AbstractPage` set that owns it (`Route` → its page/pageData; `AbstractPage` → itself; `Layout` → `pages`; `ComponentPosition` → its group; `ComponentGroup` → `pages`, `layouts.pages`, and up through `components` → their positions → their groups). That last leg is `ComponentVoter::getComponentPages()` inverted. Breadth-first with a visited-id set, memoised in a `\WeakMap` — the same shape `RouteLiveResolver` and `RouteReachabilityResolver` already use, and no maintained state or new table (a denormalised design was built and rejected on #225).

**`HttpCachePurger` collects the keys beside the existing `cwa-html` handling**, and **the resolver's type switch is the trigger restriction**: it returns `[]` for anything that is not `Route`, `AbstractPage`, `AbstractPageData`, `Layout`, `ComponentGroup` or `ComponentPosition`, so no separate class list exists to drift. A hardcoded switch rather than config because these are the bundle's own structural types — an application cannot add a new one except an `AbstractPageData` subclass, which `instanceof` already covers.

> **Behaviour change, approved: a component content edit no longer purges any manifest.** Manifests will therefore live much longer. This is the correct granularity — a manifest body is IRIs only, so a content edit cannot change it — but it is the part most likely to surprise, and it is what the Behat scenario "Editing a component's content does not purge the manifest of the page it is in" exists to pin.

**`PageDataProvider::findPageDataResourcesByPages()` was considered for the upward walk and deliberately left out.** It is not needed: a page data manifest already carries `manifest:<template-page-iri>` alongside its own key, because `AbstractPageData.page` is in `Route:manifest:read`. So a write inside a shared template emits **one** key that drops all N page data manifests built from it, where resolving the page data would have emitted N keys — reintroducing exactly the header bloat part 1 removes. Pinned by "A page data manifest carries a grouping key for its template page as well as its own".

> **One imprecision inherited from `PropagateUpdatesListener`, not introduced here.** `collectUpdatedPageDataAndPositions()` runs for *every* updated resource, so a content-only edit to a component that is bound to a `pageDataProperty` still collects the owning `AbstractPageData` and the resolved `ComponentPosition`s — both structural — and therefore still purges those manifests. Fixing that means changing which resources the propagator gathers, which is a wider blast radius than #227. The "content edit does not purge" scenario deliberately uses a component in an ordinary `ComponentPosition` for this reason; a first draft used the `pageDataProperty`-bound component from `there is a PageData resource with the route path …` and could not have passed.

**Detection of a manifest operation is an explicit bundle-owned context key** (`CwaTagCollector::MANIFEST_CONTEXT_KEY`, set by `ResourceManifestNormalizer`), **not** `$context['operation']` or `root_operation`. `JsonLd\ItemNormalizer::normalize()` unsets `operation` on its "non-resource got serialized and contains a resource" branch, which is exactly the `ResourceManifest` → `Route` transition, and `root_operation` is only ever populated by `createOperationContext()` — which by then has nothing to copy. The context key survives because AP4 preserves unknown keys through `createChildContext`/`createOperationContext`; `cwa_current_page_data` already relies on the same thing.

**Response-side header size is unbounded.** `SurrogateKeysPurger::getResponseHeaders()` is a plain `implode` — no chunking, no length check. `maxHeaderLength` applies only to `purge()` (Souin 1500, Varnish xkey 8000). The limit is infrastructural (nginx `proxy_buffer_size`, Varnish `http_resp_hdr_len`), and the failure is a 502 at the proxy, never a truncated header — which is what the reporter's `proxy-buffer-size: 256k` is evidence of.

**Behat:** `features/main/manifest_cache_tags.feature` — the manifest carrying a grouping key and none of its members, a UUID-addressed manifest carrying the same key as the route-addressed one, a nested manifest carrying a key per depth, a page data manifest carrying its template page's key and a write inside that template purging it, an ordinary response still carrying its own IRI, membership change (a new `ComponentPosition`) purging the key, the content-only edit **not** purging it, and page / layout / route writes each purging it. New `ProfilerContext` steps are the **response-side twin** of `collectPurgedTags()`: `collectResponseTags()` reads whichever of `xkey`/`surrogate-key` the response carries and splits on `/[,\s]+/`, so nothing asserts an exact header string — the harness runs the Varnish xkey purger while production runs Souin. Unit coverage in `tests/HttpCache/CwaTagCollectorTest.php` and `tests/HttpCache/ManifestKeyResolverTest.php`; both classes need unit tests specifically because Infection scores from **PHPUnit** coverage only, so a class reached solely by Behat gets no mutation scrutiny at all.

> **A Behat trap that cost a false failure here: the first scenario of a run can execute against the *previous* container.** After changing a service definition, the first scenario still saw the old wiring and the same scenario passed on its own and failed as scenario 1 of a file run — and vice versa when the change was reverted. When a red/green check disagrees with an isolated run, run it twice before believing either.

**Services are registered explicitly, with no reliance on autoconfiguration or decoration inference.** `api_platform.http_cache.tag_collector` is the **primary** id (it is what AP4's format configs reference by name), with `silverback.api_components.http_cache.tag_collector` and the FQCN as aliases — the same inversion the two mandatory exceptions above use, for the same reason. `->autoconfigure(false)` on both new services: this is a bundle, an application may disable autoconfiguration, and several bundle definitions already opt out.

> **Tag grammar — three shapes, deliberately distinct.** A tag is a **resource IRI** (starts with `/`, or a scheme under `ABS_URL`), a **singleton flag** (a bare token with no `:` and no `/` — only `cwa-html`), or a **grouping key** `<kind>:<resource-iri>` whose value is itself shape 1. #232's reasoning that `cwa:html` would only *look* consistent still holds once value-carrying keys exist: it would be shape 3 with a non-IRI value, a fourth grammar pretending to be the third. All three are separator-safe under Souin (`', '`) and Varnish xkey (`' '`) because no shape contains a comma or a space.

References: `src/HttpCache/CwaTagCollector.php`, `src/HttpCache/ManifestKeyResolver.php`, `src/HttpCache/HttpCachePurger.php`, `src/Serializer/Normalizer/Trait/ManifestIriFilterTrait.php`, `src/Serializer/Normalizer/ResourceManifestNormalizer.php`, `src/Resources/config/services_doctrine_orm_http_cache_purger.php`, `src/EventListener/Api/CacheHeadersEventListener.php`, `src/DependencyInjection/Configuration.php` (`addHttpCacheNode`), `src/DependencyInjection/SilverbackApiComponentsExtension.php`, `src/Resources/config/services.php`, `src/EventListener/Api/PublishableEventListener.php`.

---

### #234 — Anonymous route and page collections ignored the inherited `liveAt` ✓ **DONE**

Follow-up to #224/#225. `GET /_/routes` is the sitemap source, so listing a route gated by a scheduled ancestor handed search engines a soft-404 to crawl — and the module had no way to filter it, because both `liveAt` and `_metadata.effectiveLiveAt` are admin-only. Fixed with `RouteAncestorGateResolver` and a native recursive CTE; see **Route publication** above for the mechanism, the one-self-reference constraint, and the verified portability matrix.

Built the way `PublishableExtension` already builds — exclude a set so pagination and `totalItems` stay correct. The bounded-depth pure-DQL alternative was rejected: each level branches two ways (`parentPage` / `parentPageData`), so the query doubles per level and it imposes a depth ceiling nothing else in the feature has. No denormalised state was reintroduced — a join table and a derived column were both built and rejected on #225, and the CTE makes neither necessary.

Behat in `features/main/route_schedule.feature` covers scheduled parent, draft parent, scheduled grandparent, the #224 unrouted-ancestor guard, a route with no parent, `totalItems` correctness, admin still seeing everything, both cycle directions, and the `GET /_/pages` and `GET /page_data/page_datas` halves. The scenario that pinned the old leak was inverted, not deleted.

> **Found while inverting it: `OrSearchFilter` defeats every extension predicate, including a route's own `liveAt`.** `addWhereByStrategy()` calls `$queryBuilder->orWhere(...)`, which ORs against the *entire* accumulated WHERE rather than only among the filter's own clauses. On `main`, an anonymous `GET /_/routes?path=launch` lists a route scheduled for 2999 — no ancestry involved. The old pinned scenario used `?path=`, so it was demonstrating this bug, not the inheritance one; the inverted scenarios query the unfiltered collection instead. **Not fixed here** — it is a separate defect in a public filter with its own blast radius. Needs its own issue.

### #225 — Nested child page whose parent has no Route: the parent was invisible to the public ✓ **DONE**

The voter chain never treated `parentPage`/`parentPageData` as a reachability edge, so it was wrong in **both** directions. Full mechanism in **Route reachability** above; this entry records the judgement calls.

- **Two bugs, one root cause.** A routeless parent with a live routed child was 401 for the public (its `PageData`, its template `Page` and its components), while a routeless page with *no* routed descendant leaked its **components** to the public. Fixing only the first would have left the chain half-taught.
- **It is 401, not 403, for an anonymous request** — the entry point converts the denial. The manifest was never the broken part: `GET /_/resource_manifest//child-path` already returned 200 *and already published the parent depth's IRIs anonymously*; only the follow-up per-IRI fetches failed. That is why the fix is in the voters and not in manifest filtering.
- **The case 2 tightening is a behaviour change for existing applications.** A component that is *placed* in a page structure nothing routes to is now admin-only (`routable_security`), where it used to be public. It is scoped deliberately: `ComponentVoter::voteByRoute` returns `null` (abstain → public, unchanged) when the component is in **no** page at all, and `false` when it is in pages but none is reachable. The first version denied both, and **86 existing scenarios failed** — bare `Form`, `Collection`, publishable and persisted components that fixtures create without placing. That failure was the signal the rule was too broad, not a reason to edit the scenarios. An application relying on the old behaviour either leaves `routable_security` unset (the voter then returns `true` before reaching any of this) or places the component in a routed page.
- **Admins are unaffected in both directions** because the unreachable path falls through to `routable_security` rather than returning a flat `false`. That also means an admin now reads a component behind a `route_security` route they lack the role for — previously denied. Deliberate: it matches the posture everywhere else that admins can see the whole structure.
- **Reachability is not `liveAt`, and neither is denormalised.** An "earliest live date among reaching routes" column cannot express `route_security`, which is per-token and path-matched, so it would have granted anonymous access to a page reachable only via `/user-area/...`. Storing the edges in a join table was then built and rejected: it added a maintained table, a rebuild command and an upgrade step for a question `ComponentVoter` already answers by traversal. Both questions are now resolved by walking at request time. The cost is that neither is expressible in DQL — see the collection limitation in **Route publication** above.
- **Tests:** 21 scenarios in `features/main/security.feature`, 2 in `features/main/dynamic_page.feature`, and unit tests for `RouteReachabilityResolver`. Both halves were watched failing first — the case 1 scenarios 401 without the edge, the case 2 scenario 200 without the tightening. New Behat steps: `there is a routeless parent PageData/Page with a component and a routed child Page with the path :path`, `… with an unrouted child Page`, `there is a routeless Page with a component and no routed descendant`, `there is a chain of :depth routeless Pages ending in a routed Page with the path :path`, `there are two routeless PageData resources which are each other's parent`, `there is a routeless parent PageData with a dynamic position and a routed child Page with the path :path`.
- **The case 2 scenario that matters is the orphan *plain* `Page`.** A routeless template `Page` used by a `PageData` was already denied, because `voteByPageTemplate` sub-requests the page data and gets a 401. The leak only existed where `voteByPageTemplate` abstains — a page with no page data at all. A first attempt at the regression scenario used the template shape and passed with the fix reverted.
- **Half B (`cwa-nuxt-module#288`) needed no API change.** `PageDataProvider::getPageData()` already falls back to `iriConverter->getResourceFromIri($path)` and accepts an `AbstractPageData`, and `ComponentPositionEventListener` emits `Vary: path` on dynamic positions regardless of whether the header holds a route path or an IRI — two values that resolve to the same page data simply make two cache entries. Both are now pinned by scenarios asserting the resolved component IRI rather than relying on the schema's `required`.

---

### #224 — Route-level live / scheduled publication date ✓ **DONE**

`Route.liveAt` plus an effective date inherited down the page hierarchy and resolved at request time. Full semantics are in **Route publication — `liveAt` and the effective date** above; this entry records only what is easy to get wrong.

- **Decided against the first instinct on every one of these:** `null` means *not live* (not "no schedule, therefore live") — backwards compatibility comes from the constructor default plus the column's `CURRENT_TIMESTAMP` default, not from the semantics. Public status is **404**, not 401/403. Redirects to a gated target are **not** truncated — only the reflected page IRI is withheld.
- **The inheritance rule has two halves and only one is obvious.** A routed ancestor with `liveAt = null` gates everything below it. An ancestor with **no Route at all** is skipped entirely. Getting the second half wrong silently takes live child pages offline the moment someone sets a parent relationship on an unrouted template — `features/main/route_schedule.feature` carries a regression guard for it that passes both before and after the change.
- **Gates that needed nothing.** `ResourceManifestVoter`, `RoutableVoter`, `DenyAccessListener::isPageDataAllowedByRoute` and `ComponentVoter::voteByRoute` all reach `RouteVoter`, so gating the voter covered them for free. `cascadeChildPaths` runs post-authorisation on the entity graph and is untouched. `/routes/{id}/children` was already `ROLE_ADMIN`-only.
- **A Doctrine trap worth keeping, though the listener that hit it is gone.** When an `onFlush` listener writes to an entity, use `recomputeSingleEntityChangeSet`, never `computeChangeSet` — including for scheduled *insertions*. Doctrine has already computed the insert changeset by then, and `computeChangeSet` **replaces** it with a diff against `originalEntityData`, so the INSERT omits every other column and dies on `NOT NULL constraint failed: route.name`. This cost a full debugging cycle on the denormalised `effectiveLiveAt` listener before that listener was removed entirely.
- **Every write path is covered because the hook is `onFlush`, not the call sites.** `RouteGenerator::create()` in particular sets only the owning side (`$object->setRoute($route)`) and leaves `Route.page`/`Route.pageData` null, so the listener pairs each collected route with the `AbstractPage` it was collected from and hands that to the resolver rather than trusting the inverse side. `CwaFixtureBuilder` and `DoctrineContext` need no special handling for the same reason.
- **Tests:** `features/main/route_schedule.feature` (38 scenarios), two appended to `features/main/cache_headers.feature`, and unit tests for `RouteLiveResolver`, `UnpublishedRouteExceptionListener` and the cache cap. New Behat steps: `the Route :path goes live at/in :x`, `the Route :path has no go-live date`, `the Route :path should (not) be live`, `there is a PageData resource with the route path :path whose parent page has no route`, `the response shared max age should be at most :seconds`.
- **Test-app config change:** `tests/Functional/app/config/packages/api_platform.yaml` now sets `defaults.cache_headers.shared_max_age`. Without it the bundle emits no `s-maxage` at all (`api_platform.http_cache.shared_max_age` defaults to null) and the cap is untestable.

---

### `RouteVoter::supports()` must not depend on `route_security` being configured ✓ **DONE**

`supports()` used to read `self::READ_ROUTE === $attribute && $subject instanceof Route && $this->config`. `route_security` defaults to `[]`, so in any application that omitted the setting the voter **abstained on every route** — and `AffirmativeStrategy::decide()` returns `allowIfAllAbstainDecisions` (default `false`) when every voter abstains. The result was that `is_granted('read_route', object)` denied every route read, for everyone, silently.

Nothing caught it because the test app *does* configure `route_security`, so every scenario exercised the configured path. A voter that opts out of `supports()` is not neutral — under the affirmative strategy it is a denial unless some other voter grants.

The guard is gone (the loop now iterates `$this->config ?? []`), which was mandatory for #224 anyway: the publication check lives in this voter and has to run whether or not `route_security` is set.

---

### #222 — Config guards that are declared but never enforced (follow-up to #214)

`user.class_name`, `refresh_token.*`, `publishable.permission`, and `refresh_token.options.class` share the pattern #214 fixed: a node with a default plus `isRequired()` children, so `ArrayNode::finalizeValue` inserts the default and never finalizes it — the guard reads as working and never runs. The extension then reads missing keys directly, wiring `null` into typed scalars.

Not fixed alongside #214 because each needs a per-setting judgement: an inert default that preserves current behaviour (as #214 took) only works where a sensible one exists. `user.class_name` may have none, in which case the right answer is a clear compile-time failure — **which is a breaking change** for anyone relying on the silent-null path today. None are verified to the depth #214 was; confirm each empirically before deciding.

---

<details><summary>#216 — `/resend-verify-email/{username}` not routable ✓ <b>DONE</b> (see "Security routes: one path each" below)</summary>

Found 2026-08-14 from the Nuxt module side (module issue #281). Filed as bundle issue #216.

`src/Resources/config/routing/security.php:42-45` registers `api_components_resend_email_verification` at **`/verify-email/{username}/{token}`** — byte-identical to `api_components_verify_email` two entries above (`:37-40`), which points at `VerifyEmailAddressAction`. Symfony resolves duplicate paths to the **first** match, so `ResendVerifyEmailAddressAction` is unreachable: the route it is presumably meant to serve, **`/resend-verify-email/{username}`**, is registered nowhere.

The Nuxt module calls exactly that path — `Auth.resendVerifyEmail()` → `/resend-verify-email/{username}` (`cwa-nuxt-3-module/src/runtime/api/auth.ts:97`). Compare the sibling entry `api_components_resend_new_email_verification` at `/resend-verify-new-email/{username}` (`:47-50`), which is correct and is what `resendVerifyNewEmail()` hits.

**Expected fix:** change the `api_components_resend_email_verification` path to `/resend-verify-email/{username}` and drop the `{token}` placeholder (a resend needs only the username — `ResendVerifyEmailAddressAction` generates a fresh token).

**Why it went unnoticed:** the module's `useResendVerifyEmail()` composable had a separate bug (module #281, now fixed) where any non-`'current'` type fell through to the *new email* endpoint, so the broken route was rarely exercised. With the module fixed, "resend verification for my current address" will now hit the missing path and surface as a 404 (rendered as "Username not found" by the composable's error handling) until this is corrected.

**Worth a Behat scenario** pinning that each of the four security routes resolves to its intended controller — a duplicate path is invisible to unit tests.

</details>


### #186 — `#[Publishable]` on AbstractPage / AbstractPageData — page-level draft/live toggle

Currently the only "draft" signal for a page is the absence of a Route. Once a page is live, there is no way to take it offline without deleting the route (losing URL history and redirects).

**Desired behaviour:** `AbstractPage` gains a `publishedAt: ?\DateTimeInterface` column. Unpublished pages are invisible to unauthenticated users via the existing voter infrastructure (`AbstractRoutableVoter`, `RouteVoter`). Admins can still access and edit unpublished pages via the entity IRI.

**Things to consider:**
- `#[Publishable]` today lives on components; check whether voter logic extends cleanly to page-level entities
- `Page.isTemplate` pages should be admin-accessible regardless of `publishedAt`
- Draft page with an existing Route — route should return 403/404 for public traffic, not 500
- Interaction with `cascadeChildPaths` and the children endpoint: should unpublished children be hidden from the public list?
- Migration default: treat existing pages as already published (`now()`) for backwards compatibility

**Acceptance criteria:**
- Unauthenticated `GET /_/routes/{path}` to an unpublished page returns 403/404
- Admin `GET /_/resource_manifest/{uuid}` for an unpublished page works for `ROLE_ADMIN`
- Behat scenarios: public access denied, admin access allowed, publish via PATCH

**Additional considerations (unresolved — do not implement yet):**
- **Component permission inheritance**: component access is currently derived from whether a routed page exists that the component is reachable from. Adding page-level `publishedAt` must account for this — an unpublished page should also make its components inaccessible to public users, which may require extending the voter chain rather than a simple field check.
- **Front-end draft/live UX**: the right approach for the Nuxt module is still undecided. Two leading options are (a) a dedicated draft-preview URL scheme and (b) an admin-overlay flag on the normal URL. Neither is settled.
- **Hero component editing conflict**: if a page title is edited from within a hero component (a common CWA pattern), the page entity and the hero component are two separate resources each with their own `publishedAt`. A live page could have a draft hero (or vice versa), producing incoherent states. This needs a clear resolution — e.g. page-level `publishedAt` drives visibility for the whole subtree, or component states are independent and the admin UI must handle the mismatch — before implementation begins.

Leave this issue open until the front-end approach and component-state semantics are agreed.

> **2026-08-14 — Daniel: not doing this now.** Reviewed and deferred again, deliberately, not for lack of time. The three unresolved points above (component permission inheritance, front-end draft/live UX, hero-component state conflict) are still unresolved, and none of them is settled by writing the API side first — building `publishedAt` onto `AbstractPage` before the front-end approach is agreed would lock in answers to questions nobody has decided. **Do not start this**, and do not treat "the column is easy to add" as a reason to; the column is not the hard part.

> **2026-09-20 — #224 does not supersede this; it complements it, and #186 stays open and deferred.** #224 gates *URL resolution*, so none of the three blockers applies to it: there is no second `publishedAt` on the page or its components to disagree with (Route is not `#[Publishable]` and has no draft twin); component permission inheritance is not a new question because component reachability is *already* derived through routes via `ComponentVoter::voteByRoute` → `RouteVoter`, which is the mechanism #224 gates rather than a new one; and no draft-preview scheme is needed because admins already fetch by IRI/UUID. What #224 still cannot express is #186's other half — "this page is a draft while its URL is live". Taking a page offline is now possible (clear `liveAt`); decoupling the entity's draft state from its URL is not. The blockers above remain the reason to leave this alone.

---

### #196 — `explicitAllowOnly`: per-type opt-in component placement restriction (bundle side; front-end: cwa-nuxt-module #249) ✓ **DONE (both placement paths)**

**Implemented.** Declaration is a **Silverback class attribute** `#[Silverback\ExplicitAllowOnly]` (`src/Annotation/ExplicitAllowOnly.php`) read by `ExplicitAllowOnlyAttributeReader` (`src/AttributeReader/`, extends `AttributeReader`, service `silverback.api_components.attribute_reader.explicit_allow_only` — mirrors `Publishable`/`Timestamped`/`Uploadable`). This is the bundle's own attribute system — a component type declares the attribute, no interface to implement. `AbstractComponent::isPositionRestricted()` and the `RestrictedComponent` override are **removed**; `RestrictedComponent` carries `#[Silverback\ExplicitAllowOnly]`. `ComponentPositionValidator` now checks `$this->explicitAllowOnlyReader->isConfigured(...)` on **both** placement paths (was `isPositionRestricted()`): `validateDirectComponent` (the placed component) **and** `validateDynamicPosition` (the pageDataProperty's resolved `componentClass`) — the dynamic path was restructured to mirror the direct path so a restricted type can't be bound to a dynamic position in an unrestricted group and bypass the rule server-side. Exposure to the front-end matches the module's already-locked contract: `VersionedDocumentationNormalizer` adds `explicitAllowOnly => true` to each flagged component's Hydra `supportedClass` entry (matched by `title` = short name; flagged short names found by walking `ResourceNameCollectionFactory` and testing each class with the reader). The module already reads `supportedClass['explicitAllowOnly'] === true` (absent ⇒ false) in `getComponentMetadata`, so **no module code change is required**. Behat: `features/main/component_position.feature` covers both the direct path (RestrictedComponent) and the dynamic path (via new test entity `PageDataWithRestrictedComponent`, whose `restrictedComponent` property resolves to a RestrictedComponent) — rejected in an unrestricted group (422), accepted when the group lists it (201); new `features/main/explicit_allow_only.feature` asserts the docs flag (RestrictedComponent → true, DummyComponent → false).

**Both placement paths enforced.** `explicitAllowOnly` applies to **direct** components (`validateDirectComponent`) **and** **dynamic** page-data-property positions (`validateDynamicPosition`, checking the property's resolved `componentClass`), so a flagged type can't be bound to a dynamic position in an unrestricted group and bypass the rule server-side. The front-end (module) blocks both paths too (`AddComponentDialog` + `useDynamicPositionSelectOptions.getPropertyOptions`). Only remaining cross-repo item: component cloning (cwa-nuxt-module #157) must also respect the flag.

---
<details><summary>Original issue context</summary>

**Goal:** a component **type** can be marked so it may only be placed in a `ComponentGroup` that explicitly lists its collection IRI in `allowedComponents`. Everywhere else it is hidden from the admin add UI and rejected on save. Requested behaviour: "if a component of type X is flagged, it must be explicitly allowed by its type/IRI in a group to be added there."

**Design decisions (agreed):**
- **Per-type, declarative** — declared once on the component class, not per-instance. Named **`explicitAllowOnly`** (used as the annotation option, the metadata key, and the front-end property).
- Declared as an **`#[ApiResource]`-level option** on the component class (e.g. `explicitAllowOnly: true`).
- **Replaces `AbstractComponent::isPositionRestricted()`.** That method is per-instance and server-only and becomes redundant once the flag is declared via annotation. Remove the base method **and every per-subclass override**; entities set `explicitAllowOnly: true` on their `#[ApiResource]` attribute instead.
- **`ComponentPositionValidator` reads the per-type `explicitAllowOnly` value** (from resource metadata) instead of calling `$component->isPositionRestricted()`. Existing `restrictedMessage` violation is retained. Server validation stays the source of truth.
- **Expose `explicitAllowOnly` in the metadata the front-end reads.**

**LOCKED interface contract (bundle ⇄ module):**
The Nuxt module does **not** read a bespoke metadata endpoint. It derives component metadata from the **Hydra JSON-LD API docs** (`getComponentMetadata` → reads `docs['supportedClass']` / `supportedProperty`); `isPublishable` is inferred from the presence of a `publishedAt` *property*. `explicitAllowOnly` is **not an entity property**, so the contract is:

- Expose a **boolean under the exact key `explicitAllowOnly`** on each component's Hydra **`supportedClass`** entry, in the same docs the module already fetches (the entrypoint/docs used for `isPublishable`) — **no separate endpoint**.
- **Class-level** flag (not a `supportedProperty`), associated with the component by the same `title`/resource name the module keys on.
- **Absent ⇒ `false`.** The module reads `supportedClass[n].explicitAllowOnly` with a `false` fallback, so the front-end can ship independently and simply activates once the bundle emits the key.
- The bundle must ensure the value **compacts to exactly `explicitAllowOnly`** in the emitted docs — if API Platform surfaces custom class metadata namespaced (e.g. via `#[ApiResource(extraProperties: [...])]`), add a JSON-LD `@context` alias so it appears under the bare `explicitAllowOnly` term. No `extraProperties` precedent in the codebase today.
- `allowedComponents` (group, collection-IRI/type-level) and `ComponentPositionValidator` (server = source of truth) are unchanged.

The exact key `explicitAllowOnly` is the **locked** interface — both sides read/write that term.

**Front-end (tracked in cwa-nuxt-module #249):** add `explicitAllowOnly` to `ApiDocumentationComponentMetadata`; `AddComponentDialog.findAvailableComponents` excludes such types from groups that don't list them; component **cloning** (#157) must respect it too.

**Acceptance criteria (bundle side):**
- A component type with `explicitAllowOnly: true` in its `#[ApiResource]` is rejected by `ComponentPositionValidator` when placed in a group whose `allowedComponents` does not list its collection IRI, and accepted when it does.
- `explicitAllowOnly` is present in the metadata the front-end consumes for every component type.
- `AbstractComponent::isPositionRestricted()` and all overrides are removed; no behaviour regression for previously-restricted components (they now use the annotation).
</details>

---

### #189 — Tool: generate fixtures from currently-populated database ✓ **DONE**

Console command `silverback:api-components:generate-fixtures` (`src/Command/GenerateFixturesCommand.php`).

Walks the DB (Layouts → Pages → PageData → ComponentGroups → ComponentPositions → Components) and emits a complete `AbstractCwaScaffold`-compatible PHP file. Supports `--output` option (default `src/DataFixtures/GeneratedScaffold.php`). Emits `uiComponent`, `uiClassNames`, component own-properties, nested closures, and `pageDataPosition` calls. Unit-tested in `tests/Command/GenerateFixturesCommandTest.php`.

---

### #190 — Tool: find orphaned ComponentGroups and ComponentPositions

A console command (and optionally an admin UI panel) that identifies:
- **Orphaned ComponentGroups** — groups whose owning Layout/Page/Component no longer references them
- **Orphaned ComponentPositions** — positions not linked to any active group
- **Unused components** — components that appear in no ComponentPosition

Should be read-only by default (report mode) with an optional `--fix` flag to delete.

---

### #191 — Tool: migration command for renaming components (discriminator mapping) ✓ **DONE**

Maker command `make:rename-component` (`src/Maker/MakeRenameComponent.php`).

Accepts `old-name` and `new-name` arguments (short class names). Derives dtype (`strtolower` of short name) and FQCN (`App\Entity\Component\X`) interactively, with `--old-fqcn`, `--new-fqcn`, `--old-dtype`, `--new-dtype` override options. Uses `IriConverterInterface` to resolve the collection IRI (falls back to derived `/component/kebab-name` if class not found). Generates a Doctrine migration (`src/Resources/skeleton/migration/RenameComponent.tpl.php`) that updates `dtype` in `abstract_component` and replaces the old IRI in `component_group.allowed_components` JSON. Outputs a per-group warning table (location IRI + reference) for any groups referencing the old component, plus a front-end rename checklist. Unit-tested in `tests/Maker/MakeRenameComponentTest.php`.

---

### #193 — Require a file on publish for Uploadable entities — configure via `#[UploadableField(requiredOnPublish: true)]` ✓ **DONE**

**Implemented.** `UploadableField` gains `bool $requiredOnPublish = false` and `?string $requiredOnPublishMessage = null` (`src/Annotation/UploadableField.php`). A new validator mapping loader `Validator\MappingLoader\UploadableLoader` (service `silverback.api_components.validator.mapping_loader.uploadable`, wired into `validator.builder` alongside the timestamped loader in `ValidatorCompilerPass`) walks each `#[Uploadable]` class's `UploadableField`s and, for every flagged `requiredOnPublish`, adds a **class-level** `RequiresUploadedFile` constraint (`src/Validator/Constraints/`, validator `silverback.api_components.validator.requires_uploaded_file`) in the `{ShortName}:published` group. The constraint passes when **either** the transient file property (e.g. `$file`) **or** the stored filename property (`UploadableField::$property`) is non-null (read via `PropertyAccess`, so private/public storage both work — no private-property fatal, unlike the old `Assert\Expression` on `this.filename`). The violation is attached `->atPath($fileProperty)` so the front-end maps it to the field. Message is configurable per field via `requiredOnPublishMessage` (supports the `{{ property }}` placeholder); the default fallback is ``A file must be uploaded for the `{{ property }}` field before publishing.`` The bundle-side `RequiresUploadedFileTrait` workaround is retired.

**Multiple files** scale for free — each `UploadableField` gets its own independent `RequiresUploadedFile` constraint keyed to its own file + storage property, each with its own message. Behat: `features/uploads/uploads.feature` (test entity `DummyUploadableRequiredOnPublish`, two required fields — one custom message, one default) covers publish-with-no-files → 422 with a per-field violation each, publish-with-only-one-file → 422 for the missing one, publish-with-all-files → 200.

**Edge cases → docs, not the attribute:** *"at least N of these"*, *"exactly one of a group"*, conditional requiredness stay app-side via a custom `Assert\Callback` in the `{ShortName}:published` group. File-type / size validation stays on the field via `#[Assert\File(...)]` as the file is uploaded — `requiredOnPublish` only adds the not-blank-on-publish rule.

References: `src/Validator/Constraints/RequiresUploadedFile.php`, `src/Validator/Constraints/RequiresUploadedFileValidator.php`, `src/Validator/MappingLoader/UploadableLoader.php`, `src/Validator/PublishableValidator.php` (`getShortName() . ':published'`), `src/Annotation/UploadableField.php`.

---

### #199 — Multi-field uploadables: imagine gating + shared-storage collision guard ✓ **DONE**

Surfaced while wiring a two-field uploadable (`file` + `preview`) in an app. Three fixes:

**1. Imagine only runs on raster images (not any non-SVG).** `MediaObjectFactory::createMediaObjects()` previously gated imagine-variant generation on *"not SVG"*, so a non-image (PDF/docx) uploaded to a field that declares `imagineFilters` invoked Liip Imagine on it and 500'd. Now gated on `isImagineProcessable($mimeType)` (contains `image/` **and** not `image/svg+xml`). The same guard is applied to the eager-warm path `UploadableFileManager::storeFilesMetadata()` (the dynamic `ImagineFiltersInterface` route), reading the stored file's mime before warming. Behat: `features/uploads/uploads.feature` — uploading a docx to an `imagineFilters` field → 201 with only the primary media object; an image → still gets the `thumbnail` variant.

**2. Multiple uploadable fields already work — each needs its own storage property.** The Doctrine `UploadableListener::loadClassMetadata` auto-maps a nullable string column per `UploadableField` (keyed off `UploadableField::$property`), which is why `UploadableTrait`'s unmapped `$filename` becomes a column with no `#[ORM\Column]`. The mechanism supports any number of fields; each just needs a **distinct** `property:` plus a matching nullable string entity property (the bundle maps the column). `UploadableTrait` is the single-field convenience (property defaults to `filename`); for extra fields declare e.g. `public ?string $previewFilename = null;` + `#[UploadableField(property: 'previewFilename')]`.

**3. Collision guard (the silent-corruption footgun).** Because `UploadableField::$property` defaults to the constant `'filename'`, two fields that both omit `property:` resolve to the **same** column — uploading to one overwrites the other and both fields report the same file (no error, just corruption). `UploadableAttributeReader::getConfiguredProperties()` now throws `UnsupportedAnnotationException` when two `UploadableField`s on a class share a storage `property`, so the misconfiguration fails loudly at metadata load instead. Unit-tested in `tests/AttributeReader/UploadableAttributeReaderTest.php`; the multi-field behaviour is exercised by test entity `DummyMultipleUploadable` (`file` generic + `preview` with imagine filters, distinct columns) in `features/uploads/uploads.feature`.

Not implemented (issue #199 item 3, enhancement): a field-level "generic file vs image" flag to default the `/download/{property}` disposition to `attachment` and/or skip image-dimension extraction. Left open — the download disposition is still controllable per request via `?download=true`.

References: `src/Factory/Uploadable/MediaObjectFactory.php` (`isImagineProcessable`), `src/Helper/Uploadable/UploadableFileManager.php` (`storeFilesMetadata`), `src/AttributeReader/UploadableAttributeReader.php`, `src/EventListener/Doctrine/UploadableListener.php`.

---

### Deleted-file markers are keyed per resource, never per property name ✓ **DONE**

A `PATCH {"file": null}` clears an uploadable field. `UploadableNormalizer` records that intent so `UploadableFileManager::persistFiles()` can tell "no file submitted" from "the file was explicitly removed" — the payload looks identical either way.

That marker **must be keyed on the resource being written**, held in the `\WeakMap<object, list<string>> $deletedFields` on `UploadableFileManager`. It was previously an `ArrayCollection` of bare storage-property names. `filename` is the default storage property of *every* `UploadableField`, so the marker matched any resource written afterwards, and nothing ever cleared the collection. Under **FrankenPHP worker mode** the shared service outlives the request: one admin file deletion poisoned every later write in that worker that carried no new file — a publish `PATCH {publishedAt}` being exactly that — silently deleting an unrelated resource's file and nulling its path. Symptom: the component survives with its text intact, only the file vanishes, intermittently, and never in dev (which does not run the worker Caddyfile).

Consequences for future work here:
- `addDeletedField(object $object, string $field)` takes the resource. `UploadableNormalizer::denormalize()` registers markers **after** `$this->denormalizer->denormalize(...)` returns, because only then does the object exist. For a published publishable resource that object is the draft from `PublishableNormalizer::createDraft`, which is why clearing a file on a published resource clears it on the draft and leaves the published file alone.
- Publishing continues the write against the *published* instance, so `PublishableEventListener::mergeDraftIntoPublished()` calls `transferDeletedFields($draft, $published)`. Without it, a single request that clears a file **and** publishes would silently stop clearing.
- A `WeakMap` (not a plain map plus `kernel.reset`) so entries die with the objects — the service cannot accumulate state across requests under any runtime.

**Worker mode makes request-scoped state on a shared service a whole class of bug.** Any new bundle service holding mutable per-request state needs the same treatment.

Tests: `tests/Helper/Uploadable/UploadableFileManagerTest.php` (cross-object leak, marker still works for its own object, no marker means no deletion, marker follows a merge). Behat asserts the *stored object* survives a publish — `the file for the resource :name should exist in its configured filestore` — on all four merge scenarios in `features/uploads/uploads.feature`; the pre-existing "valid download link" step only string-compares a URL built from the IRI, and the schemas only prove `filename` is non-null, so neither could catch a deleted file.

References: `src/Helper/Uploadable/UploadableFileManager.php`, `src/Serializer/Normalizer/UploadableNormalizer.php`, `src/EventListener/Api/PublishableEventListener.php`.

---

### Stored files are never deleted before their replacement is in place ✓ **DONE**

Two rules govern every write that changes an uploadable's stored path:

1. **Delete after, not before.** `PublishableEventListener::mergeDraftIntoPublished()` snapshots the published resource's paths with `getStoredFilePaths()`, runs the copy, then calls `deleteOrphanedFiles($publishedResource, $previousPaths)`. It used to call `deleteFiles($publishedResource)` *first*, inside a `catch` that swallowed the exception, so any failure in the copy left the resource pointing at a file that no longer existed.
2. **Never delete a path the resource still references.** `deleteOrphanedFiles()` compares each field's previous path with its current one and skips anything unchanged. Draft and published can legitimately share a stored path — `copyFilepath()` keeps the original path when the source is missing from the filestore — and the old code deleted it, taking the file the published resource had just inherited.

Publishing a draft that genuinely has no file still clears the published file (previous path set, current null → delete): that behaviour is unchanged.

`copyFilepath()` writes a clone's copy **beside the original**, preserving the field's `prefix` (it previously built the path from `pathinfo()['filename']`, dropping the directory), under the same tokenised naming as every other stored file, stripping an existing token first so repeated draft/publish cycles do not accumulate one per cycle. When the source object is missing it returns the **original path** rather than null — nulling turned a recoverable storage problem into permanent loss, because the next publish copied that null over the published resource's own path.

**Behat cannot reach the shared-path case through the API** (cloning always copies the file), so `the resource :resource has the same file as the resource :other` sets it up directly. Verified to fail against the old ordering before the fix landed.

References: `src/Helper/Uploadable/UploadableFileManager.php` (`getStoredFilePaths`, `deleteOrphanedFiles`, `removeFilepathValue`, `copyFilepath`), `src/EventListener/Api/PublishableEventListener.php`.

---

### Security routes: one path each, and unknown usernames are 404 ✓ **DONE**

`src/Resources/config/routing/security.php` had `api_components_resend_email_verification` registered at `/verify-email/{username}/{token}` — **the same path as `api_components_verify_email`**. Symfony resolves a duplicate path to the first match, so `ResendVerifyEmailAddressAction` was unreachable and the path the Nuxt module calls (`/resend-verify-email/{username}`, `auth.ts:97`) was registered nowhere. Correct path: username only, no token — the action *generates* a token, and its sibling `/resend-verify-new-email/{username}` already had that shape.

**A duplicate path fails nothing.** No test errors; one route silently never runs. It survived because no scenario had ever hit that route — every other security route did have one. `bin/console debug:router` is the quick check.

`UserDataProcessor::findUserByUsername()` **throws** `InvalidArgumentException('Username not found')` rather than returning null, so the `if (!$user)` guards after it are only reachable via the request-limit path. Every action calling into it must catch and return **404** (as `PasswordRequestAction` always did); without the catch an unknown username is a 500 leaking the exception message. `ResendVerifyEmailAddressAction` and `ResendVerifyNewEmailAddressAction` now do — the latter's 500 was already live, just untested.

---

### Services holding request-scoped state must be tagged `kernel.reset` ✓ **DONE**

`ResetInterface` alone does nothing — a service is only reset between requests if it carries the `kernel.reset` tag. Autoconfiguration adds it, but **this is a bundle**: an application may disable autoconfiguration, and several of the bundle's own definitions already opt out with `->autoconfigure(false)`. Nothing was tagged, so `CwaCollectorData::reset()` and `JWTEventListener::reset()` were unreachable as far as the framework was concerned.

Tagged explicitly: `mercure.resource_publisher` and `http_cache.purger` (queue changed objects), `data_collector.data` (profiler panel data), `jwt_event_listener` (holds the JWT to write as a cookie). `MercureResourcePublisher::reset()` also lowers `isPropagating`, so a request dying inside `propagate()` cannot leave the re-entrancy guard raised and suppress every publish for the rest of a worker's life.

`tests/DependencyInjection/ServicesResetterTest.php` loads the config files into a bare `ContainerBuilder` and asserts the tag is on each **definition**. It deliberately does not boot a kernel: autoconfiguration would add the tag there and mask a missing one, so a booted-kernel test would assert the wrong thing. **Add to its list whenever a bundle service gains mutable per-request state** — or better, scope the state so it cannot outlive its request, as `UploadableFileManager` does with a `WeakMap`.

> **Do not write kernel-booting tests casually.** Booting in debug registers Symfony's ErrorHandler, which PHPUnit reports as Risky, and **Infection's initial test run fails on Risky** — so a single risky test aborts the whole mutation gate in CI (`PHPUnit (Symfony 7.4)`, the coverage job) even though `failOnRisky` is unset for the normal suite. The first version of this test did exactly that and broke the build.

---

### #194 — Uploads silently overwrite another resource's file on filename collision — store under original name + unique token ✓ **DONE**

**Implemented** in `UploadableFileManager::persistFiles()`: files are now stored as `<sanitised-stem>-<token>.<ext>` (`tokeniseFilename()` — slugified stem, length-capped, `bin2hex(random_bytes(4))` token), with a `fileExists()` regeneration loop guaranteeing no upload ever overwrites another resource's file. Data-URI uploads (`UploadedDataUriFile`) keep their UUID name (no token). The original name is resolved by `resolveOriginalName()` which **prefers whichever candidate carries a file extension** — for real multipart uploads that's `getClientOriginalName()` (the on-disk file is a temp name), but in some contexts (incl. the Behat harness) the client name is absent/the form field and the file's own basename holds the real name+ext. Behat: `features/uploads/uploads.feature` "Uploading keeps the original filename with a unique token…" (one real multipart upload + a second same-source resource via a `persistFiles` helper — two consecutive authed multipart POSTs 401/415 in the harness, so that path is avoided). Content-disposition download scenarios switched from exact `filename=image.png` to `should contain "filename=image-"`.

---
<details><summary>Original issue context</summary>

`UploadableFileManager::persistFiles()` writes to `prefix . $file->getFilename()` with **no collision handling**. Two resources whose uploads share a basename (both `image.png`) resolve to the **same** storage path — the second write silently overwrites the first, and deleting/replacing one removes the file the other still references. Confirmed: a multipart `image.png` upload is stored verbatim as `image.png`.

**Desired behaviour:** every stored file gets the original filename as a readable stem plus a unique random token, so each upload is its own object and can never clobber a sibling.
- Stored name = `slugify(stem) + '-' + token + '.' + ext`; token = `bin2hex(random_bytes(4))` (unguessable, collision-proof).
- Applies to paths carrying a client/source filename: **multipart** (`UploadedFile::getClientOriginalName()`) and **fixtures** (source basename, see #195).
- **Data-URI / base64 keeps UUID** — no client filename, UUID already unique+opaque; store as clean `<uuid>.<ext>`.
- Replacing a resource's *own* file: `persistFiles()` deletes the field's current file first, so a replacement writes a fresh tokened name and removes the old — siblings untouched. Satisfies "don't overwrite, unless it's the same file being replaced."
- **Mandatory sanitisation** (strip path separators / `..`, slugify, cap length) — client name becomes a storage key, so guard path traversal.
- Belt-and-braces `fileExists()` regeneration loop upholds the no-overwrite invariant even on an astronomically unlikely token clash.

**Test (written, currently red):** `features/uploads/uploads.feature` → "Uploading keeps the original filename with a unique token and never collides with another resource's file" — two multipart `image.png` uploads must yield two distinct `image-<token>.png` files, both present. New Behat steps + `FilesystemProvider`/`UploadableAttributeReader` injection added to `features/bootstrap/UploadsContext.php`. **Note:** assertion steps use manual checks + plain exceptions, not `Assert::assert*` — PHPUnit 11's failure-message `Exporter` needs its TextUI Configuration Registry, which Behat never bootstraps, so a failing `Assert::*` fatals while rendering.

**Fallout to expect:** existing scenarios/helpers asserting bare `image.png` names change (e.g. `content-disposition: filename=…` download scenarios); several `UploadsContext` helpers create files via `persistFiles()` and will now get tokened names.

References: `src/Helper/Uploadable/UploadableFileManager.php` (`persistFiles()`, existing `copyFilepath()` suffix convention), `src/Serializer/Normalizer/UploadableNormalizer.php` (data-URI → `Uuid::uuid4()`), `src/EventListener/Api/UploadableEventListener.php` (`onPreWrite` → `persistFiles`).
</details>

---

### #195 — CwaFixtureBuilder: attach uploadable files from a local filepath (persist to configured filestore) ✓ **DONE**

**Implemented**: `CwaFixtureBuilder` now takes optional `UploadableFileManager` + `UploadableAttributeReaderInterface` (wired in `services.php`; nullable so existing unit construction still works). In `persistWithAssociations()` it calls `persistUploadedFile($entity)` — which, when the entity `isConfigured()` as Uploadable, delegates to `persistFiles($entity)` (gated because `getConfiguredProperties(…, true)` **throws** for non-uploadable classes, so it is *not* a safe unconditional call). A developer just sets `$component->file = new File($localPath)` and the file is written to the field's configured adapter on flush with the unique tokenised name from #194. Unit tests in `tests/Fixture/CwaFixtureBuilderTest.php` (`test_uploadable_component_with_file_is_persisted_via_file_manager`, `test_non_uploadable_component_does_not_call_file_manager`).

---
<details><summary>Original issue context</summary>

`CwaFixtureBuilder` / `AbstractCwaScaffold` can't seed resources with an `#[Uploadable]` file field — nothing fires the upload pipeline during a Doctrine fixture flush (`UploadableEventListener` is HTTP-only), so `filename` stays null and the resource is invalid/unrenderable.

**Desired behaviour — auto-detect, no new builder methods:**
```php
$image = new Image();
$image->file = new File(__DIR__ . '/assets/hero.jpg'); // local example file, by path
$g->add($image);
```
During the persist phase the builder, for each uploadable entity (`UploadableAttributeReader::isConfigured`), inspects its `#[UploadableField]` transient properties; if one holds a `File`, it calls `UploadableFileManager::persistFiles($entity)` before the flush. Covers both `->add()` and `->component()` paths.
- Reuses `persistFiles()` → filestore selection, `prefix`, Imagine metadata and the unique-filename / no-overwrite logic (#194) all come for free.
- Each fixture file → its own stored object; a re-used example file never shares storage between instances.
- File lands in whatever adapter the field resolves to in the current environment (local/memory in test; real store when seeding prod-like).

**Follow-on:** `GenerateFixturesCommand` (#189) should emit file attachments for uploadable components when generating from a populated DB — out of scope here, note the seam.

**Acceptance:** setting a `File` on an `#[UploadableField]` property of a fixture entity writes the file to the configured adapter and populates `filename` after flush; two entities using the same source file get independent stored files; non-uploadable / no-file entities unaffected; unit test in `tests/Fixture/CwaFixtureBuilderTest.php`.

Depends on #194. References: `src/Fixture/CwaFixtureBuilder.php` (flush phases; inject `UploadableFileManager` + `UploadableAttributeReader`), `src/Helper/Uploadable/UploadableFileManager.php` (`persistFiles`), `features/bootstrap/UploadsContext.php` (existing `new File(...)` + `persistFiles(...)` pattern).
</details>

---

### #197 — Manifest: each depth's payload is a nested resource tree (`NestedJsonStructure[]`) — front-end: cwa-nuxt-module #250 ✓ **DONE (API side)**

**Implemented.** `GET /_/resource_manifest/{id}` now returns `resource_iris` as an array indexed by rendering depth (root first) where **each element is a nested tree node `{ "iri": string, "children": [...] }`** instead of a flat `string[]`. Only `ResourceManifestNormalizer` emits `resource_iris` (via `ManifestDepthGroupTrait`; `RouteNormalizer` does not). The trait was rewritten: `buildDepthGroups` splits depths on the `parentPage`/`parentPageData` boundary (unchanged) and, within each depth, `buildDepthNodes` builds the containment tree instead of flattening — same IRI set as before, same per-depth dedup, same blank-node/`resource_metadatas`/`@`-key/back-reference exclusions (skipped/blank/duplicate resources hoist their children so no noise nodes appear). **Decisions taken:** hard swap (no parallel key — pre-alpha BC break, ships in lockstep with module #250); node key is `iri` (not `@id` — bespoke DTO field); **no per-node metadata** — and none is planned: #198 (which proposed it) was **closed as won't-do**. The front-end derives the resource type (incl. specific component type) from the IRI, so manifest metadata would be redundant or would couple the manifest cache to component internals. Placeholder/skeleton rendering is a front-end concern (developer-defined per-type templates) — requested in cwa-nuxt-module, not an API change. Tests: `tests/Serializer/Normalizer/ManifestDepthGroupTraitTest.php` asserts exact nested structures; `features/main/route.feature` + `features/main/page.feature` converted to new `DoctrineContext` steps (`the manifest depth :n root IRI should be …`, `… should have :n resource IRIs`, `… should contain the IRI …`, `… should contain/not contain an IRI matching …`) which flatten a depth's tree. Full suite green.

<details><summary>Original design notes</summary>

Change the manifest endpoints (`GET /_/resource_manifest/{id}` and the `Route:manifest:read` output) so each depth's payload is a **nested resource tree** instead of a flat list of IRIs. The **outer array stays indexed by rendering depth** (root first) — only the inner element type changes.

**Why:** the current inner `string[]` flattens away resource **containment** (route → pageData → page → componentGroup → position → component → nested groups/components). Keeping that containment as a tree gives the front-end a home for future per-node structural metadata, so it can render skeleton **placeholders that occupy the correct space before content loads** — mitigating cumulative layout shift (CLS). Keeping the outer array depth-indexed means the module's existing depth logic (`irisByDepth`, `pageIriAtDepth(depth)`) is preserved; only the per-depth payload changes.

**Proposed shape** — outer index = depth (unchanged); each element is a recursive `NestedJsonStructure = { "iri": string, "children": NestedJsonStructure[] }`:
```json
{
  "resource_iris": [
    { "iri": "/_/routes//conference", "children": [
      { "iri": "/_/page_data/parent-uuid", "children": [
        { "iri": "/_/pages/parent-template-uuid", "children": [
          { "iri": "/_/component_groups/cg-uuid", "children": [
            { "iri": "/_/component_positions/pos-uuid", "children": [
              { "iri": "/_/component/dummy-uuid", "children": [] } ] } ] } ] } ] }
    ] },
    { "iri": "/_/routes//conference/programme", "children": [ /* depth-1 tree */ ] }
  ]
}
```
Page nesting → outer array index (as today); component nesting → the per-depth tree. A flat page → single-element outer array, one tree. Each node is the future home for per-node placeholder metadata.

**Scope:** refactor `ManifestDepthGroupTrait` — keep the parentPage/parentPageData boundary split that produces the depth array, but within each depth **preserve nesting** (build `NestedJsonStructure` nodes) instead of flattening to `string[]`; blank-node / skip-IRI rules still apply per node. Update the emit in `RouteNormalizer` + `ResourceManifestNormalizer`. Update Behat (`features/main/route.feature`, `features/main/page.feature`): the `resource_iris[0][5]` DummyComponent-IRI assertion becomes a tree traversal (a node with that `iri` exists under depth 0).

**Open questions (agree before implementation):**
- **Transition** — hard-swap `resource_iris` to `NestedJsonStructure[]`, or ship the nested form under a new key (e.g. `manifest`) and deprecate the flat one? **Coordinated breaking change** with cwa-nuxt-module #250 either way; the outer array staying depth-indexed limits the blast radius to the per-depth payload.
- **Per-node placeholder metadata** — what each node carries beyond `iri`/`children` (UI component name, position sort, dimension hints). Follow-up once the tree lands.
- **Node key** — `iri` + `children` (proposed) vs. `@id` to match JSON-LD conventions used elsewhere.
- **Siblings** — sibling child pages (tab bars) still come from `GET /_/routes/{id}/children`, not the manifest. Recommend keeping out for v1.

References: `src/Serializer/Normalizer/Trait/ManifestDepthGroupTrait.php`, `src/Serializer/Normalizer/RouteNormalizer.php`, `src/Serializer/Normalizer/ResourceManifestNormalizer.php`.
</details>

> **⚠ Note:** the older `resource_iris: string[][]` description in the manifest architecture sections and design-decisions list above (e.g. "`resource_iris` is `string[][]`") is now **superseded by #197** — the shape is `NestedJsonStructure[]` (depth-indexed array of `{ iri, children }` trees).

---

### #200 — Cache-safety headers: mark auth-scoped responses non-cacheable so shared caches can distinguish public from personalised (front-end: cwa-nuxt-module #258) ✓ **DONE**

**Implemented.** Several responses are served from an identical URL but vary by the authenticated session — `Route` and `ResourceManifest` return a draft to a permitted user and the published version otherwise; `ComponentPosition` rewrites its component IRI / exposes admin-only groups by role — with no distinguishing URL or query marker. New `kernel.response` listener `CacheHeadersEventListener` (`src/EventListener/Api/CacheHeadersEventListener.php`, service `silverback.api_components.event_listener.api.cache_headers`, tagged `POST_RESPOND`) marks such responses **`Cache-Control: private, no-store`** (via `Response::setPrivate()` + `addCacheControlDirective('no-store')`, and drops `s-maxage`) **only when the request is authenticated** (`TokenStorageInterface` token whose user is a `UserInterface`) **and** the resource is affected. Anonymous requests are left untouched on API Platform's default `public` (set upstream by `AddHeadersProcessor`, a state processor that runs before this listener), so the only variant a shared cache ever stores is the published one — matching the rule Souin already enforces at the edge by excluding cookie-bearing requests. `no-store` is the authoritative marker the module's service-worker `cacheWillUpdate` drops on (cwa-nuxt-module #258).

**Design decisions (agreed with Daniel):**
- **No `Vary: Cookie`.** Many cookies churn, so varying on `Cookie` would collapse the shared-cache hit rate. Instead of varying, an authenticated response is simply marked non-cacheable; the cacheable anonymous variant needs no cookie dimension. (The existing `Vary: path` on dynamic `ComponentPosition` GETs — `ComponentPositionEventListener` — is unrelated and untouched.)
- **Personalisation gate = authenticated token**, not cookie presence — a stale/invalid cookie on an otherwise-anonymous request keeps the response cacheable.
- **Affected-resource set is an explicit, configurable allow-list**, maximising static cache hits. Config node `silverback_api_components.http_cache.personalised_resource_classes` (default `[Route, ResourceManifest, ComponentPosition]`, wired via `SilverbackApiComponentsExtension` → `$personalisedResourceClasses` arg). Any **Publishable**-configured resource is treated as personalised *in addition* to the list (matched dynamically via `PublishableAttributeReader::isConfigured()`), so app-defined publishable components are covered without enumeration. A resource **not** in the set (e.g. `Layout`) stays publicly cacheable even for authenticated users.

**Behat:** `features/main/cache_headers.feature` — scenario outlines assert authenticated GETs of Route / ResourceManifest / ComponentPosition / Publishable → `private` + `no-store`; anonymous GETs of the same → `public`, no `no-store`; and an authenticated GET of an unaffected type (`Layout`) → still `public`.

References: `src/EventListener/Api/CacheHeadersEventListener.php`, `src/DependencyInjection/Configuration.php` (`addHttpCacheNode`), `src/DependencyInjection/SilverbackApiComponentsExtension.php`, `src/Resources/config/services.php`.

> **Later:** the listener gained a second responsibility and an explicit priority under **#227** — it also caps `s-maxage`/`max-age` at the next scheduled transition, and is pinned to `POST_RESPOND - 1` because it reads an `Expires` that `PublishableEventListener` writes at plain `POST_RESPOND`.

---

### #232 — Purge the rendered HTML when a site-wide resource changes (front-end: cwa-nuxt-module #289) ✓ **DONE**

**Implemented.** cwa-nuxt-module #289 tags each cacheable rendered page with a `Surrogate-Key` built from the resource IRIs the render touched, so the existing purge already invalidates pages as well as API responses. That covers everything the front end holds as a resource — but **not** resources that shape every page without ever entering its resource store. `SiteConfigParameter` is the case that bites: `siteName`, `concatTitle`, `maintenanceModeEnabled` and the robots settings all change the rendered HTML, but the module reads them through a separate fetch that never populates the store, so no page carries a key a site-config write would purge. Pages stayed stale until the TTL lapsed.

`HttpCachePurger` now appends a **constant front-end tag** to the purge it already sends when a written resource belongs to a configured class. No new endpoint, no second round-trip: the tag rides the existing `PurgerInterface::purge()` call.

**`HttpCachePurger::RENDERED_HTML_TAG = 'cwa-html'` is a cross-repo interface contract**, and gets the same treatment as `explicitAllowOnly` and `SouinPurger::SEPARATOR`, for the same reason: **a mismatch fails silently by matching nothing.** The module holds the other half as `RENDERED_HTML_SURROGATE_KEY` (`cwa-nuxt-3-module/src/runtime/api/http-cache.ts`), prepends it to the IRI list and joins with `SURROGATE_KEY_SEPARATOR = ', '`. Both sides read/write the exact literal `cwa-html`. It is safe from colliding with a resource IRI because `IriConverterInterface::getIriFromResource()` defaults to `UrlGeneratorInterface::ABS_PATH` — every collected tag is a path starting with `/` (or, under `ABS_URL`, a `http(s)://` URL); no IRI can ever be the bare token. It is also separator-safe under both purgers (Souin `', '`, Varnish xkey `' '`) since it contains neither a comma nor a space.

**Not namespaced, deliberately.** #227 proposes `kind:value` grouping keys (`manifest:/_api/_/routes/<id>`, `nav:<layout>`, `routes:collection`) where the prefix is a *kind* qualifying a value. This tag has no value part, so `cwa:html` would only *look* consistent while actually being a different grammar. The two conventions are deliberately distinct. **Reviewed and upheld under #227**, where the three tag shapes are written down; `nav:<layout>` and `routes:collection` were declined outright, so the only `kind:value` key that will exist is `manifest:<entity-iri>`.

**The tag is sent once per purge, not once per written resource.** The guarantee is structural, not filtered: `collectResource()` sets a single `bool $purgeRenderedHtml` when the resolved resource class matches, and `propagate()` appends the constant once. A `bool` cannot be added twice, so a flush that writes ten listed resources still emits one tag. `reset()` clears the flag and the service is already tagged `kernel.reset`, so nothing survives a request under worker mode.

**Cross-request reality, stated plainly:** `propagate()` is called from exactly one place — `PropagateUpdatesListener::postFlush()` — so a purge fires **once per Doctrine flush**. `SiteConfigParameter` has only item-level operations and there is no batch operation, so **a multi-parameter save is N HTTP requests, N flushes, N purges**. The bundle cannot coalesce those without cross-request state, which the worker-mode rule forbids. This is accepted, not a defect: purging an already-purged key is a cheap no-op at the edge and the stampede lands once regardless. **Do not build a debounce.**

**This is a deliberate stampede,** which is why the class list must stay short and why it must never become a general-purpose purge endpoint. The difference between "an admin changed the site name" and "anyone who can reach this can flush the entire HTML cache" is entirely in who can trigger the write.

**Which is why `#[Post]` on `SiteConfigParameter` was fixed in the same change.** It carried **no `security:` at all** — the other four operations all had `security: SiteConfigParameter::API_SECURITY` (`is_granted('read_site_config', object)` → `publishable.permission`), but creation was gated only by the application's firewall `access_control`, i.e. any authenticated user. Under #232 that would have let any logged-in user flush the whole HTML cache. It is now `#[Post(securityPostDenormalize: SiteConfigParameter::API_SECURITY)]` — **`securityPostDenormalize`, not `security`**, because on a POST the plain `security` expression is evaluated with `object` still null (`AccessCheckerProvider`), and `SiteConfigParameterVoter::supports()` requires `$subject instanceof SiteConfigParameter`, so a null subject makes every voter abstain and denies admins too.

**Configuration** — `silverback_api_components.http_cache.purge_rendered_html_classes`, default `[SiteConfigParameter::class]`, mirroring `personalised_resource_classes` exactly (same `http_cache` node, `scalarPrototype`, `defaultValue`, wired in `SilverbackApiComponentsExtension` to `$purgeRenderedHtmlClasses`). Matching uses `is_a($resourceClass, $listed, true)`, so subclasses match. A config list rather than a hardcoded listener, because the next candidates are easy to imagine and should not need a bundle release each time.

> **Listed classes should be association-free leaf resources.** `HttpCachePurger::collectResource()` is reached for resources collected as **associations** of another write (`PropagateUpdatesListener::gatherAllAssociatedEntities`), not only for the resource actually written. `$type` cannot discriminate — associated resources are collected as `'updated'` too — so filtering on it is not an option and was deliberately not attempted. `SiteConfigParameter` has no associations, so this is inert today; listing a class that *is* reachable as an association would let unrelated writes drop the whole HTML cache.

**Deploys need no tag.** A new build changes the `/_nuxt` hashes with no resource IRI changing, which is not expressible as a resource purge — but Souin's in-memory `otter` store dies with the pod on redeploy, so it is already handled.

**Behat:** `features/main/purge_rendered_html.feature` — a listed class purging the tag on PUT / POST / DELETE; an unlisted class (`Layout` via `ComponentGroup`) purging its own IRI but **not** the tag; and a single write emitting the tag exactly once. The "several listed resources in one flush yield exactly one tag" case is **not expressible through the API** (no batch operation ⇒ never more than one written resource per request), so it lives in `tests/HttpCache/HttpCachePurgerTest.php` alongside subclass matching, no-carry-over between purges, and `reset()`.

> **Test-harness coverage gap — the Behat app does not run Souin.** `tests/Functional/app/config/packages/api_platform.yaml` selects `api_platform.http_cache.purger.varnish.xkey` with `xkey.glue: ' '`, so Behat exercises an `xkey` header joined with spaces, while production uses Souin's `Surrogate-Key` joined with `', '` and chunked at 1500 bytes. Neither the `', '` separator nor the chunking is covered. Switching the test app was considered and rejected as out of scope. Instead `ProfilerContext::collectPurgedTags()` — the single shared parser that `the resource :name should be purged from the cache` and the three new `the cache tag :tag should …` steps all use — reads whichever of `xkey` / `surrogate-key` is present and splits on `/[,\s]+/`, so it asserts correctly under either purger and a later switch is free. **Never assert an exact purge header string**: the purger, its glue and its chunking are all dependency-resolved, and `composer.lock` is gitignored so CI resolves API Platform fresh.

References: `src/HttpCache/HttpCachePurger.php`, `src/DependencyInjection/Configuration.php` (`addHttpCacheNode`), `src/DependencyInjection/SilverbackApiComponentsExtension.php`, `src/Resources/config/services_doctrine_orm_http_cache_purger.php`, `src/Entity/Core/SiteConfigParameter.php`, `features/bootstrap/ProfilerContext.php`.

---

### #211 / #212 / #215 — Maker DX: `make:page-data` prompts + correct nuxt.config snippet, `make:api-component` help text ✓ **DONE**

Three papercuts found by the docs accuracy audit, all in `src/Maker/`.

**#211 — `MakePageData` had no `interact()`.** `make:page-data ConferenceData` produced an entity with zero properties, silently. It now has an `interact()` that loops "property name → property type" until a blank name (type defaults to `?string`), matching the pattern in `MakeApiComponent`/`MakeCwaScaffold`, plus an `$io->warning()` in `generate()` when the property list ends up empty. The `--properties` option is still `VALUE_IS_ARRAY` (so `--properties a:?string --properties b:?string` works) but each value is now **split on commas**, so the natural one-liner `--properties a:?string,b:?string` works too. It was also switched from `VALUE_OPTIONAL` to `VALUE_REQUIRED`, so a bare `--properties` errors instead of injecting `null` into the parser. The space-separated form `--properties a:?string b:?string` still fails with Symfony's "Too many arguments" — that is thrown during input binding, before any maker code runs, so it cannot be intercepted; `setHelp()`/`addUsage()` document the two forms that do work and name that trap explicitly. `parseProperties()` also fixes an "Undefined array key 1" on a value with no colon (now defaults the type) and rejects an empty property name with a `RuntimeCommandException`.

**#212 — the printed nuxt.config snippet used the wrong shape.** It emitted `properties: ['headline', ...]`; the module's type is `properties?: { [propertyName: string]: string }` (`cwa-nuxt-3-module/src/runtime/types/index.ts`), a property name → admin label map read by `useDynamicPositionSelectOptions`. It now emits `properties: { headline: 'Headline', heroImage: 'Hero Image' }` with a humanised default label, and includes `name: 'Conference Data'` (also part of that config entry, read by `useDataType` → `pageDataClassName`).

**#215 — `make:api-component --timestamped` help said `updatedAt`.** `TimestampedTrait` declares `$createdAt` and `$modifiedAt`; there is no `updatedAt`. Both the option description and the interactive question now say `modifiedAt`, and `MakeApiComponentTest` reflects over `TimestampedTrait` so a future rename of the trait fields fails the test rather than silently desyncing the help text.

The two stale `updatedAt` mentions in `src/Serializer/MappingLoader/TimestampedLoader.php` and `src/Serializer/MappingLoader/UploadableLoader.php` were corrected on the #213/#214 branch (`UploadableLoader`'s was additionally a copy-paste describing the *timestamped* groups).

Tests: `tests/Maker/MakePageDataTest.php`, `tests/Maker/MakeApiComponentTest.php`.

---

### #213 / #214 — Two guards that looked like they worked and never ran ✓ **DONE**

Both found by the docs accuracy audit. Neither was quite what the issue described, and in both cases the correction changed which fix was right.

**#213 — `createdAt` was writable, but only on entities that skip `TimestampedTrait`.** `TimestampedDataPersister` keeps whatever `createdAt` an existing object carries, and the serializer has already written the request body onto that object by the time it runs. `TimestampedTrait::setCreatedAt()` ignores a second value, and PropertyAccess prefers the setter over the public property — so every entity in the bundle was shielded and the bug was invisible. An application entity is under no obligation to use the trait.

**Dropping the `:timestamped:write` group is not enough on its own.** `TimestampedContextBuilder` returns early when `$context['groups']` is empty, so a resource that declares no serialization groups gets no group filtering at all. Enforcement therefore lives in `TimestampedNormalizer::denormalize()`: capture `createdAt` off the object being populated *before* denormalizing, restore it after. The write group is still dropped from `createdAt`, but that only stops it being advertised as writable in the generated input schema — it is documentation, not enforcement. `persistTimestampedFields($entity, true)` is untouched, so every seeding path (`CwaFixtureBuilder`, `RouteGenerator`, `UserFactory`, `DoctrineContext`) is unaffected.

> **`OBJECT_TO_POPULATE` is not always an instance of `$type`.** Denormalizing a collection property (e.g. `ComponentGroup.pages`) leaves the Doctrine `PersistentCollection` in that context key while `$type` is still the entity class. Anything reading bundle metadata off the object-to-populate must gate on `instanceof $type` — the first version of this fix asked the attribute reader for a `Timestamped` configuration on a `PersistentCollection` and 500'd two unrelated Behat scenarios.

**#214 — `isRequired()` under a node that carries a default is never enforced.** `ArrayNode::finalizeValue()` inserts the default and `continue`s without finalising, so the required-child check never runs for an omitted node. `user.email_verification` resolved to `['enabled' => true]`, the extension read keys that were not there (26 undefined-array-key warnings on a minimal config), and services typed `bool` were wired with `null`. The same declaration made the node impossible to configure *partially* — supplying anything, including `enabled: false`, ran finalisation and hard-failed on the first missing required child.

**Convention going forward: never put `isRequired()` on a *child* of a node that carries `addDefaultsIfNotSet()` or `canBeDisabled()`.** Either give the child a real default, or make the **parent node itself** `isRequired()` — `ArrayNode::finalizeValue()` checks `isRequired()` at `:214` *before* inserting a default at `:227`, so a required node is enforced on omission while its own children still resolve their defaults when it is present. A node-level `->validate()` expresses invalid **combinations**, not required **presence**: it runs in `finalize()`, i.e. only when the node is present, which is precisely the case that already worked. `user.email_verification` rejects `verify_on_register`/`verify_on_change` without a redirect target that way, and `refresh_token` demands `options.class` for the doctrine handler the same way (#222).

Defaults were chosen to be **all-off** rather than "correct": `deny_unverified_login: true` would lock users out of an application that never configured verification, and `verify_on_register: true` would send emails for which no redirect target exists (`AbstractUserEmailFactory::getTokenPath()` throws). Making the children genuinely required was rejected as a breaking change — it would force config on every application currently omitting the node.

The `email` sub-node had no default of its own, so `new_email_confirmation` and `password_reset` had the same defect; all three now use `addDefaultsIfNotSet()` with `default_redirect_path` defaulting to null (which is exactly the previous effective behaviour — `AbstractUserEmailFactory` accepts null and throws a clear exception at send time). `email_verification.enabled` is now actually honoured by `VerifyEmailFactory`, which previously received a hardcoded `true`.

**Still carrying the same latent pattern, deliberately unfixed** because every fix forces configuration onto existing applications — decide before touching: `user.class_name`, `refresh_token.*`, `publishable.permission`, and `refresh_token.options.class` (read unguarded under the doctrine storage branch).

Tests: `tests/DependencyInjection/ConfigurationTest.php` and `tests/DependencyInjection/SilverbackApiComponentsExtensionTest.php` (bare `ContainerBuilder`, no kernel boot — see the Risky/Infection warning above), `tests/Serializer/MappingLoader/TimestampedLoaderTest.php`, `tests/Serializer/Normalizer/TimestampedNormalizerTest.php`, and `features/timestamped/timestamped.feature` (PATCH scenarios for both the guarded and unguarded entity shapes).

> **Newly covering a large declarative file costs MSI.** These DI tests pulled `Configuration.php` and `SilverbackApiComponentsExtension.php` into the covered set for the first time, so their mutants started counting where `--only-covered` had been skipping them — MSI fell from 85% to 82% against an 80% gate. The fix was to broaden the extension test to assert the wiring it performs, not to exclude the files.

---

### #237 — `orWhere()` in a Doctrine filter silently discards every query-extension predicate ✓ **DONE**

`OrSearchFilter::addWhereByStrategy()` built its clauses with `$queryBuilder->orWhere(...)`. **Doctrine's `orWhere()` ORs against the entire accumulated WHERE, not just among the filter's own clauses.** AP4 registers `FilterExtension` at priority `-16` while this bundle's query extensions carry no explicit priority and therefore default to `0` — higher priority runs first, so the extensions add their predicates **before** the filter runs and the filter then ORs them away:

```
(liveAt IS NOT NULL AND liveAt <= :now) OR (o.path LIKE :path)
```

An anonymous `GET /_/routes?path=launch` therefore listed a route scheduled for 2999. The filter parameter is attacker-controlled, so the predicate being defeated is a security one. Confirmed empirically to defeat `RouteExtension` (own `liveAt`), `RouteAncestorGateResolver` (ancestor gate, #234) **and** `PublishableExtension` (an anonymous filtered collection returned every draft alongside the published resources).

**The general trap, not a one-off: never use `orWhere()` in an API Platform filter.** Anything already on the query — a publication gate, a draft exclusion, an application's own extension — is inside the left operand of that OR and is discarded the moment the filter matches. Collect the filter's own clauses and apply them with a **single `andWhere()`** wrapping one `Expr\Orx`, which is the shape API Platform's own `SearchFilter` uses.

**The accumulator must be shared across `addWhereByStrategy()` calls.** `AbstractFilter::apply()` calls `filterProperty()` once per query parameter, and each of those calls `addWhereByStrategy()` once — so an `Orx` built *inside* `addWhereByStrategy()` would turn multi-field search into AND and destroy the filter's whole purpose. `apply()` is overridden to clear the accumulator, delegate to the parent, then apply the single `andWhere()`; the accumulator is cleared again in a `finally` so no state survives the request under worker mode.

Doctrine's DDC-1237 handling in `Expr\Composite::processQueryPart()` parenthesises any part whose string contains ` OR ` / ` AND `, which is what keeps the `word_start` strategy (a two-`LIKE` string clause) from capturing the preceding predicate once it is ANDed on.

**`addWhereByStrategy()`'s five single-value branches are unreachable from `filterProperty()`** — `normalizeValues((array) $value, ...)` always hands it an array — but they were fixed too, since the method is `protected`.

Tests: `tests/Filter/OrSearchFilterTest.php` asserts the DQL shape across all five strategies (case-sensitive and `i`-prefixed, single and multi-value) plus cross-field OR, the `word_start` parenthesisation and non-leakage between two `apply()` calls; `features/main/or_search_filter.feature` covers the scheduled, draft and ancestor-gated route cases, the publishable draft case, and two guards proving the filter still ORs across fields and across multiple values for one field. `DummyOrSearchFilterable` had existed with no coverage of any kind, which is why this survived.
