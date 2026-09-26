# Changelog

Every change that reaches `main` adds a line under **Unreleased**. When a version is tagged, that section is renamed to the tag and becomes the GitHub release notes.

## Unreleased

### Fixed
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
