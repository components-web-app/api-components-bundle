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

### API endpoints

| Endpoint | Purpose |
|----------|---------|
| `GET /_/routes/{path}` | Resolve a path to a Route resource |
| `GET /_/resource_manifest/{id}` | Unified manifest endpoint — `{id}` starting with `/` resolves to a Route by path; a UUID resolves to a `Page` or `AbstractPageData` entity. Returns `{ "resource_iris": string[][] }`. |
| `POST /routes/generate` | Auto-generate a Route for a Page/PageData |
| `GET /routes/{id}/redirects` | Follow the redirect chain for a Route |
| `PATCH /_/routes/{id}` | Accepts optional `cascadeChildPaths: true` — when `path` changes, walks direct children and updates their route paths (prefixing with the new parent path), creating redirects from old to new paths. |
| `GET /_/routes/{id}/children` | Returns the recursive child tree for a route (admin-only). Each node: `{ "route": IRI, "path": string, "children": [] }`. |

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
