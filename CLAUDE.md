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
4. **Behat tests** — `features/main/route.feature`: nested PageData and nested Page manifests both tested; `features/main/page.feature`: create with parentPage (201), create with parentPageData (201), both set (422), PATCH to set parentPage (200), flat PageData manifest (200), nested PageData manifest (200), nested Page manifest (200)
5. **`/_/resource_manifest/{id}` unified endpoint** — `ResourceManifest` DTO (`src/ApiResource/ResourceManifest.php`) with `ResourceManifestStateProvider` resolving route paths (starts with `/`) or UUIDs (Page then AbstractPageData). `ResourceManifestVoter` delegates access control to `RouteVoter` or `AbstractRoutableVoter`. `ResourceManifestNormalizer` produces `{ "resource_iris": string[][] }` using the shared `ManifestDepthGroupTrait`.
6. **`ManifestDepthGroupTrait`** (`src/Serializer/Normalizer/Trait/ManifestDepthGroupTrait.php`) — `buildDepthGroups`, `collectCurrentDepth`, `shouldSkipIri` extracted and shared between `RouteNormalizer` and `ResourceManifestNormalizer`
7. **`pageDataProperty` component IRIs in manifests** — `PageDataNormalizer` injects `cwa_current_page_data` into the serialization context when `Route:manifest:read` is active. `ComponentPositionNormalizer.normalizeForPageData()` reads this context key and resolves `pageDataProperty` positions during manifest generation without requiring an HTTP `path` header. `ManifestDepthGroupTrait.collectCurrentDepth()` now also collects string IRI values from non-blank-node subresources (AP4 returns component IRIs as strings when `AbstractComponent` has no `Route:manifest:read` fields). Blank node resources (`/.well-known/genid/...`) are excluded from string IRI collection to avoid leaking internal metadata IRIs (e.g. `pageDataMetadata`). Behat test in `features/main/route.feature` covers `resource_iris[0][5]` matching a DummyComponent IRI.
8. **`Layout.componentGroups` returns IRI strings** — see fixed bug above. Behat test in `features/main/layout.feature` covers `componentGroups[0]` equal to the component group IRI.

### ~~Outstanding — `parentPage` in standard Page read group~~ — ALREADY WORKS

`parentPage` already appears in `GET /_/pages` responses for pages that have a parent set. Because `Page` has no explicit `normalizationContext`, AP4 does not inject a `groups` key into the Symfony serializer context. Without a `groups` key, the Symfony serializer ignores all `#[Groups]` annotations and serializes all accessible properties — including `parentPage` via `getParentPage()`. No code change was needed; Behat test added to `features/main/page.feature` for coverage.

### ~~Bug: PATCH `/_/pages/{uuid}` throws 500 when body contains `componentGroups`~~ — FIXED

**Symptom (discovered 2026-06-17):** Saving a Page from the Nuxt admin modal triggered a 500/422 when the PATCH body contained `componentGroups` as embedded JSON-LD objects (AP4 tried to denormalize the component positions collection, which caused `ArrayCollection::$sortValue` access errors).

**Fix (committed 2026-06-17):** Overrode `getComponentGroups()` in `Page.php` with `#[ApiProperty(writable: false)]`. AP4 now ignores `componentGroups` during deserialization — whether sent as IRI strings or embedded objects. Component groups are managed via their own endpoints.

**Behat tests:** Two scenarios in `features/main/page.feature` — PATCH with IRI-string componentGroups (200), PATCH with embedded componentGroups including positions (200).

---


---

## API contracts: Route UI/UX (from Nuxt module design discussion)

### 1. Cascade child path update on route PATCH — COMPLETE ✓

`PATCH /_/routes/{id}` accepts optional `cascadeChildPaths: true` boolean. When set and `path` changes, `RouteEventListener.onPostWrite` walks direct children via `AbstractPage.parentPage`/`parentPageData`, updates their route paths (prefixing with the new parent path), and creates redirects from old to new child paths. Children whose path does not start with the old prefix are ignored. Intermediate flush required before creating child redirects (Doctrine processes INSERTs before UPDATEs; flushing path changes first frees old paths in the DB). Behat tests in `features/main/route.feature`.

### 2. Route children endpoint — COMPLETE ✓

`GET /_/routes/{id}/children` returns the recursive child tree for a specific route. Admin-only (`ROLE_ADMIN`). Response:
```json
{
  "children": [
    {
      "route": "/_/routes//conference/programme",
      "path": "/conference/programme",
      "children": []
    }
  ]
}
```
Each node embeds its direct children recursively so the full sub-tree is visible in one request. Children are plain objects (not IRIs) since you need the tree structure to navigate it; `route` and `path` are the only data fields per node — all other route detail is fetched via the IRI.

Implementation: `RouteChildren` + `RouteChildrenNode` DTOs, `RouteChildrenStateProvider`, `RouteChildrenNormalizer`. Custom `Get` operation added to `Route` at `/routes/{id}/children`. The standard Route `Get` requirement updated to `(?!.+\/(?:redirects|children)$).+` to exclude both sub-resource suffixes. Behat tests in `features/main/route.feature`.

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
  ->onRoutesCreated(Closure): self                   (Closure receives array<PageBuilder> of direct child page builders; called after phaseThree so child route paths are available)
  ->getRoute(): ?Route

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

### Internal flush ordering

The builder manages persisting in the correct order. Roughly:

1. Persist all Layout, Page, and AbstractPageData entities (no relations yet)
2. `flush()` — entities get UUIDs
3. Create ComponentGroups (keyed by entity IRI + location name for deduplication)
4. `flush()`
5. Call `RouteGenerator::create()` for all auto-routed entities (parents before children — breadth-first)
6. `flush()` — routes now have paths
6.5. Call `onRoutesCreated` callbacks on any `PageDataBuilder` that registered one, passing the child `PageBuilder` instances tracked during `evaluateNested()`. The callback mutates already-persisted entity properties (e.g. sets `HtmlContent.html` with real child paths). Followed by a `flush()` to persist those changes.
7. Create ComponentPositions and nav-bar links (which may reference routes created in step 5)
8. Final `flush()`

`->getRoute(routeName)` and `PageDataBuilder/PageBuilder->getRoute()` are only valid after step 5 completes. The builder defers all closures to the correct phase internally. Closures registered against GroupBuilder via `->add()` or `->pageDataPosition()` are evaluated in phase 7. The `->nested()` closure is evaluated during phase 5 so parent routes exist before child routes are generated.

### `onRoutesCreated` — implementation plan

**Use case:** A `PageData` entity has a component whose content must reference child page URLs (e.g. an `HtmlContent` with links to the child pages). Child routes don't exist at entity-creation time, so the content must be set after `phaseThree`.

**Required changes in `CwaFixtureBuilder`:**

In `evaluateNested()`, record which page refs were registered by each nested closure and store them on the `PageDataBuilder`:

```php
foreach ($this->pageDataSpecs as $spec) {
    $closure = $spec['builder']->getNestedClosure();
    if (null === $closure) continue;
    $beforePageRefs = array_keys($this->pageSpecs);
    $this->parentContext = $spec['builder']->getPageData();
    $closure($this);
    $this->parentContext = null;
    $addedRefs = array_diff(array_keys($this->pageSpecs), $beforePageRefs);
    $spec['builder']->setChildPageRefs(array_values($addedRefs));
}
```

Add a new `phaseThreePointFive()` called between `phaseThree()` and `phaseFour()` in `flush()`:

```php
private function phaseThreePointFive(): void
{
    $hasChanges = false;
    foreach ($this->pageDataSpecs as $spec) {
        $cb = $spec['builder']->getOnRoutesCreated();
        if (null === $cb) continue;
        $childBuilders = array_values(array_filter(array_map(
            fn($ref) => $this->pageSpecs[$ref]['builder'] ?? null,
            $spec['builder']->getChildPageRefs(),
        )));
        $cb($childBuilders);
        $hasChanges = true;
    }
    if ($hasChanges) {
        $this->manager->flush();
    }
}
```

**Required changes in `PageDataBuilder`:**

```php
private ?\Closure $onRoutesCreated = null;
private array $childPageRefs = [];

public function onRoutesCreated(\Closure $cb): self { $this->onRoutesCreated = $cb; return $this; }
public function getOnRoutesCreated(): ?\Closure { return $this->onRoutesCreated; }
public function setChildPageRefs(array $refs): void { $this->childPageRefs = $refs; }
public function getChildPageRefs(): array { return $this->childPageRefs; }
```

**App usage (`AppScaffold`):**

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

- `TimestampedDataPersister->persistTimestampedFields($entity, true)` on entities that have the `#[Timestamped]` annotation (Layout, Page, AbstractPageData, ComponentGroup). **Not** called on `AbstractComponent` subclasses — see open issue below.
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

These are known open issues with enough context to resume work without re-investigation.

### ~~`CwaFixtureBuilder.component()` throws for non-timestamped entities~~ — FIXED

**Fixed (commit `b438d60d`):** `TimestampedDataPersister.isConfigured()` added; `CwaFixtureBuilder.phaseOne()` now guards the `persistTimestampedFields` call with `isConfigured()`. Non-timestamped components (e.g. `HtmlContent`, `NavigationLink`) are persisted without crashing. Timestamped components (if any) still get timestamps set. Unit test added.

---

### ~~ComponentPosition `sortValue` collision on insert — double-shifting~~ — FIXED

**Fixed (commit `b438d60d`):** `ComponentPositionSortValueHelper.calculateSortValue()` now only shifts existing positions when an actual sortValue collision exists. Previously it always shifted all positions with `sortValue >= newSortValue`, causing double-shifts when the Nuxt module pre-shifted upstream. Behat test added for the no-collision case. The module's pre-shift workaround remains compatible (no collision → no shift).

Related: Nuxt module issue `components-web-app/cwa-nuxt-module#224` Bug 2.

---

### ~~#170 — Component group `allowedComponents` does not validate `pageDataProperty` positions on write~~ — FIXED

**Read side fixed** (commit `2305ad89`): `ComponentPositionNormalizer.normalizeForPageData()` now skips populating the component if the resolved type is not in `componentGroup.allowedComponents`.

**Write side fixed** (pending commit): `ComponentPosition` now stores a `pageDataClass` (FQCN) alongside `pageDataProperty`. `ComponentPositionValidator` validates the pair on every POST/PATCH: (1) `pageDataClass` must be a known API-registered PageData resource; (2) `pageDataProperty` must be a component-typed property on that class; (3) the resolved component type must be in `componentGroup.allowedComponents` if set. Both fields must be set together (entity-level `Assert\Expression` constraint).

**`CwaFixtureBuilder` updated:** `GroupBuilder.pageDataPosition()` now takes `pageDataClass` as its first argument: `->pageDataPosition(string $pageDataClass, string $propertyName, ?int $sort = null)`.

**Nuxt module action required:** The module must now send `pageDataClass` alongside `pageDataProperty` in POST/PATCH requests to `/_/component_positions`. See nuxt module CLAUDE.md for the consumer-side spec.

Related Nuxt module issue: `components-web-app/cwa-nuxt-module#151`.

---

### ~~#98 — Mercure subscriptions not secured~~ — COMPLETE ✓

**Fixed (commit `5ec68934`):** Added `mercure.secure_subscriptions: bool` config option (default: `false`). When `true`, `MercureAuthorization.getSubscribeIrisForResource()` evaluates each resource's AP4 security expression before including it in the subscriber JWT token. Class-level expressions (e.g. `is_granted('ROLE_ADMIN')`) are evaluated against the current user. Expressions referencing `object` (item-level security) are treated as always-accessible because access cannot be determined without a concrete instance. `DummySecuredMercureResource` test entity (ROLE_ADMIN, mercure: true) and three Behat scenarios in `features/user/security.feature` cover: excluded for non-admin, included for admin, excluded for anonymous. Test config sets `secure_subscriptions: true`.

---

---

### ~~#115 — Symfony data collector / profiler integration~~ — COMPLETE ✓

**Implemented (commit `8a7c95cf`):** `CwaCollectorData` (`src/DataCollector/CwaCollectorData.php`) is a shared `ResetInterface` service that bundle listeners push data into. `CwaDataCollector` (`src/DataCollector/CwaDataCollector.php`) reads from it at `collect()` time and renders via `@SilverbackApiComponents/Collector/cwa.html.twig`. The toolbar/panel shows three categories:

- **JWT/authentication**: cookie presence (with name), refresh issued, cookie cleared
- **Route resolution**: resolved path and route IRI from `RouteStateProvider`
- **Mercure publications**: count and topic list from `MercureResourcePublisher`

Services instrumented with optional `?CwaCollectorData` arg: `JWTEventListener`, `JWTClearTokenListener`, `MercureResourcePublisher`, `RouteStateProvider`. Unit tests in `tests/DataCollector/`.

---

### ~~#60 — Uploadable: Private files (S3 pre-signed URLs)~~ — COMPLETE ✓

**Fixed (commit `3146fafc`):** `urlGenerator: 'public'` and `urlGenerator: 'temporary'` paths on `#[UploadableField]` are now tested and documented. When the adapter implements the corresponding Flysystem interface (`PublicUrlGenerator` / `TemporaryUrlGenerator`), the direct URL is used; otherwise the field falls back to the `'api'` generator (the bundle's download endpoint). `MediaObjectFactory` now type-hints `UploadableAttributeReaderInterface` instead of the concrete class. Four unit tests in `MediaObjectFactoryUrlGeneratorTest` and two Behat scenarios in `features/uploads/uploads.feature` cover all four paths.

---

## Known Configuration Quirks

### `repeat_ttl_seconds: 8600` — possible typo for 86400

The default value for `user.password_reset.repeat_ttl_seconds` in the bundle configuration is `8600` seconds (2 hours 23 minutes). This is likely a typo for `86400` (24 hours). Verify against `src/DependencyInjection/Configuration.php` before quoting this value in documentation or changing the default. If intentional, add a comment explaining the reasoning.
