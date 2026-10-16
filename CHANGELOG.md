# Changelog

Every change that reaches `main` adds a line under **Unreleased**. When a version is tagged, that section is renamed to the tag and becomes the GitHub release notes.

## Unreleased

### Breaking
- `sortValue` on a component position is read-only on the API: a value in a request body is ignored, and with `after`/`before` it is a 422; the server places every insert, move and reorder by one rule that keeps the most values it can and leaves every value unique ([#PR](https://github.com/components-web-app/api-components-bundle/pull/PR))
- `ComponentPositionSortValueHelper` (`silverback.helper.component_position_sort_value`) and `ComponentPosition::getSortCollection()` are removed; `ComponentPositionOrderer` replaces the helper, and `ComponentPositionNormalizer` no longer takes it. `ComponentPositionValidator` takes the new `ComponentPositionIriResolver` ([#PR](https://github.com/components-web-app/api-components-bundle/pull/PR))

### Added
- `after`/`before` (a position IRI) on a component position created on its own, embedded in a component write, or patched into or within a group: it goes right after or before that position, and an anchor that is not a position of the group is a 422 on that field ([#PR](https://github.com/components-web-app/api-components-bundle/pull/PR))
- `PUT /_/component_groups/{id}/order` with `{"componentPositions": [iri, …]}` reorders a group in one locked request, and `GET` on the same path returns `{componentGroup, sortValues: {iri: int}}` for the positions the group lists ([#PR](https://github.com/components-web-app/api-components-bundle/pull/PR))

### Changed
- Positions sharing a `sortValue` are ordered by id. Purges and Mercure updates from a placement or reorder are sent after its transaction commits ([#PR](https://github.com/components-web-app/api-components-bundle/pull/PR))

## [2.0.0-alpha.23](https://github.com/components-web-app/api-components-bundle/compare/2.0.0-alpha.22...2.0.0-alpha.23) - 2026-10-16

### Breaking
- `UploadedDataUriFile`'s constructor no longer takes `$error` and `$test`: a decoded data URI is always a valid upload ([#459](https://github.com/components-web-app/api-components-bundle/pull/459))
- `OrphanedFileDetector` takes the `PublishableAttributeReader` (`$publishableAttributeReader`) ([#459](https://github.com/components-web-app/api-components-bundle/pull/459))
- `ComponentPositionValidator` and `ComponentGroupNormalizer` take the new `ListedComponentPositions` helper (`silverback.api_components.helper.listed_component_positions`) ([#467](https://github.com/components-web-app/api-components-bundle/pull/467))
- `GenerateFixturesCommand` takes the `FixtureAssetStoreFactory` (`silverback.api_components.fixture.asset_store_factory`) as its second argument ([#466](https://github.com/components-web-app/api-components-bundle/pull/466))

### Added
- `maxComponents` on a component group (`null`, the default, is unlimited; otherwise a positive integer), read and written with `allowedComponents`: a position created in, or moved into, a group that already lists that many positions is refused with a 422 on `componentGroup` naming the limit, also when the position is created inside a component write. A dynamic page data position counts as one, a published component with its draft as one, and a position hidden by `allowedComponents` not at all. Setting a limit below what a group holds removes nothing; the group takes no more until it is under the limit, and its positions can still be moved within it. `group(..., max: n)` in `CwaFixtureBuilder`, and `generate-fixtures` writes it. Upgrade step: new `max_components` column on `component_group`; generate and run a migration ([#467](https://github.com/components-web-app/api-components-bundle/pull/467))
- `#[UploadedImage]`, an `Assert\Image` for upload fields that skips the image checks for an SVG (`allowSvg: false` refuses one), takes its `maxPixels` from the new `uploadable.image_max_pixels` (default 30,000,000) when it sets none, and says the limit with an approximate width x height; the orphaned files report checks it the same way ([#456](https://github.com/components-web-app/api-components-bundle/pull/456))
- A fixture asset store for `generate-fixtures` (`fixtures.assets`: a `local` folder or a `flysystem` adapter and prefix, or `--assets-store`/`--assets-dir` for one run): each uploaded file is stored once under its SHA-256, listed in a `<Scaffold>.assets.json` manifest beside the class, and loaded with `$cwa->asset('<hash>')` through the same store, so a large site's files can stay out of the repository; with nothing configured the scaffold keeps readable copies beside it as before ([#466](https://github.com/components-web-app/api-components-bundle/pull/466))
- Generating into a local asset store deletes the assets its manifest no longer lists; `silverback:api-components:prune-fixture-assets <manifest>...` (`--dry-run`) deletes from any store the assets that none of the given manifests lists ([#466](https://github.com/components-web-app/api-components-bundle/pull/466))
- `redirectTo(routeName)` on a scaffold's page or page data makes its own route redirect to a named route, resolved after route generation and before the callbacks; an `--append` load never changes the redirect of a route that already exists ([#468](https://github.com/components-web-app/api-components-bundle/pull/468))
- `CwaFixtureBuilder` takes a `PageBuilder` or `PageDataBuilder` as a route target: `linkRoute(property, page:)`, `redirect(path, to:)` and `linkIri()`'s `route:`, `page:` and `pageData:` ([#474](https://github.com/components-web-app/api-components-bundle/pull/474))

### Changed
- `generate-fixtures` writes byte-identical stored files once in its default `assets/` output, under the name of the first, and every `new File(...)` for them names that asset ([#470](https://github.com/components-web-app/api-components-bundle/pull/470))

### Fixed
- A component written with a new position inside it (`"componentPositions": [{"componentGroup": …}]`, as the module adds a component) is checked against the group's `allowedComponents` and `#[ExplicitAllowOnly]`, as a position created on its own is ([#467](https://github.com/components-web-app/api-components-bundle/pull/467))
- A base64 or data-URI upload to a field with a `File` or `Image` constraint was refused with "The file could not be uploaded." instead of being validated ([#459](https://github.com/components-web-app/api-components-bundle/pull/459))
- The orphaned files scan reads an SVG's size as Symfony's `Image` constraint does (`viewBox` fallback, length units), also when file info is cached ([#459](https://github.com/components-web-app/api-components-bundle/pull/459))
- The orphaned files scan checks a row with a publication date against its field's publish-group constraints too (`{ShortName}:published` or the class's `validationGroups`), as publishing does ([#459](https://github.com/components-web-app/api-components-bundle/pull/459))
- A `#[Timestamped]` entity a scaffold saved only through `$cwa->persist()`, or that a page data or component field held, was inserted with no `createdAt`/`modifiedAt`, failing the load on a NOT NULL column ([#463](https://github.com/components-web-app/api-components-bundle/pull/463))
- `doctrine:fixtures:load` generated every imagine variant of every loaded image in one process (5.5 GB for 710 images), because each flush serialized the new resources for Mercure; a fixture load now propagates no update, flushes the HTTP cache once at the end, and says to run `silverback:api-components:imagine:generate` ([#465](https://github.com/components-web-app/api-components-bundle/pull/465))
- `generate-fixtures` left out, without a report, the redirect of a route that belongs to a page or page data; it is now written as `->redirectTo()`, or reported when its target is not part of the site ([#468](https://github.com/components-web-app/api-components-bundle/pull/468))

### Tooling
- Codecov waits for both coverage uploads (`phpunit` and `behat`) before posting its statuses and comment, so `codecov/project` no longer fails on partial coverage mid-run ([#473](https://github.com/components-web-app/api-components-bundle/pull/473))

## [2.0.0-alpha.22](https://github.com/components-web-app/api-components-bundle/compare/2.0.0-alpha.21...2.0.0-alpha.22) - 2026-10-16

### Breaking
- `GenerateFixturesCommand` takes the new `ScaffoldGenerator` service (`silverback.api_components.fixture.scaffold_generator`) instead of the six collaborators it used to build the scaffold itself ([#453](https://github.com/components-web-app/api-components-bundle/pull/453))
- `nested()` on a page or page data adds a closure instead of replacing the previous one, and `PageBuilder::nested()` returns the builder ([#453](https://github.com/components-web-app/api-components-bundle/pull/453))
- A component builder with no groups, and a draft, is persisted with the positions instead of in the first phase; a component builder with groups is persisted after the `nested()` closures whenever the scaffold has one ([#453](https://github.com/components-web-app/api-components-bundle/pull/453))

### Added
- `ComponentBuilder`/`PageDataBuilder::linkIri()` writes the IRI of a page, layout, page data or route of the scaffold into a field, or a key of an array field, at load time; `generate-fixtures` emits it for such IRIs instead of copying an id that will not exist after the reload ([#453](https://github.com/components-web-app/api-components-bundle/pull/453))
- `CwaFixtureBuilder::publish()`, `ComponentBuilder::draft()`, `ComponentBuilder`/`PageDataBuilder::linkRoute()` and `PageBuilder::reachableWithoutRoute()` for hand-written and generated scaffolds (`linkRoute()` also inside `afterRoutes()` and `onRoutesCreated()`); every route the builder creates or keeps can be named in `getRoute()` and `redirect(to:)`; `generate-fixtures --keep-timestamps` writes every publish and go-live date exactly ([#453](https://github.com/components-web-app/api-components-bundle/pull/453))

### Changed
- `generate-fixtures` writes the scaffold a developer would write by hand: a method per layout, page and page data (children called from their parent's `nested()`), named variables and properties instead of `$c[]`/`$g[]`, `linkRoute()` next to each component, a named `uiComponent:`, variables named from the first heading of a component with no label, `publish()` instead of a past date, `draft()` instead of `afterRoutes()`, long HTML split at block tags or written as a nowdoc without changing a byte, and plain lists ([#453](https://github.com/components-web-app/api-components-bundle/pull/453))
- The `make:cwa-scaffold` skeleton is written in the same style, with a navigation link (`--link-class`, default `App\Entity\NavigationLink`) linked to the home route, left out when the class is not a component with a writable `label` (a public property or `setLabel()`) and `route`, and published only when it is publishable ([#453](https://github.com/components-web-app/api-components-bundle/pull/453))
- An `--append` load summary counts the scaffold positions of kept groups, and a route name the builder cannot resolve names the `linkRoute()` owner or redirect and the routes of the load ([#453](https://github.com/components-web-app/api-components-bundle/pull/453))

### Fixed
- A kept route with no `routeName:` is also registered under the name derived from its path, so a generated scaffold's `linkRoute()` resolves in an `--append` load ([#453](https://github.com/components-web-app/api-components-bundle/pull/453))
- A `nested()` declared inside a nested closure was never run ([#453](https://github.com/components-web-app/api-components-bundle/pull/453))
- An `--append` load created a duplicate of a component with its own group declared outside a `nested()` closure and placed only in a kept group of a nested page ([#453](https://github.com/components-web-app/api-components-bundle/pull/453))
- `generate-fixtures` exports a stored file under the name its reload is stored under (lowercase, extension from the content type), so a file uploaded under a PHP temp name such as `phpGPCPOl` no longer changes name between generations ([#453](https://github.com/components-web-app/api-components-bundle/pull/453))
- `generate-fixtures` wrote two `use` lines with the same short name (a fatal error) for two classes sharing one, and dropped a group owned by a draft without reporting it ([#453](https://github.com/components-web-app/api-components-bundle/pull/453))

## [2.0.0-alpha.21](https://github.com/components-web-app/api-components-bundle/compare/2.0.0-alpha.20...2.0.0-alpha.21) - 2026-10-13

### Breaking
- `ResourceMetadataProvider` no longer has the public `$metadatas` property or `getMetadatas()`: it keeps each resource's metadata in a `WeakMap` and is reset between requests (see Fixed) ([#449](https://github.com/components-web-app/api-components-bundle/pull/449))

### Fixed
- Memory no longer grows request after request in a worker (FrankenPHP worker mode). `ResourceMetadataProvider` kept every serialised resource and its `_metadata`, and API Platform's skolem IRI converter, which is not tagged `kernel.reset` (api-platform/core#8681), kept every `_metadata` object it gave a `/.well-known/genid/` IRI, each keeping the other's objects alive. Both are now reset between requests. `PropagateUpdatesListener` is reset too, so a flush that fails no longer leaves its entities to be purged and published by the next request's flush, and the user email factories no longer keep the last user they emailed ([#449](https://github.com/components-web-app/api-components-bundle/pull/449))
- A component on a live routed page is readable anonymously when it is also placed on an unrouted page. `ComponentVoter` collected the component's pages with `iterator_to_array()`, which kept the repeated keys of the collections it `yield from`s, so a later page replaced the routed one and the visitor got a 401 ([#450](https://github.com/components-web-app/api-components-bundle/pull/450))

## [2.0.0-alpha.20](https://github.com/components-web-app/api-components-bundle/compare/2.0.0-alpha.19...2.0.0-alpha.20) - 2026-10-13

### Added
- `silverback:api-components:warm-http-cache` requests, through the HTTP cache, the API responses server-side rendering fetches for each public route (the route, its `resource_manifest` and every resource the manifest lists, or only the route lookup for a redirect), anonymously and with the module's `Accept`, `preload` and `path` headers and an `Accept-Encoding` of `gzip`, so the cache stores them under the keys SSR asks for. Run it before a new pod takes traffic. Options: `--base-url` (default `http://localhost`), `--path`, `--limit` (shortest paths first), `--concurrency` (default 2), `--max-time`, `--timeout` and `--accept-encoding`. It always exits 0 once it has started, and prints requests, hits, misses, failures and the slowest requests ([#443](https://github.com/components-web-app/api-components-bundle/pull/443))

### Fixed
- The `resource_manifest` of a redirecting route no longer lists its `redirectPath` (a front-end path) among its IRIs, so the module no longer requests it from the API and gets a 404 each time the redirect is rendered. `GET /_/routes/{path}` still returns `redirectPath` ([#445](https://github.com/components-web-app/api-components-bundle/pull/445))

## [2.0.0-alpha.19](https://github.com/components-web-app/api-components-bundle/compare/2.0.0-alpha.18...2.0.0-alpha.19) - 2026-10-13

### Breaking
- A page's and a page data's `title` defaults to `null` instead of `'Unnamed Page'`, and page data's `title` column is nullable like the page's. `POST /_/routes/generate` (and `RouteGenerator::create()`) refuses a page or page data with no title, or one that gives no slug, with a 422 and a `title` violation, creating nothing; `RouteGeneratorInterface::generatePath()` returns `null` for it. `CwaFixtureBuilder` leaves an untitled page or page data with no `route:` without a route, and treats untitled page data like `withoutRoute()` page data in an `--append` load. Upgrade step: generate and run a migration (it makes `_acb_abstract_page_data.title` nullable), then clear the old default with `UPDATE _acb_page SET title = NULL WHERE title = 'Unnamed Page';` and `UPDATE _acb_abstract_page_data SET title = NULL WHERE title = 'Unnamed Page';` (with your `table_prefix` if it is not `_acb_`) ([#440](https://github.com/components-web-app/api-components-bundle/pull/440))

### Fixed
- Renaming a route whose path is 254 or 255 characters and whose name is taken (it is still named after its old path, as routes created with `name` = `path` are) no longer fails with a 500 from MySQL or PostgreSQL after the rename was written: the redirect's name is shortened to fit its `-N` suffix in 255 characters, and its path stays the old path. `CwaFixtureBuilder::redirect()` shortens a suffixed derived name the same way ([#438](https://github.com/components-web-app/api-components-bundle/pull/438))
- `CwaFixtureBuilder` saves what a `nested()` closure declares when nothing else in the load is new: a component, or an existing group linked to a component, was persisted but never flushed, so an `--append` load could drop it while its summary reported it created ([#437](https://github.com/components-web-app/api-components-bundle/pull/437))

## [2.0.0-alpha.18](https://github.com/components-web-app/api-components-bundle/compare/2.0.0-alpha.17...2.0.0-alpha.18) - 2026-10-13

### Breaking
- `RouteGenerator` takes a `ValidatorInterface` ([#433](https://github.com/components-web-app/api-components-bundle/pull/433))
- A page's `uiComponent` is stored as the full component name, `CwaPage<Name>`: a POST or PATCH sending the short name (`PrimaryPageTemplate`, as the admin's page modals do) stores and returns `CwaPagePrimaryPageTemplate`, so pages written through the API and through `CwaFixtureBuilder` agree and `generate-fixtures` reloads them unchanged. Layouts are unchanged. Upgrade step: run `silverback:api-components:prefix-page-ui-components` once to prefix pages already stored with the short name; it is safe to run again ([#431](https://github.com/components-web-app/api-components-bundle/pull/431))

### Fixed
- `POST /_/routes/generate` is a 422 with a `path` violation, and creates nothing, when the parent's path and the title slug together are longer than 255 characters; `RouteGenerator::create()` throws the same `ValidationException` for fixtures. `PATCH /_/routes/{id}` with `cascadeChildPaths` checks every descendant's new path before writing anything, so a rename that would push one over 255 characters is a 422 naming that descendant instead of a 500 after the route and part of the tree were already moved. A cascade to a path under the route's own path no longer recurses without end when an unrouted child page loops back to it ([#433](https://github.com/components-web-app/api-components-bundle/pull/433))
- A value longer than its `VARCHAR(255)` column (a page's `title` or `metaDescription`, any `reference`, `uiComponent`, route `path`/`name`, component position `pageDataProperty`/`pageDataClass`, site config `key`, form `formType`, collection `resourceIri`, user `username`/`emailAddress`/`newEmailAddress`) is a 422 with a field violation instead of a 500 from MySQL or PostgreSQL ([#430](https://github.com/components-web-app/api-components-bundle/pull/430))
- A `PUT` to a user resource updates only the fields it sends. API Platform's default `standard_put` (on 4.x and 5.x) built a new user from the body, so a `PUT` returned 200 after blanking the password hash and resetting roles, `enabled` and email verification, or failed with 422/500 when it sent no email address. The bundle sets `standard_put: false` on every `Put` of an `AbstractUser` resource ([#429](https://github.com/components-web-app/api-components-bundle/pull/429))
- `CwaFixtureBuilder` creates the groups of components declared inside a `nested()` closure, with their positions, so a scaffold generated from a site with nested pages no longer reloads those pages emptied below their first component ([#428](https://github.com/components-web-app/api-components-bundle/pull/428))

## [2.0.0-alpha.17](https://github.com/components-web-app/api-components-bundle/compare/2.0.0-alpha.16...2.0.0-alpha.17) - 2026-10-12

### Fixed
- Storing an imagine variant again replaces its file info, so `_metadata.mediaObjects` reports the variant actually stored after a filter's format, quality or size changes. Variant rows written before this fix stay stale: delete them with `DELETE FROM _acb_imagine_cached_file_metadata WHERE filter <> '';`, then purge the HTTP cache, and each row is read again from its stored variant on the next render ([#421](https://github.com/components-web-app/api-components-bundle/issues/421), [#422](https://github.com/components-web-app/api-components-bundle/pull/422))

## [2.0.0-alpha.16](https://github.com/components-web-app/api-components-bundle/compare/2.0.0-alpha.15...2.0.0-alpha.16) - 2026-10-12

### Added
- `imagine:generate --limit=N` and `--max-memory=768M` stop a run cleanly with exit 3, meaning variants are still missing: run it again in a fresh process until it exits 0. `--max-memory` checks the process's resident memory (`VmRSS`), which counts libvips's native allocations that `memory_limit` cannot see, and every run generates at least one variant. The libvips operation cache is switched off for the run ([#418](https://github.com/components-web-app/api-components-bundle/issues/418), [#419](https://github.com/components-web-app/api-components-bundle/pull/419))

## [2.0.0-alpha.15](https://github.com/components-web-app/api-components-bundle/compare/2.0.0-alpha.14...2.0.0-alpha.15) - 2026-10-11

### Breaking
- Uploads and imagine variants on Google Cloud Storage and S3 are written with a `Cache-Control`: `public, max-age=31536000, immutable` for originals and `public, max-age=2592000` for variants, configured by `silverback_api_components.uploadable.cache_control.original`/`.imagine` (`null` for none) and per field by `#[UploadableField(cacheControl:, imagineCacheControl:)]` (`false` for none). Files stored earlier keep theirs until updated in the bucket. `UploadableFileManager` takes an `UploadableCacheControl` ([#414](https://github.com/components-web-app/api-components-bundle/issues/414), [#415](https://github.com/components-web-app/api-components-bundle/pull/415))

### Added
- `silverback:api-components:imagine:generate` generates every imagine variant not stored yet, one image at a time, so adding a filter no longer makes visitors' requests generate them, with the field's `imagineCacheControl`: run it as a one-off Job after the deploy, then purge the HTTP cache. `--filter` and `--class` narrow it (an unknown one exits 2), `--dry-run` counts what is missing, `-v` lists each variant; it exits 1 if a variant failed. `ImagineFilterGenerator::filter()` reports whether a filter was resolved, skipped over the memory budget or failed ([#413](https://github.com/components-web-app/api-components-bundle/issues/413), [#416](https://github.com/components-web-app/api-components-bundle/pull/416))

## [2.0.0-alpha.14](https://github.com/components-web-app/api-components-bundle/compare/2.0.0-alpha.13...2.0.0-alpha.14) - 2026-10-11

### Breaking
- Imagine variants of an AVIF, HEIC or HEIF original are saved as WebP (`cache/<filter>/photo-1a2b3c4d.webp`) unless the filter set has a `format`, on every driver: vips cannot encode them and other drivers often cannot. Variants an earlier version stored for such originals under `.avif`/`.heic`/`.heif` are no longer used and are generated again as WebP; remove the old files with `bin/console liip:imagine:cache:remove` (or `--filter=<filter>` for each filter used on such images). The bundle replaces `liip_imagine.filter.manager`'s class with `Silverback\ApiComponentsBundle\Imagine\FilterManager` unless the application set its own. `MediaObjectFactory` no longer takes a logger ([#410](https://github.com/components-web-app/api-components-bundle/issues/410), [#411](https://github.com/components-web-app/api-components-bundle/pull/411))

### Fixed
- An imagine filter that fails to generate (a codec the driver lacks, a corrupt image) is skipped and logged at error, and the original is served, instead of a 500 for the resource holding the image and every page manifest containing it. Uploads of such images no longer fail either ([#410](https://github.com/components-web-app/api-components-bundle/issues/410), [#411](https://github.com/components-web-app/api-components-bundle/pull/411))

## [2.0.0-alpha.13](https://github.com/components-web-app/api-components-bundle/compare/2.0.0-alpha.12...2.0.0-alpha.13) - 2026-10-10

### Breaking
- Imagine variants of a filter set with a `format` are stored with that format's extension (`cache/<filter>/photo-1a2b3c4d.webp`, not `.png`), so they are served with the right `Content-Type`. Existing variants for those filters are generated again under the new name; clear them with `bin/console liip:imagine:cache:remove --filter=<filter>` for each such filter, as nothing else removes the old files ([#404](https://github.com/components-web-app/api-components-bundle/pull/404))
- `ImagineRemoveEvent::$paths` is always an array (empty for every path), and `FileInfoCacheManager::deleteCaches()` with `null` filters deletes only variant rows, keeping the original's row, as the cache resolver only removes variants ([#406](https://github.com/components-web-app/api-components-bundle/pull/406))

### Added
- `"publishedAt": "now"` on a publishable POST, PUT or PATCH publishes by the server's clock, so a browser whose clock is ahead of the server no longer leaves "Publish now" as a draft scheduled a moment ahead. Only the exact string `now` is resolved; a caller who may not publish still cannot set it, and it is a 422 on `?published=true` like any publication date change ([#408](https://github.com/components-web-app/api-components-bundle/pull/408))

### Fixed
- `liip:imagine:cache:remove --filter=<filter>` and `liip:imagine:cache:remove` no longer fail with a `TypeError` after deleting the files: the file info rows of those filters are deleted for every path, and the originals' rows kept ([#405](https://github.com/components-web-app/api-components-bundle/issues/405), [#406](https://github.com/components-web-app/api-components-bundle/pull/406))
- `CacheManager::remove()` accepts a single path or filter as a string, as liip's signature allows, instead of throwing a `TypeError` ([#403](https://github.com/components-web-app/api-components-bundle/issues/403), [#406](https://github.com/components-web-app/api-components-bundle/pull/406))
- An upload is stored with the extension of its content: a PNG uploaded as `holiday.jpg` is stored as `.png`, and one with no extension gets `.png`. A valid extension (`.jpeg`) is kept, and plain text or an unrecognised type keeps the client's extension ([#404](https://github.com/components-web-app/api-components-bundle/pull/404))
- An imagine variant with no file info row (rows lost, or a bucket shared with another database) reports its real type, size and dimensions instead of `-1` and an empty type: the stored variant is read through `FlysystemCacheResolver` and its row saved ([#404](https://github.com/components-web-app/api-components-bundle/pull/404))

## [2.0.0-alpha.12](https://github.com/components-web-app/api-components-bundle/compare/2.0.0-alpha.11...2.0.0-alpha.12) - 2026-10-10

### Breaking
- Upgrade step: the imagine file info table (`_acb_imagine_cached_file_metadata`) gets its `unique_cache_item` index on `(path, filter)` and a `NOT NULL DEFAULT ''` `filter` column. Run `silverback:api-components:deduplicate-file-info` first (gives originals an empty filter and removes duplicate rows; safe to run again), then generate and run a migration ([#399](https://github.com/components-web-app/api-components-bundle/pull/399))

### Changed
- `silverback_api_components.imagine.memory_limit` defaults to `320M` instead of `512M`. With GD, sources over about 23 MP now get no imagine filters (the original is served and a warning logged); set it higher, or use the vips driver, to filter larger images ([#400](https://github.com/components-web-app/api-components-bundle/pull/400))

### Fixed
- The file info table's unique index on path and filter now exists: it was declared where Doctrine ignores it, and originals are stored with an empty filter so the index covers them on every database. Caching file info a concurrent request already saved keeps that row ([#399](https://github.com/components-web-app/api-components-bundle/pull/399))

## [2.0.0-alpha.11](https://github.com/components-web-app/api-components-bundle/compare/2.0.0-alpha.10...2.0.0-alpha.11) - 2026-10-10

### Breaking
- `MediaObjectFactory` and `UploadableFileManager` take an `ImagineFilterGenerator` (`$imagineFilterGenerator`) instead of liip's `FilterService`; an application that constructs or decorates them must pass the generator ([#397](https://github.com/components-web-app/api-components-bundle/pull/397))

### Added
- `silverback_api_components.imagine.memory_limit` (default `512M`, `null` to disable): imagine filters are generated with `memory_limit` raised to it and restored afterwards, so an application can run its workers with a lower global `memory_limit` ([#397](https://github.com/components-web-app/api-components-bundle/pull/397))
- `silverback:api-components:purge-http-cache --tag <tag> --path </path>` (both repeatable) purges only those tags through the configured purger, never a full flush: `--path` purges the tag a write to the route at that path purges (`<route import prefix>/_/routes/<path>`), whether or not a route exists there. With Souin that is a surrogate-key purge, which also reaches a CDN that purges by tag. Exits 1 when the purge fails, 2 for a tag containing a space or comma, and 0 with nothing purged when no purger is configured ([#396](https://github.com/components-web-app/api-components-bundle/pull/396))
- Invalid files in the orphaned file report: `invalidFiles` lists each referenced stored file that breaks its uploadable field's current `File`/`Image` constraints (`Default` group, composites such as `When` honoured) with its resource, field, path and violation messages, checked from cached file info or, without a row, from the object without decoding it; report only, never modified or deleted. Upgrade step: new `invalid_files` column on `_acb_orphaned_file_report`; generate and run a migration ([#391](https://github.com/components-web-app/api-components-bundle/pull/391))

### Fixed
- An imagine filter whose source is too large to generate within the memory budget is skipped and logged instead of exhausting PHP's memory, on read and on upload; the response still carries the original. The estimate uses the measured cost of the configured driver (GD, Imagick, vips) ([#397](https://github.com/components-web-app/api-components-bundle/pull/397))
- Reading uploadables no longer saves `NULL` filenames: the uploadable normalizer stopped nulling the file fields on the entity, and caching file info (for a file seen for the first time, or an imagine filter generated on read) inserts only its own row instead of flushing every pending change. A page whose request generated a filter or cached file info lost the images of the components serialised before it ([#393](https://github.com/components-web-app/api-components-bundle/pull/393))

## [2.0.0-alpha.10](https://github.com/components-web-app/api-components-bundle/compare/2.0.0-alpha.9...2.0.0-alpha.10) - 2026-10-10

### Changed
- `symfony/mercure` ^0.9 and `symfony/mercure-bundle` ^0.6 are allowed alongside ^0.8 and ^0.5 ([#385](https://github.com/components-web-app/api-components-bundle/pull/385))

## [2.0.0-alpha.9](https://github.com/components-web-app/api-components-bundle/compare/2.0.0-alpha.8...2.0.0-alpha.9) - 2026-10-08

### Added
- `isReachableWithoutRoute` on pages and page data (admin-only, default `false`): a page with no route that is flagged is readable as though a live route reached it, so an application can serve it from its own routes (e.g. the module's fetch by IRI) with `routable_security` set. Its components, manifest and ancestors follow, as for a routed page, and its nearest routed ancestor must still be readable. A page with a route ignores the flag. Upgrade step: new `is_reachable_without_route` column on the page and page data tables; generate and run a migration ([#382](https://github.com/components-web-app/api-components-bundle/pull/382))

## [2.0.0-alpha.8](https://github.com/components-web-app/api-components-bundle/compare/2.0.0-alpha.7...2.0.0-alpha.8) - 2026-10-04

### Breaking
- `api_platform.use_symfony_listeners` is no longer forced on: the bundle follows API Platform's default (off). An application that needs API Platform's listeners must enable it itself; the bundle works either way ([#369](https://github.com/components-web-app/api-components-bundle/pull/369))
- The bundle's API behaviour runs in state provider and processor decorators instead of `EventPriorities` listeners. Removed: `DenyAccessListener`, `ComponentUsageEventListener`, `RouteEventListener`, `DeletedResourceEventListener`, `UploadableEventListener` and the listeners' request/view methods ([#369](https://github.com/components-web-app/api-components-bundle/pull/369))
- Renamed: `EventListener\Api\UserEventListener` → `Helper\User\UserWriteNotifier` (`postWrite()` → `notify()`, id `silverback.api_components.helper.user.write_notifier`); `EventListener\Api\CollectionApiEventListener` → `Helper\Collection\CollectionPopulator` (`transform()` → `populate()`, id `silverback.api_components.helper.collection.populator`); the draft merge moved from `PublishableEventListener` to `Helper\Publishable\PublishableDraftMerger` (id `silverback.api_components.helper.publishable.draft_merger`) ([#369](https://github.com/components-web-app/api-components-bundle/pull/369))
- Upload and download operations no longer use custom controllers; `UploadStateProvider`/`DownloadStateProvider` run them from the read chain ([#369](https://github.com/components-web-app/api-components-bundle/pull/369))
- Mercure 1.0 protocol only: subscribe grants are URL Patterns in `authorization_details` (`…/:id\?`, and `…/:id\?draft=1` for draft access) and the cookie is `__Secure-mercure_access_token`; `symfony/mercure` ^0.8 and `symfony/mercure-bundle` ^0.5 are required. Upgrade steps: set `protocol_version: '1.0'` and `jwt.claims` `iss`/`sub`/`client_id` on the hub in `mercure.yaml`; in the Caddyfile, move the keys into an `issuer <iss> { publisher { jwt … } subscriber { jwt … } }` block matching `iss`, pin `resource_identifier` to the public hub URL (the tokens' `aud`; publishing through the internal URL is 401 otherwise) and drop `protocol_version_compatibility`; on a plain-HTTP hub URL set a prefix-less `cookie_name` on both sides ([#379](https://github.com/components-web-app/api-components-bundle/pull/379))

### Added
- `GET /_/health/live` (`api_components_health_live`): an anonymous, `private, no-store` liveness check that touches no database, cache or remote service, so it stays 200 through a database outage; use it for the liveness probe and `/_/health` for readiness ([#375](https://github.com/components-web-app/api-components-bundle/pull/375))
- Orphaned file report (admin): `POST /_/orphaned_files/scan`, `GET /_/orphaned_files` and `silverback:api-components:scan-orphaned-files` report stored files under the uploadable fields' filesystems and prefixes that no row references (older than `orphaned_files.minimum_age`, default one hour; imagine cache and `orphaned_files.excluded_paths` excluded) as orphaned when the bundle named them or has file info for them and as unknown otherwise, and rows whose file is missing; `POST /_/orphaned_files/delete` deletes selected orphaned paths or all of them, re-checked against a fresh scan, and never an unknown file. Upgrade step: the report is stored in a new table, `_acb_orphaned_file_report`; generate and run a migration ([#373](https://github.com/components-web-app/api-components-bundle/pull/373))

### Fixed
- Stored files, their imagine variants and `_acb_file_info` rows are deleted whenever Doctrine removes an uploadable entity (group, page and orphan cascades, `POST /_/orphaned_resources/delete`, direct removal), only after the flush succeeds, and never while another row still references the path; a failed file delete is logged, not a 500 ([#372](https://github.com/components-web-app/api-components-bundle/pull/372))
- `GET /me` and `GET /…/{id}/usage` work with `use_symfony_listeners: false`: `/me` keeps the controller of the `Get` it is built from and `UserStateProvider` resolves the token user itself, and the usage operation is named after its key ([#369](https://github.com/components-web-app/api-components-bundle/pull/369))

### Tooling
- `API_PLATFORM_USE_SYMFONY_LISTENERS=0|1` sets the flag in the Behat test app, with a cache directory per value, and CI runs one Behat leg with it on ([#369](https://github.com/components-web-app/api-components-bundle/pull/369))
- `simple-phpunit` runs PHPUnit 13.4 ([#378](https://github.com/components-web-app/api-components-bundle/pull/378))

## [2.0.0-alpha.7](https://github.com/components-web-app/api-components-bundle/compare/2.0.0-alpha.6...2.0.0-alpha.7) - 2026-09-26

### Fixed
- `scan-orphaned` (and `clean-orphaned`) no longer fails with a `TypeError` when the notification recipients resolve to null, e.g. from an empty `%env(default::MAILER_EMAIL)%`: null or empty recipients mean no email ([#366](https://github.com/components-web-app/api-components-bundle/pull/366))
- `orphaned_resources.notify.recipients` accepts `Name <address>` as well as a bare address, in a list or a comma-separated string (quote a name containing a comma), so the template's default `MAILER_EMAIL` no longer silently stops the orphan alert ([#363](https://github.com/components-web-app/api-components-bundle/pull/363))

### Tooling
- The Behat suite runs as concurrent shards (`bin/behat-parallel`) with coverage merged into one report, and the test app hashes passwords at bcrypt cost 4: about 9x faster locally ([#360](https://github.com/components-web-app/api-components-bundle/pull/360))
- Infection on a pull request mutates only the lines it changes in `src/`, gated at 80; `main` still runs the full set and is the only run that sends the Stryker badge score ([#362](https://github.com/components-web-app/api-components-bundle/pull/362))

## [2.0.0-alpha.6](https://github.com/components-web-app/api-components-bundle/compare/2.0.0-alpha.5...2.0.0-alpha.6) - 2026-09-26

### Breaking
- Upgrade step: the orphaned resource report is now stored in a new table, `_acb_orphaned_resource_report`. Generate and run a migration (`doctrine:migrations:diff`). Reports previously held in `cache.app` are not migrated; `GET /_/orphaned_resources` is 404 until the next scan ([#354](https://github.com/components-web-app/api-components-bundle/pull/354))
- `silverback:api-components:clean-orphaned` no longer deletes anything: it is now an alias of `scan-orphaned`, which only scans. Delete orphans through the API (`DELETE` per IRI, or `POST /_/orphaned_resources/delete`) ([#353](https://github.com/components-web-app/api-components-bundle/pull/353))

### Added
- `silverback:api-components:scan-orphaned` emails `silverback_api_components.orphaned_resources.notify.recipients` when the orphaned resources differ from the last alert, with the counts, what is new since then and a link to the admin page (`notify.admin_page_path`, default `/_cwa/orphaned`, on `user.email_links.default_origin`). `--no-notify` skips it; the HTTP scan never emails ([#354](https://github.com/components-web-app/api-components-bundle/pull/354))
- `POST /_/orphaned_resources/delete` (admin) deletes selected orphans (`iris`) or all of them (`all: true`), re-checked against a fresh scan, and returns what was deleted and what was rejected ([#353](https://github.com/components-web-app/api-components-bundle/pull/353))
- Fixture builder: `PageBuilder::withoutRoute()` keeps a non-template page without a route; `generate-fixtures --namespace` sets the generated class's namespace ([#347](https://github.com/components-web-app/api-components-bundle/pull/347))

### Changed
- The orphaned resource report is stored in the database instead of `cache.app`, so it is shared between pods and survives deploys, and its `generatedAt` has microsecond precision ([#354](https://github.com/components-web-app/api-components-bundle/pull/354))
- `silverback:api-components:scan-orphaned` (alias `clean-orphaned`) runs the same scan as `POST /_/orphaned_resources/scan` synchronously, stores the report `GET /_/orphaned_resources` returns, and prints the counts per kind (IRIs with `-v`) ([#353](https://github.com/components-web-app/api-components-bundle/pull/353))

### Fixed
- The orphan scan reports what is orphaned only through an orphaned parent: the positions in an orphaned group, the components only those positions hold, the groups those components own, to any depth, and cycles nothing live reaches. A resource still reached from a live page, layout, component or page data is not listed, and `POST /_/orphaned_resources/delete` accepts the newly listed IRIs ([#358](https://github.com/components-web-app/api-components-bundle/pull/358))
- An admin's `POST /_/orphaned_resources/scan`, or the refresh after `POST /_/orphaned_resources/delete`, no longer absorbs an orphan change: `scan-orphaned` compares with the orphans at the last alert, kept in the same table, instead of with the last stored report. A failed send is retried on the next run ([#356](https://github.com/components-web-app/api-components-bundle/pull/356))
- A delete leaves nothing for the next orphan scan: a component removed by a cascade takes the groups it owns and its unused draft with it. An explicit `DELETE` of a published component still keeps its draft ([#353](https://github.com/components-web-app/api-components-bundle/pull/353))
- A fixture `redirect()` whose `name:` belongs to a route created in the same load throws even when its path already exists, instead of re-pointing that name at the existing route ([#352](https://github.com/components-web-app/api-components-bundle/pull/352))
- A component held by a page data property typed as a parent class (such as `AbstractComponent`) counts as used, so `clean-orphaned` no longer deletes it; it is public only when the page data is on a live route, and editing it purges and publishes the page data ([#349](https://github.com/components-web-app/api-components-bundle/pull/349))
- The new email address and change password forms and `/verify-email` look the user up through `UserRepositoryInterface::loadUserByIdentifier()` instead of the undeclared `find()`/`findOneBy()` ([#346](https://github.com/components-web-app/api-components-bundle/pull/346))
- A failed user email send returns 503 (or is logged after a write) whether or not a logger is configured, instead of a 500 ([#346](https://github.com/components-web-app/api-components-bundle/pull/346))
- `generate-fixtures` names the class after the `--output` file instead of always `GeneratedScaffold` ([#347](https://github.com/components-web-app/api-components-bundle/pull/347))
- `generate-fixtures` emits `withoutRoute()` for a non-template page with no route, so it no longer gains a route on reload ([#347](https://github.com/components-web-app/api-components-bundle/pull/347))
- A fixture `redirect()` whose derived route name is taken gets a suffixed name instead of failing on the unique constraint ([#347](https://github.com/components-web-app/api-components-bundle/pull/347))
- Two scaffold classes in one `doctrine:fixtures:load` no longer fail on detached entities: each scaffold starts a new builder load and `getRoute()` finds existing routes by name ([#347](https://github.com/components-web-app/api-components-bundle/pull/347))

## [2.0.0-alpha.5](https://github.com/components-web-app/api-components-bundle/compare/2.0.0-alpha.4...2.0.0-alpha.5) - 2026-09-25

### Breaking
- `RouteGeneratorInterface` gains `generatePath()`: an application with its own route generator must implement it ([#335](https://github.com/components-web-app/api-components-bundle/pull/335))
- A throttled password reset, email verification or new email confirmation request returns 429 with `Retry-After`, where it returned 200 ([#332](https://github.com/components-web-app/api-components-bundle/pull/332))
- Registration and a new email address request whose email link is refused return 201, where they returned 400 for a user that had been saved ([#327](https://github.com/components-web-app/api-components-bundle/pull/327))

### Added
- Fixture builder: `allow:` on page groups, `liveAt()` on pages and page data, `redirect()` and `afterRoutes()` ([#322](https://github.com/components-web-app/api-components-bundle/pull/322))
- Orphaned resource report: `POST /_/orphaned_resources/scan` and `GET /_/orphaned_resources` (admin) list unowned groups, empty positions and unused components ([#329](https://github.com/components-web-app/api-components-bundle/pull/329))
- `doctrine:fixtures:load --append` with a `CwaFixtureBuilder` scaffold creates only what is missing and prints a created/kept/skipped summary; adds `PageDataBuilder::withoutRoute()` and `RouteGeneratorInterface::generatePath()` ([#335](https://github.com/components-web-app/api-components-bundle/pull/335))

### Fixed
- The current-password check (`UserPasswordValidator`) reloads the user through `UserRepositoryInterface::loadUserByIdentifier()` instead of the undeclared `find()`, so it works with an application's own user repository ([#337](https://github.com/components-web-app/api-components-bundle/pull/337))
- `generate-fixtures` writes a scaffold that loads and gives back the same site: published state and drafts, uploaded files, component-owned and shared groups, relations, dates, inherited and non-public fields, meta descriptions, scheduled routes and redirects. It lists anything it cannot reproduce ([#322](https://github.com/components-web-app/api-components-bundle/pull/322))
- An application's `#[ORM\MappedSuperclass]` between `AbstractComponent` or `AbstractPageData` and its entities no longer breaks queries with a missing table ([#328](https://github.com/components-web-app/api-components-bundle/pull/328))
- A refused email link no longer leaves a half-done change: a password reset request is a 400 that keeps the existing reset token, and an email after a completed user write (welcome, account enabled, email change and the rest) is logged at error instead of turning the saved write into a 400 ([#327](https://github.com/components-web-app/api-components-bundle/pull/327))
- A throttled password reset, email verification or new email confirmation request is a 429 with `Retry-After` instead of a 200 that sends nothing. Each flow has its own throttle: new `user.new_email_confirmation.repeat_ttl_seconds` and `user.email_verification.repeat_ttl_seconds` (default 300), with `password_reset.repeat_ttl_seconds` now governing only password resets. The throttle starts only once the email is sent ([#332](https://github.com/components-web-app/api-components-bundle/pull/332))
- The `UserPassword` constraint no longer fails with an undefined-method error for a signed-in user that is not the bundle's user, such as an in-memory admin ([#334](https://github.com/components-web-app/api-components-bundle/pull/334))

### Tooling
- `CHANGELOG.md` is kept for every change, and pushing a tag publishes its section as the GitHub release notes ([#320](https://github.com/components-web-app/api-components-bundle/pull/320))
- PHPStan: all 23 `method.notFound` baseline entries resolved; the baseline is down from 98 findings to 69 ([#334](https://github.com/components-web-app/api-components-bundle/pull/334))

## [2.0.0-alpha.4](https://github.com/components-web-app/api-components-bundle/compare/2.0.0-alpha.3...2.0.0-alpha.4) - 2026-09-24

### Breaking
- User email links use the request origin only when it matches `user.email_links.allowed_origins`, else `email_links.default_origin`; with neither set the emails are not sent (400) ([#316](https://github.com/components-web-app/api-components-bundle/pull/316))

### Added
- `GET /_/health`: uncached readiness check, 200 or 503 ([#315](https://github.com/components-web-app/api-components-bundle/pull/315))

### Fixed
- A failed HTTP cache purge after a write is logged instead of turning the saved write into a 500 ([#314](https://github.com/components-web-app/api-components-bundle/pull/314))

### Tests
- Pin the exact cache tags a Route write purges under the API prefix ([#317](https://github.com/components-web-app/api-components-bundle/pull/317))

## [2.0.0-alpha.3](https://github.com/components-web-app/api-components-bundle/compare/2.0.0-alpha.2...2.0.0-alpha.3) - 2026-09-24

### Changed
- The manifest lists a component's own component groups, positions and components ([#310](https://github.com/components-web-app/api-components-bundle/pull/310))

## [2.0.0-alpha.2](https://github.com/components-web-app/api-components-bundle/compare/2.0.0-alpha.1...2.0.0-alpha.2) - 2026-09-24

First 2.x release since `2.0.0-alpha.1` in 2020. Requires PHP 8.5, Symfony ^7.4 || ^8.1, API Platform ^4.4 || ^5.0, Doctrine ORM 3.

### Breaking
- Upgrade to API Platform 4; PUT no longer supported by default ([2b2a2531](https://github.com/components-web-app/api-components-bundle/commit/2b2a2531), [1d4e0478](https://github.com/components-web-app/api-components-bundle/commit/1d4e0478))
- Doctrine ORM 3 ([#177](https://github.com/components-web-app/api-components-bundle/pull/177)); ORM mappings moved from XML to attributes ([8b28106b](https://github.com/components-web-app/api-components-bundle/commit/8b28106b))
- PHP 8.5 required ([91b88cf6](https://github.com/components-web-app/api-components-bundle/commit/91b88cf6)); dependency floors raised ([#285](https://github.com/components-web-app/api-components-bundle/pull/285))
- Service IDs renamed to `silverback.api_components.*`, with class-name aliases kept ([59a3837f](https://github.com/components-web-app/api-components-bundle/commit/59a3837f))
- Page hierarchy moved to `parentPage`/`parentPageData` on `AbstractPage`; `nested` and `parentRoute` removed ([51bf3be7](https://github.com/components-web-app/api-components-bundle/commit/51bf3be7))
- Manifest endpoint renamed to `/_/resource_manifest/{id}` and returns a nested tree per depth ([b8ed6a3d](https://github.com/components-web-app/api-components-bundle/commit/b8ed6a3d), [8db3df53](https://github.com/components-web-app/api-components-bundle/commit/8db3df53))
- Filters are API Platform `QueryParameter`s: `?search=` replaces per-field search ([#297](https://github.com/components-web-app/api-components-bundle/pull/297)); `OrSearchFilter` removed ([#303](https://github.com/components-web-app/api-components-bundle/pull/303))
- Uploads stored under tokenised filenames ([79f4365a](https://github.com/components-web-app/api-components-bundle/commit/79f4365a))
- `urlGenerator: 'public'` honours the filesystem's `public_url` config ([#269](https://github.com/components-web-app/api-components-bundle/pull/269))
- A POST form submit is a full submit; PATCH only validates ([#264](https://github.com/components-web-app/api-components-bundle/pull/264), [#280](https://github.com/components-web-app/api-components-bundle/pull/280))
- `user:create` fails on validation violations instead of writing a duplicate ([#274](https://github.com/components-web-app/api-components-bundle/pull/274))
- `refresh-tokens:expire --field` accepts only `username` and `emailAddress` ([#273](https://github.com/components-web-app/api-components-bundle/pull/273))
- Route generation refuses a page whose parent has no route (422) ([#246](https://github.com/components-web-app/api-components-bundle/pull/246))
- A component placed only in unreachable pages is no longer public ([#233](https://github.com/components-web-app/api-components-bundle/pull/233))
- Mercure is a runtime dependency; symfony/mercure 0.8 supported ([#281](https://github.com/components-web-app/api-components-bundle/pull/281))
- `make:rename-component` writes a Doctrine Migrations `Version` file in the configured path ([#275](https://github.com/components-web-app/api-components-bundle/pull/275))

### Added
- Nested sub-pages, route children endpoint and `cascadeChildPaths` ([51bf3be7](https://github.com/components-web-app/api-components-bundle/commit/51bf3be7), [f85cf3d4](https://github.com/components-web-app/api-components-bundle/commit/f85cf3d4))
- `Route.liveAt` scheduled publication, inherited down the page hierarchy ([#229](https://github.com/components-web-app/api-components-bundle/pull/229), [#231](https://github.com/components-web-app/api-components-bundle/pull/231))
- `CwaFixtureBuilder` and `AbstractCwaScaffold` ([f85cf3d4](https://github.com/components-web-app/api-components-bundle/commit/f85cf3d4), [79f1a765](https://github.com/components-web-app/api-components-bundle/commit/79f1a765), [bdf50025](https://github.com/components-web-app/api-components-bundle/commit/bdf50025))
- Makers: `make:api-component`, `make:page-data`, `make:cwa-scaffold`, `make:rename-component` ([942c192c](https://github.com/components-web-app/api-components-bundle/commit/942c192c), [e8312764](https://github.com/components-web-app/api-components-bundle/commit/e8312764), [ecfc8dee](https://github.com/components-web-app/api-components-bundle/commit/ecfc8dee), [f48eec00](https://github.com/components-web-app/api-components-bundle/commit/f48eec00))
- `silverback:api-components:generate-fixtures` command ([f48eec00](https://github.com/components-web-app/api-components-bundle/commit/f48eec00))
- Command to clean orphaned components and groups ([83c63a0f](https://github.com/components-web-app/api-components-bundle/commit/83c63a0f))
- `#[Silverback\ExplicitAllowOnly]` component placement restriction ([dde605ae](https://github.com/components-web-app/api-components-bundle/commit/dde605ae))
- `#[UploadableField(requiredOnPublish: true)]` ([9d56debd](https://github.com/components-web-app/api-components-bundle/commit/9d56debd), [#267](https://github.com/components-web-app/api-components-bundle/pull/267))
- `ComponentPosition.pageDataClass` ([c09c7bb9](https://github.com/components-web-app/api-components-bundle/commit/c09c7bb9))
- Site config parameters resource ([28954d51](https://github.com/components-web-app/api-components-bundle/commit/28954d51))
- Resend verification and new-email confirmation emails ([80d6329f](https://github.com/components-web-app/api-components-bundle/commit/80d6329f))
- Route filtering, ordering and redirect endpoint ([34137f63](https://github.com/components-web-app/api-components-bundle/commit/34137f63), [f95a84ce](https://github.com/components-web-app/api-components-bundle/commit/f95a84ce))
- Data admin ([#172](https://github.com/components-web-app/api-components-bundle/pull/172))
- CWA Symfony profiler panel ([3797fdd5](https://github.com/components-web-app/api-components-bundle/commit/3797fdd5))
- Cache headers: auth-scoped responses are `private, no-store` ([#201](https://github.com/components-web-app/api-components-bundle/pull/201))
- Shared cache lifetime capped at the next scheduled transition ([#240](https://github.com/components-web-app/api-components-bundle/pull/240))
- Manifests tagged per rendering depth, not by every member IRI ([#244](https://github.com/components-web-app/api-components-bundle/pull/244))
- Rendered HTML purged when a site-wide resource changes ([#235](https://github.com/components-web-app/api-components-bundle/pull/235))
- Purge rendered HTML on request: command and `POST /_/rendered_html/purge` ([#247](https://github.com/components-web-app/api-components-bundle/pull/247))
- Flush the whole HTTP cache: command and `POST /_/http_cache/purge` ([#291](https://github.com/components-web-app/api-components-bundle/pull/291))
- The manifest lists a page's layout component groups once ([#307](https://github.com/components-web-app/api-components-bundle/pull/307))
- `dev-main` aliased to `2.x-dev` ([#308](https://github.com/components-web-app/api-components-bundle/pull/308))

### Fixed
- Route and page collections filter on the inherited `liveAt` ([#236](https://github.com/components-web-app/api-components-bundle/pull/236))
- Route publication and reachability resolved by traversal ([#233](https://github.com/components-web-app/api-components-bundle/pull/233))
- `OrSearchFilter` no longer ORs away security predicates ([#238](https://github.com/components-web-app/api-components-bundle/pull/238))
- Mercure subscribe topics include the application's route prefix ([#305](https://github.com/components-web-app/api-components-bundle/pull/305))
- A failed Mercure publish is logged instead of answering a saved write with a 500 ([#284](https://github.com/components-web-app/api-components-bundle/pull/284))
- Uploaded files no longer disappear on publish ([#209](https://github.com/components-web-app/api-components-bundle/pull/209))
- A missing source image skips its imagine filter ([#301](https://github.com/components-web-app/api-components-bundle/pull/301))
- `/resend-verify-email/{username}` is routable ([#217](https://github.com/components-web-app/api-components-bundle/pull/217))
- `createdAt` enforced on write; `user.email_verification` keys all resolve ([#221](https://github.com/components-web-app/api-components-bundle/pull/221))
- Config guards declared but never checked are enforced ([#242](https://github.com/components-web-app/api-components-bundle/pull/242))
- API Platform's default exception statuses kept ([#295](https://github.com/components-web-app/api-components-bundle/pull/295))
- API Platform's Symfony listeners enabled by the bundle ([#282](https://github.com/components-web-app/api-components-bundle/pull/282))
- References to classes removed upstream ([#288](https://github.com/components-web-app/api-components-bundle/pull/288))
- `ComponentPosition` moves no longer leave duplicate `sortValue`s ([#279](https://github.com/components-web-app/api-components-bundle/pull/279))
- Filtered `componentPositions` stay a JSON list ([#300](https://github.com/components-web-app/api-components-bundle/pull/300))
- Unrouted pages passed through in route children and path cascades ([#268](https://github.com/components-web-app/api-components-bundle/pull/268))
- `make:rename-component` table names, escaped JSON and unresolvable IRIs ([#263](https://github.com/components-web-app/api-components-bundle/pull/263), [#265](https://github.com/components-web-app/api-components-bundle/pull/265), [#248](https://github.com/components-web-app/api-components-bundle/pull/248))
- Maker prompts and help text ([#220](https://github.com/components-web-app/api-components-bundle/pull/220), [#262](https://github.com/components-web-app/api-components-bundle/pull/262))
- Orphan-clean progress bar sized by component count ([#261](https://github.com/components-web-app/api-components-bundle/pull/261))
- JWT no longer leaks across requests in worker mode ([ef947ddc](https://github.com/components-web-app/api-components-bundle/commit/ef947ddc))
- Draft components excluded from anonymous manifests ([6a43adee](https://github.com/components-web-app/api-components-bundle/commit/6a43adee))
- POST to Page/PageData requires `routable_security` ([3c749304](https://github.com/components-web-app/api-components-bundle/commit/3c749304))
- Double Mercure publish from a re-entrant flush ([d702566e](https://github.com/components-web-app/api-components-bundle/commit/d702566e))

### Tooling
- PHPStan level 5 in CI, Scrutinizer retired ([#294](https://github.com/components-web-app/api-components-bundle/pull/294), [#292](https://github.com/components-web-app/api-components-bundle/pull/292))
- CI no longer depends on avoidable network downloads ([#239](https://github.com/components-web-app/api-components-bundle/pull/239), [#277](https://github.com/components-web-app/api-components-bundle/pull/277))
- Behat coverage and mutation gates fixed ([#219](https://github.com/components-web-app/api-components-bundle/pull/219), [#203](https://github.com/components-web-app/api-components-bundle/pull/203), [#210](https://github.com/components-web-app/api-components-bundle/pull/210))
- API Platform 4.4 and 5 both supported ([#302](https://github.com/components-web-app/api-components-bundle/pull/302))

Earlier 2020–2024 changes, made before the move to pull requests, are in the [full diff](https://github.com/components-web-app/api-components-bundle/compare/2.0.0-alpha.1...2.0.0-alpha.2). Some notable ones:
- `RouteGenerator::create()` accepts any `RoutableInterface`, and `RouteGeneratorInterface` is added
- `ComponentGroup.location` lets templates choose which group renders where
- `/me` returns roles, including hierarchical ones
- An expired JWT cookie is returned on logout
- Component collection renamed to component group ([cce8a233](https://github.com/components-web-app/api-components-bundle/commit/cce8a233))
