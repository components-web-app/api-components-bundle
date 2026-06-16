# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Working Principles

### Principle of least exposure
Only expose API fields and serialization groups when they are concretely needed by a consumer. Add serialization only when there is a real, tested requirement for it.

### TDD process
All feature work follows this cycle:
1. Explain what we're about to do and why, with a proposed test (Behat scenario or PHPUnit test)
2. Agree on whether the test is correct (Daniel is the author and has deep system knowledge — expect discussion)
3. Write or adjust the test, then write the code to make it pass
4. Keep CLAUDE.md current throughout — update mid-task if the design shifts, not just at the end

---

## Overview

`components-web-app/api-components-bundle` is a Symfony bundle that provides the API layer for the CWA (Components Web App) framework. It exposes a flexible, component-driven page structure via API Platform, handles route generation, security, file uploads, and real-time push via Mercure.

Companion project: **CWA Nuxt Module** (`@cwa/nuxt`) — the frontend that consumes this API. Local source at `/Users/danielwest/Documents/GitHub/_CWA/cwa-nuxt-3-module`. The two projects must be kept in sync on shared concepts (serialization groups, resource types, nested page conventions).

## Commands

```bash
# Unit tests
php -d memory_limit=256M vendor/bin/phpunit

# Integration tests (Behat)
php -d memory_limit=256M vendor/bin/behat

# Database setup for tests
php tests/Functional/app/bin/console -e test doctrine:database:create
php tests/Functional/app/bin/console -e test doctrine:migrations:migrate --no-interaction
php tests/Functional/app/bin/console -e test doctrine:schema:validate
```

Behat features live in `features/`. PHPUnit tests in `tests/`. Behat coverage is more extensive than unit — prefer adding Behat scenarios for new API behaviour, unit tests for pure logic.

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

### Serialization groups

The module fetches resources using the `Route:manifest:read` normalization context (endpoint: `GET /_/resource_manifest/{id}`). This group controls what the Nuxt module sees.

Key current group assignments:
- `Route`: `page`, `pageData` → `Route:manifest:read`
- `AbstractPageData`: `page` (the Page template IRI) → `Route:manifest:read`
- `AbstractPage`: `route`, `parentPage`, `parentPageData` → `Route:manifest:read`

### Bug: `Layout.componentGroups` returns embedded objects instead of IRIs

**Symptom (discovered 2026-06-16):** The navigation bar is empty for unauthenticated users. No failed requests appear in the network tab — the layout's component group contents are simply never fetched.

**Root cause:** `GET /_api/_/layouts/{uuid}` returns `componentGroups` as **full embedded objects**:
```json
"componentGroups": [{ "@id": "/_api/_/component_groups/...", "location": "top", "componentPositions": [...] }]
```

The module's `fetchAssociatedResources` expects all associated property values to be **IRI strings** (this is both the module's contract and the caching architecture principle: "Never embed related resource data — always return IRIs"). Receiving objects instead of strings causes a silent TypeError (`object.split is not a function`) that is swallowed by `fetchBatch`, so the component groups are never stored and `CwaComponentGroup` finds nothing.

**Why page content still works:** The manifest response includes page component group IRIs directly in `resource_iris`, so they are fetched as standalone resources in the manifest batch. Layouts have no manifest and rely entirely on `fetchAssociatedResources`.

**Required fix:** Change the `Layout` serialization group so `componentGroups` is serialized as an array of IRI strings only (not embedded objects). Check `ComponentGroup.componentPositions` for the same issue — the module also expects these to be IRI strings.

The module will be updated with a defensive `@id` extraction as a fallback, but the correct fix is here: the API must return IRIs, not embedded objects, for all associated resource properties.

### API endpoints

| Endpoint | Purpose |
|----------|---------|
| `GET /_/routes/{path}` | Resolve a path to a Route resource |
| `GET /_/resource_manifest/{id}` | Unified manifest endpoint — `{id}` starting with `/` resolves to a Route by path; a UUID resolves to a `Page` or `AbstractPageData` entity. Returns `{ "resource_iris": string[][] }`. |
| `POST /routes/generate` | Auto-generate a Route for a Page/PageData |
| `GET /routes/{id}/redirects` | Follow the redirect chain for a Route |

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
4. **Behat tests** — `features/main/route.feature`: nested PageData and nested Page manifests both tested; `features/main/page.feature`: create with parentPage (201), create with parentPageData (201), both set (422), PATCH to set parentPage (200), flat PageData manifest (200), nested PageData manifest (200)
5. **`/_/resource_manifest/{id}` unified endpoint** — `ResourceManifest` DTO (`src/ApiResource/ResourceManifest.php`) with `ResourceManifestStateProvider` resolving route paths (starts with `/`) or UUIDs (Page then AbstractPageData). `ResourceManifestVoter` delegates access control to `RouteVoter` or `AbstractRoutableVoter`. `ResourceManifestNormalizer` produces `{ "resource_iris": string[][] }` using the shared `ManifestDepthGroupTrait`.
6. **`ManifestDepthGroupTrait`** (`src/Serializer/Normalizer/Trait/ManifestDepthGroupTrait.php`) — `buildDepthGroups`, `collectCurrentDepth`, `shouldSkipIri` extracted and shared between `RouteNormalizer` and `ResourceManifestNormalizer`

### Outstanding — `parentPage` in standard Page read group

**Requirement (discovered 2026-06-15):** The Nuxt module's admin parent-page picker must filter out descendants of the current page to prevent circular parent chains (e.g. A → B → A). The picker is populated from `GET /_/pages` (via `useParentPageLoader`). To detect descendants client-side, each page in that collection response must include its own `parentPage` IRI.

Currently `parentPage` is only in `Route:manifest:read`. It needs to be added to whatever serialization group drives the `/_/pages` collection read (e.g. `Page:read` or a shared `AbstractPage:read` group). This satisfies the principle of least exposure — there is a concrete consumer (the admin picker descendant-filter).

A Behat test should cover: `GET /_/pages` response includes `parentPage` for a page that has one set.

### Outstanding — UUID-based manifest must walk the `parentPage` chain

**Bug (discovered 2026-06-16):** When the Nuxt module admin accesses a nested `Page` entity directly via its admin URL (e.g. `/_cwa/%2F_api%2F_%2Fpages%2F{child-uuid}`), the fetcher calls `GET /_api/_/resource_manifest/{child-uuid}`. The module code is correct: it uses `irisByDepth[0]` as the parent depth and renders `pageIriAtDepth(depth)` for each level. However, the admin admin page displays only a placeholder (no parent content) because the manifest endpoint currently returns only the accessed page in a single depth group — it does not walk the `parentPage`/`parentPageData` chain upward.

**Required fix:** `ResourceManifestNormalizer` (or `ResourceManifestStateProvider`) when resolving by Page UUID must walk the `parentPage`/`parentPageData` chain to the root and produce `resource_iris: string[][]` with one inner array per depth level, root first — exactly as the route-path path does when the manifest normalizer walks the embedded parent sub-tree via the `Route:manifest:read` group.

For a chapter `Page` entity whose `parentPage` is a topic `Page`:
```json
{
  "resource_iris": [
    ["/_/pages/topic-uuid", "/_/component_groups/...", ...],
    ["/_/pages/chapter-uuid", "/_/component_groups/...", ...]
  ]
}
```

The fix should mirror what `RouteNormalizer` does when following `parentPage`/`parentPageData` during route-based manifest generation. The `ManifestDepthGroupTrait` `buildDepthGroups` should already handle this if the correct sub-tree is passed in — check whether `ResourceManifestNormalizer` is passing the full serialized entity (including embedded parent data) or only the top-level page object.

A Behat test should cover: `GET /_/resource_manifest/{child-page-uuid}` for a page with `parentPage` set returns `resource_iris` with two depth groups (parent resources first, child resources last).

---

## Feature: CwaFixtureBuilder

> **Status: Design agreed, not yet implemented.**

A fluent builder API that lets developers scaffold CWA website structure (layouts, pages, component groups, components, routes) in Doctrine fixture code with minimal boilerplate. The Doctrine Fixtures Bundle handles execution; this feature adds the ergonomic PHP API on top.

### Dream developer API

```php
class AppScaffold extends AbstractCwaScaffold
{
    public function build(CwaFixtureBuilder $cwa): void
    {
        $cwa->layout('default', 'CwaLayout', function(LayoutBuilder $layout) {
            $layout->group('navigation', fn(GroupBuilder $g) => $g
                ->add(new NavigationLink('Home', '/'))
                ->add(new NavigationLink('Blog', '/blog'))
            );
        });

        $cwa->page('home', 'HomePage', layout: 'default', route: '/', function(PageBuilder $page) {
            $page->group('hero', fn(GroupBuilder $g) => $g->add(new HtmlContent('<h1>Welcome</h1>')));
            $page->group('body', fn(GroupBuilder $g) => $g->add(new HtmlContent('Intro text')));
        });

        // Complex pages extract cleanly to private methods via PHP 8.1 first-class callables
        $cwa->page('blog', 'BlogPage', layout: 'default', route: '/blog', $this->buildBlog(...));

        $cwa->page('conference', 'ConferencePage', layout: 'default', route: '/conference', $this->buildConference(...));
    }

    private function buildBlog(PageBuilder $page): void
    {
        $page->group('listing', fn(GroupBuilder $g) => $g->add(new Collection()));
        $page->nested($this->buildBlogArticles(...));
    }

    private function buildBlogArticles(CwaFixtureBuilder $cwa): void
    {
        foreach ($this->articles() as $data) {
            // route auto-generated: /blog/first-post (parent path + slug from title via RouteGenerator)
            $cwa->pageData(new BlogArticleData(title: $data['title']), template: 'blog-article');
        }
    }

    private function buildConference(PageBuilder $page): void
    {
        $page->group('details', fn(GroupBuilder $g) => $g->add(new HtmlContent('Details')));
        $page->nested(function(CwaFixtureBuilder $cwa) {
            // /conference/programme, /conference/speakers — auto-prefixed via RouteGenerator
            $cwa->pageData(new ConferenceData(title: 'Programme'), template: 'conference-section');
            $cwa->pageData(new ConferenceData(title: 'Speakers'), template: 'conference-section');
        });
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
  ->layout(ref, uiComponent, ?Closure)  → LayoutBuilder
      ->group(name, ?allow[], Closure)  → LayoutBuilder  (closure receives GroupBuilder)
  ->page(ref, uiComponent, layout, ?route, ?Closure)  → PageBuilder
      ->group(name, Closure)  → PageBuilder
      ->nested(Closure)  → PageBuilder  (CwaFixtureBuilder in closure has parent context)
  ->pageData(AbstractPageData, ?template, ?Closure)  → PageDataBuilder
      ->route(path)  → PageDataBuilder

GroupBuilder
  ->add(AbstractComponent, ?sort)  → GroupBuilder  (sort defaults to insertion order)
```

### Route auto-generation rules

| Situation | Result |
|---|---|
| `route: '/'` explicit | uses that path |
| no `route:` on `->page()` | no Route created (it's a template) |
| `->pageData(...)` inside `->nested()`, no route | calls `RouteGenerator` with parent context → `/parent-path/slug-from-title` |
| `->pageData(...)` at top level, no route | no Route created (draft) |

### What the builder handles invisibly

- `TimestampedDataPersister` called on each entity
- `$manager->persist()` for all entities
- Deduplication by reference (call `->layout('default', ...)` twice → same entity returned)
- `ComponentPosition` wrapping and sort order
- `setRoute()` / `setPageData()` bidirectional linking
- Parent context propagation through `->nested()` so `parentPage`/`parentPageData` is set automatically

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