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
- **For nested pages specifically**: the child's manifest returns `parentRoute` as an IRI only. The Nuxt module makes a separate `GET /routes_manifest/<parent-id>` request. The parent and child manifests are cached and invalidated independently. A change to the parent layout does not invalidate the child's manifest cache.

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

> **Status: API manifest layer complete and tested. Remaining gaps listed below.**
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

`$parentPage`, `$parentPageData`, and `$route` (on `AbstractPage`) all carry `#[Groups(['Route:manifest:read'])]`. When a child Route is normalised for the manifest, the parent entity is embedded inline, and inside it the parent's own route is embedded. `RouteNormalizer::getResourceIrisFromArray()` walks the structure and collects all `@id` values. Circular references resolve to IRI strings via AP3's circular-reference handler — the walker only processes arrays, so string IRIs are ignored.

For one level of nesting, `resource_iris` contains:
1. child route
2. child PageData/Page IRI
3. child Page IRI (from PageData.$page)
4. parent PageData/Page IRI (from `$parentPageData`/`$parentPage`)
5. parent Page IRI (from parent PageData.$page, if applicable)
6. parent route IRI (from parent's `$route`)

All fetched in parallel by the module.

### Route path concatenation — a public-routing necessity

`RouteGenerator` prefixes a child's generated path with the parent's path (e.g. parent `/conference` + `programme` → `/conference/programme`). This is **required for public Nuxt routing to work correctly.**

Nuxt's `<NuxtPage />` is hard-wired to `useRoute().matched` — it renders the next matched route in the tree based on URL segment depth. A page at URL `/programme` is always depth 0; there is no slot for it inside a parent template. A page at `/conference/programme` is depth 1 and can render inside `/conference`'s `<NuxtPage />`. This is a hard Nuxt constraint.

Therefore, route concatenation is not optional for public pages that should render inside a parent. `RouteGenerator` concatenates by default precisely because it is required. The module never parses URL segments to infer hierarchy — hierarchy comes from `parentPage`/`parentPageData` in the data — but URL depth MUST match rendering depth for public routing to function.

### Two routing contexts — critical module concern

See the module's CLAUDE.md for full detail. Summary for the API side:

**Public routing:** URL-depth-driven via `<NuxtPage />`. Route concatenation ensures URL depth matches rendering depth. The API's role is to produce the correctly prefixed URL via `RouteGenerator` and to expose the full parent resource tree in `resource_iris`.

**Admin/draft access:** A nested page in draft has no public Route. The admin accesses it via its entity IRI directly (the module already supports this). The URL is at depth 0 regardless of hierarchy depth. The module must use the `parentPage`/`parentPageData` data to determine rendering depth programmatically — not from the URL. The API's role is to expose the parent chain fields so the module can walk them.

The API serves both contexts correctly already — `resource_iris` contains the parent chain for public routes, and the individual resource response exposes `parentPage`/`parentPageData` for the admin data-driven path.

### What is complete ✓

1. **`$parentPage` and `$parentPageData` on `AbstractPage`** — `Assert\Expression` constraint, getters/setters, computed `getParentPageRoute()`, ORM mappings (`Core.Page.orm.xml`, `Core.AbstractPageData.orm.xml`)
2. **`$nested` removed from `AbstractPage`** — property, getter, setter, ORM mapping, and schema entry all removed. Parent = nested, always.
3. **`$route`, `$parentPage`, `$parentPageData` in `Route:manifest:read`** — parent sub-tree IRIs appear in `resource_iris` automatically via the existing normalizer walk
4. **Behat tests** — `features/main/route.feature`: nested PageData manifest has 6 elements, both child and parent route IRIs present; `features/main/page.feature`: create with parentPage (201), create with parentPageData (201), both set (422)

### What is still missing (API bundle)

1. **Behat test: `Page`-based nesting** — only `PageData`-based nesting is tested. Need a scenario where the child is a `Page` entity (not `PageData`) with a `$parentPage` or `$parentPageData`.

3. **Depth counter in `getResourceIrisFromArray`** — currently unlimited traversal. Add a configurable counter (default 5 levels) passed through the recursion. Beyond the cap the module resolves the remaining chain via its own `resourceTypeToNestedResourceProperties` traversal.

4. **Admin parent picker** — generic `parentPage`/`parentPageData` field exposed in the API schema for any entity extending `AbstractPage`. The admin UI renders a picker for these fields. No per-project code needed.

### Design decisions

- **No `$nested` boolean** — parent = nested, full stop. The presence of `$parentPage`/`$parentPageData` is the complete signal.
- **Two FK properties, not one** — `AbstractPage` is a mapped superclass with no discriminator map; `?AbstractPage` cannot be a Doctrine FK target. `?Page` + `?AbstractPageData` mirrors `Route.$page`/`Route.$pageData`.
- **`getParentPageRoute()` is computed** — no DB column; used by `RouteGenerator` only; returns null safely when the parent has no route yet.
- **Route concatenation is a public-routing necessity, not optional** — Nuxt's URL-depth constraint makes it load-bearing for public pages.
- **Hierarchy on AbstractPage, not Route** — Routes are the publication mechanism. Hierarchy must be settable before either page has a public URL.