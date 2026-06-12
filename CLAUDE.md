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
bin/phpunit

# Integration tests (Behat)
bin/behat

# Database setup for tests
bin/console -e test doctrine:database:create
bin/console -e test doctrine:migrations:migrate --no-interaction
bin/console -e test doctrine:schema:validate
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

### Serialization groups

The module fetches resources using the `Route:manifest:read` normalization context (endpoint: `GET /routes_manifest/{id}`). This group controls what the Nuxt module sees.

Key current group assignments:
- `Route`: `page`, `pageData` → `Route:manifest:read`
- `AbstractPageData`: `page` (the Page template IRI) → `Route:manifest:read`
- `AbstractPage`: `route`, `parentPage`, `parentPageData` → `Route:manifest:read`
### API endpoints

| Endpoint | Purpose |
|----------|---------|
| `GET /_/routes/{path}` | Resolve a path to a Route resource |
| `GET /routes_manifest/{id}` | Fetch a Route with all nested resources (page, pageData, layout, component groups) — used by the Nuxt module on every navigation |
| `POST /routes/generate` | Auto-generate a Route for a Page/PageData |
| `GET /routes/{id}/redirects` | Follow the redirect chain for a Route |

---

## Feature: Nested Sub-Pages

> **Status: API layer complete and tested. No remaining API work.**
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

`RouteNormalizer` walks the normalised structure and emits `resource_iris` as an **array of arrays grouped by depth**: index 0 = root/shallowest resources, last index = the resources for the requested page. The `parentPage`/`parentPageData` fields are the depth boundaries — everything reachable without crossing those fields belongs to the same depth group. Circular references resolve to IRI strings via AP3's circular-reference handler; the walker only processes arrays, so string IRIs are left as-is.

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
- **Admin/draft**: no manifest (no Route yet); `<CwaPage />` fetches the resource by IRI and walks the `parentPage`/`parentPageData` chain to build the same depth stack

The API serves both contexts correctly — `resource_iris` groups carry the full tree for public routes, and `parentPage`/`parentPageData` are exposed on every individual resource response for the admin walk.

### What is complete ✓

1. **`$parentPage` and `$parentPageData` on `AbstractPage`** — `Assert\Expression` constraint, getters/setters, computed `getParentPageRoute()`, ORM mappings (`Core.Page.orm.xml`, `Core.AbstractPageData.orm.xml`)
2. **`$nested` removed from `AbstractPage`** — property, getter, setter, ORM mapping, and schema entry all removed. Parent = nested, always.
3. **`$route`, `$parentPage`, `$parentPageData` in `Route:manifest:read`** — parent sub-tree IRIs appear in `resource_iris` automatically via the normalizer walk
4. **Behat tests** — `features/main/route.feature`: nested PageData and nested Page manifests both tested; `features/main/page.feature`: create with parentPage (201), create with parentPageData (201), both set (422), PATCH to set parentPage (200)

### What is still missing (API bundle)

Nothing — the API layer for nested sub-pages is complete. The admin parent picker is a module concern; the API already exposes `parentPage`/`parentPageData` on all `AbstractPage`-derived resources.

### Design decisions

- **No `$nested` boolean** — parent = nested, full stop. The presence of `$parentPage`/`$parentPageData` is the complete signal.
- **Two FK properties, not one** — `AbstractPage` is a mapped superclass with no discriminator map; `?AbstractPage` cannot be a Doctrine FK target. `?Page` + `?AbstractPageData` mirrors `Route.$page`/`Route.$pageData`.
- **`getParentPageRoute()` is computed** — no DB column; used by `RouteGenerator` only; returns null safely when the parent has no route yet.
- **Route concatenation is recommended, not required** — `RouteGenerator` prefixes child paths for clean URLs and SEO, but the module's `<CwaPage />` renders depth from manifest data, not URL structure.
- **`resource_iris` is `string[][]`, not `string[]`** — depth-grouped, root first. The module reads the array index as the rendering depth without any client-side traversal.
- **Single rendering mechanism** — `<CwaPage />` handles both public routes (manifest depth groups) and admin/draft access (walk `parentPage`/`parentPageData` chain). No URL-depth dependency.
- **Hierarchy on AbstractPage, not Route** — Routes are the publication mechanism. Hierarchy must be settable before either page has a public URL.