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

### ~~Bug: `Layout.componentGroups` returns embedded objects instead of IRIs~~ — FIXED

**Symptom (discovered 2026-06-16):** The navigation bar was empty for unauthenticated users. `GET /_api/_/layouts/{uuid}` returned `componentGroups` as embedded JSON-LD objects; the Nuxt module expected IRI strings.

**Root cause:** AP4 reads `readableLink` from getter methods, not property declarations. `Layout` had no explicit normalization context and `UiTrait.getComponentGroups()` had no `#[ApiProperty]` override — so AP4 auto-embedded the collection.

**Fix (committed 2026-06-16):**
- Added explicit `normalizationContext: ['groups' => ['Layout:read']]` and `denormalizationContext` to `Layout#[ApiResource]`
- Added `#[Groups(['Layout:read', 'Layout:write'])]` to `Layout.reference`, `Layout.pages`, `Layout.componentGroups`, and `UiTrait.uiComponent` / `UiTrait.uiClassNames`
- Overrode `getComponentGroups()` in `Layout.php` with `#[ApiProperty(readableLink: false, writableLink: false)]` so AP4 returns IRI strings

The Nuxt module also has a defensive `value?.['@id']` extraction fallback in `fetchAssociatedResources`, but the authoritative fix is this API-side change.

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
7. **`pageDataProperty` component IRIs in manifests** — `PageDataNormalizer` injects `cwa_current_page_data` into the serialization context when `Route:manifest:read` is active. `ComponentPositionNormalizer.normalizeForPageData()` reads this context key and resolves `pageDataProperty` positions during manifest generation without requiring an HTTP `path` header. `ManifestDepthGroupTrait.collectCurrentDepth()` now also collects string IRI values from non-blank-node subresources (AP4 returns component IRIs as strings when `AbstractComponent` has no `Route:manifest:read` fields). Blank node resources (`/.well-known/genid/...`) are excluded from string IRI collection to avoid leaking internal metadata IRIs (e.g. `pageDataMetadata`). Behat test in `features/main/route.feature` covers `resource_iris[0][5]` matching a DummyComponent IRI.
8. **`Layout.componentGroups` returns IRI strings** — see fixed bug above. Behat test in `features/main/layout.feature` covers `componentGroups[0]` equal to the component group IRI.

### Outstanding — `parentPage` in standard Page read group

**Requirement (discovered 2026-06-15):** The Nuxt module's admin parent-page picker must filter out descendants of the current page to prevent circular parent chains (e.g. A → B → A). The picker is populated from `GET /_/pages` (via `useParentPageLoader`). To detect descendants client-side, each page in that collection response must include its own `parentPage` IRI.

Currently `parentPage` is only in `Route:manifest:read`. It needs to be added to whatever serialization group drives the `/_/pages` collection read (e.g. `Page:read` or a shared `AbstractPage:read` group). This satisfies the principle of least exposure — there is a concrete consumer (the admin picker descendant-filter).

A Behat test should cover: `GET /_/pages` response includes `parentPage` for a page that has one set.

### Bug: PATCH `/_/pages/{uuid}` throws 500 when body contains `componentGroups`

**Symptom (discovered 2026-06-17):** Saving a Page from the Nuxt admin modal returns:
```
500: Warning: Undefined property: Doctrine\Common\Collections\ArrayCollection::$sortValue
```

**Trigger payload:** The Nuxt module admin modal PATCHes the full resource data including `componentGroups` as an array (either IRI strings or embedded JSON-LD objects, depending on what was returned in the GET). Example body:
```json
{
  "@type": "Page",
  "reference": "My Page",
  "layout": "/_/layouts/uuid",
  "componentGroups": ["/_/component_groups/uuid1", "/_/component_groups/uuid2"]
}
```

**Likely root cause:** The Symfony deserializer processes the `componentGroups` array and, during denormalization of `ComponentGroup` entities, either a lifecycle callback or the `ComponentPositionNormalizer` accesses `$sortValue` on the `ComponentGroup.componentPositions` `ArrayCollection` as if it were a scalar property — rather than iterating over individual `ComponentPosition` entities. This results in PHP trying to read `$sortValue` on the `ArrayCollection` object itself.

**Where to look:**
- `ComponentGroup` entity — any `#[ORM\PrePersist]` / `#[ORM\PreUpdate]` listener that reads `componentPositions->sortValue`
- `ComponentPositionNormalizer` — any code path triggered during `PATCH` denormalization that accesses the position collection
- Symfony's denormalization of `ComponentGroup.componentPositions` when the PATCH body includes inline `componentGroups`

**Workaround (Nuxt module, committed 2026-06-17):** `PageAdminModal` passes `excludeFields: ['componentGroups']` to `useItemPage`, so `componentGroups` is stripped from the PATCH body before sending. This bypasses the bug without fixing it. A proper API-side fix is still needed so that PATCH requests including `componentGroups` do not 500.

**Test to write:** Behat scenario — `PATCH /_/pages/{uuid}` with `componentGroups` in the request body returns 200, not 500.

---

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
                ->pageDataPosition('image')      // dynamic — resolved from BlogArticleData.image at render time
                ->pageDataPosition('htmlContent')
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
                ->pageDataPosition('introContent')
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
  ->layout(ref, uiComponent): LayoutBuilder          (deduped by ref; returns same builder if called twice)
  ->page(ref, uiComponent, layout, ?route, ?routeName, isTemplate=false, ?title, ?Closure): PageBuilder
  ->pageData(AbstractPageData, ?template, ?route, ?routeName, ?Closure): PageDataBuilder
  ->getRoute(routeName): Route                        (look up a named route already created)

LayoutBuilder
  ->group(name, allow: [], ?Closure): GroupBuilder   (returns the GroupBuilder; same name = same group)

PageBuilder
  ->title(string): self
  ->metaDescription(string): self
  ->group(name, ?Closure): GroupBuilder
  ->nested(Closure): void                            (Closure receives CwaFixtureBuilder with parent context)
  ->getRoute(): ?Route                               (route after builder flushes RouteGenerator)

PageDataBuilder
  ->nested(Closure): void                            (Closure receives CwaFixtureBuilder with parent context)
  ->getRoute(): ?Route

GroupBuilder
  ->add(AbstractComponent, ?sort): self              (sort defaults to insertion order × 10)
  ->pageDataPosition(propertyName, ?sort): self      (creates ComponentPosition with pageDataProperty set)
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

### Internal flush ordering

The builder manages persisting in the correct order. Roughly:

1. Persist all Layout, Page, and AbstractPageData entities (no relations yet)
2. `flush()` — entities get UUIDs
3. Create ComponentGroups (keyed by entity IRI + location name for deduplication)
4. `flush()`
5. Call `RouteGenerator::create()` for all auto-routed entities (parents before children — breadth-first)
6. `flush()` — routes now have paths
7. Create ComponentPositions and nav-bar links (which may reference routes created in step 5)
8. Final `flush()`

`->getRoute(routeName)` and `PageDataBuilder/PageBuilder->getRoute()` are only valid after step 5 completes. The builder defers all closures to the correct phase internally. Closures registered against GroupBuilder via `->add()` or `->pageDataPosition()` are evaluated in phase 7. The `->nested()` closure is evaluated during phase 5 so parent routes exist before child routes are generated.

### What the builder handles invisibly

- `TimestampedDataPersister->persistTimestampedFields($entity, true)` on every entity
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