# Porting a Behat feature to PHPUnit

The working guide for #504 Phase 2. It lives next to the code it describes and is deleted in Phase 3 along with Behat. The pilot ports are the worked examples: `Health/HealthTest.php`, `HttpCache/PurgeHttpCacheOperationTest.php`, `HttpCache/PurgeRenderedHtmlOperationTest.php` and `Debug/SubRequestStopwatchTest.php`.

## Structure and naming

- One class per feature file: `tests/Functional/<Area>/<Feature>Test.php`. The area is the topic (`Health`, `HttpCache`, `Publishable`, `User`, `Uploads`, `Fixtures`…), not the Behat folder (`main` is split by topic).
- One test method per scenario, named `test_<the scenario's behaviour in snake case>`. Keep the scenario's wording, so the map from scenario to test is obvious.
- A `Scenario Outline` becomes one method with a `#[DataProvider]`. Name each row (`yield 'a tag with a comma' => [...]`) so a failure says which row failed.
- The feature's `In order to / As a / I need` text is not copied anywhere. No comments: the method name carries the behaviour (CLAUDE.md, "No code comments").
- Unit tests stay outside `tests/Functional`, mirroring `src/`.

## Base class and traits

Every functional test extends `Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase`. It extends API Platform's `ApiTestCase` (`ApiPlatform\Test\ApiTestCase` on 5.x, aliased from `ApiPlatform\Symfony\Bundle\Test\ApiTestCase` on 4.4), so HTTP and console tests share one base class. It gives you:

| What | How |
|---|---|
| A client on the test app | `static::createClient()`, then `$client->request($method, $url, ['headers' => [...], 'body' => '...'])`. Its base URI is `http://example.com`, Behat's `base_url`, so absolute URLs, the Mercure cookie's domain check and download links match the scenarios. |
| An isolated database | An in-memory SQLite database per PHP process, with the schema created once. Each test runs in a transaction that is rolled back (DAMA `StaticDriver`). Nothing to call. |
| An isolated upload directory | The `local` and `public_url_local` filesystems write under `tests/Functional/app/public/uploads/phpunit-<pid>` (`TEST_UPLOADS_SUBDIRECTORY`, set in `setUp()`), so parallel Infection processes never see each other's files. `static::uploadsDirectory()` is its absolute path. `UploadsTrait` removes it after each test. Behat leaves the variable unset and keeps `public/uploads[/shard-<n>]`. |
| Static test doubles reset | `HubStub`, `UnreachableDatabaseMiddleware`, `RejectedDeleteMiddleware`, `MockClientCallback`, `SwitchableMailer` and `KernelHttpClient`, plus the `EMAIL_LINK_DEFAULT_ORIGIN` and `ORPHANED_RESOURCES_NOTIFY_RECIPIENTS` env vars, are reset before and after every test. A new static switch must be added to `resetTestDoubles()`. |
| A kernel with a route prefix | `static::bootKernel(['routePrefix' => '/_api'])` before `createClient()`. Every helper then works on that kernel: `iriOf()` returns `/_api/...` IRIs, so there is no separate `resourceIriPrefix` to add. |
| A debug kernel | `static::bootDebugKernel()`: `debug: true` and `framework.profiler.collect: false`, its own cache directory. Put the class in `#[Group('debug')]` under `tests/Functional/Debug`. |
| A fresh container after set-up | `static::rebootKernel()`. The traits below call it for you. |
| The entity manager | `static::getEntityManager()` |

### Set-up runs in its own container

Set-up (users, sites, data) runs in the kernel's container, and each set-up helper ends with `rebootKernel()`. So the request under test starts with a fresh container: the profile, the log handler and the HTTP client traces hold only what the request did, as when Behat's contexts set up data in their own kernel. If you write set-up by hand, call `static::rebootKernel()` before the request. Otherwise a purge sent by your set-up flush shows up in `purgedCacheTags()`.

`HubStub` is static, so Mercure updates published by set-up stay recorded. Call `HubStub::clearPublished()` after set-up when the test asserts on what the request published, as Behat's "only Mercure updates published from now are recorded" did. `MercureTrait::mercureUpdatesInLastRequest()` reads the profiler instead and holds only the last request's updates, as Behat's `there should be N mercure messages` did.

## Helpers

The shared helper layer is in `tests/Functional/Trait`, one trait per kind of step, each with its own test (`<Trait>Test.php`) that runs it against the test app. **Use these before writing your own; a helper only your area needs goes in `tests/Functional/<Area>/`.** Add a method to a shared trait only when a second area needs it, with a test in that trait's test class.

How they behave:

- Every method is `protected static`, so call it as `self::name()`.
- **Set-up helpers persist, flush and reboot the kernel**, then return what they made. The entities they return are detached: read their ids and IRIs (`self::iriOf($entity)`), and reload with `self::findByIri()` or a repository read to see what a request changed. Lazy relations of a returned entity don't load, which is why the site helpers return every entity they made in an array.
- **The site and publishable helpers build exactly what the Behat step built**: the same references (`route-page`, `manifest-tag-page`, `collection`, `is_published`/`is_draft`), sort values and dates. Equivalence depends on it, so don't change a shape to suit one test; build a different one beside it.
- **Assertion helpers read the last response** (`self::getClient()->getResponse()`) unless you pass a JSON array, content string or collector.
- Traits that need persistence `use ResourceTrait` themselves, so using several of them together is fine.

| Trait | Methods | Replaces |
|---|---|---|
| `AuthenticationTrait` | `loginAsAdmin($client)`, `loginAsSuperAdmin($client)` (JWT cookie `api_components`), `loginAsUser($client)` (Bearer), `addBearerToken($client, $user)`, `createUser($roles, $username, $emailAddress, $password)`, `createToken($user)` | `@login*` tags, `I add the logged in user's token to the request` |
| `ApiRequestTrait` | `requestJsonLd($client, $method, $url, $body = null, $headers = [])`: `Accept: application/ld+json`, and `Content-Type` `application/merge-patch+json` for `PATCH`, `application/ld+json` otherwise; an array body is JSON-encoded, a string is sent as it is; `$headers` override the defaults. `contentTypeFor($method)`. | Behatch's `I send a … request to …`, RestContext's `… to the resource …`, `with data:`, `with body:`, the content type it chose per method |
| `JsonTrait` | `lastJson()`, `jsonNode($path)`, `jsonNodeExists($path)`, `assertJsonNodeSame($path, $value)`, `assertJsonNodeExists`, `assertJsonNodeNotExists`, `assertJsonNodeCount($path, $n)`, `assertJsonNodeMatches($path, $regex)`, `assertJsonNodeListsExactly($path, [...])` (any order), `assertJsonNodeIsNow($path, $now)` (within a second), `assertJsonMatchesSchemaFile($file)`, `assertEachJsonItemMatchesSchemaFile($file)`. Paths are Behatch's: `member[0].@id`, `root`. Schema files are read from `features/assets/schema` (`jsonSchemaDirectory()`, moved in Phase 3), with relative `$ref`s resolved. Every method takes an optional JSON array (or content string for the schema ones) instead of the last response. | Behatch and JsonContext JSON node and schema steps |
| `ResourceTrait` | `persistAndFlush(...$entities)` (timestamps new `#[Timestamped]` entities first), `iriOf($resource)`, `findByIri($iri)` (clears the entity manager; `null` when gone), `assertResourceExists($iri)`, `assertResourceNotExists($iri)`, `countEntities($class)` | `the resource … should (not) exist`, `there should be N … resources`, DoctrineContext's single-entity Givens (`there is a DummyComponent`: `self::persistAndFlush(new DummyComponent())`) |
| `SiteTrait` | `createPage($reference = 'page', $isTemplate = false)`, `createLayout($reference = 'no-reference', $uiComponent = null)`, `createSiteConfigParameter($key = 'key', $value = 'value')`, `createRouteWithPage($path)`, `createComponentGroupWithComponents($n, $reference = 'collection')`, `createRoutedPageWithComponent($path, $component = null)`, `createComponentInRoute($path)`, `createPageDataWithRoute(?$path)`, `routeAt($path)`, `setRouteLiveAt($path, ?$liveAt)`, `assertRouteRedirectsTo($path, $target)`, `assertRouteDoesNotRedirect($path)`; and `new*()` builders (unpersisted) for your own shapes | DoctrineContext's `there is a Page`/`Layout`/`SiteConfigParameter`, `there is a Route … with a page`, `there is a ComponentGroup with N components`, `there is a routed Page with a component group and a component with the path …`, `there is a component in a route with the path …`, `there is a PageData resource with the route path …`, `the Route … goes live at / in / has no go-live date`, `the Route … should (not) redirect` |
| `ManifestTrait` | `manifestUrlOf($page)`, `manifestIrisAtDepth($depth)`, `manifestRootIriAtDepth($depth)`, `assertManifestDepthContains($depth, $iri)`, `assertManifestDepthNotContains`, `assertManifestDepthContainsIriMatching($depth, $regex)`, `assertManifestDepthContainsNoIriMatching`, `assertEveryManifestIriResolves()` | DoctrineContext's `the manifest depth …` steps and `every IRI in the manifest should resolve` |
| `PublishableTrait` | `newPublishable(?$publishedAt, $resource = null, $forceDraft = false)`, `createPublishable(?$publishedAt, $resource = null)`, `createPublishedWithDraft(?$draftPublishAt)` → `[$published, $draft]`, `createDraftFor($published, ?$publishAt)`, `createDraftAndPublishedResources($n)`, `assertMemberShowsDraftsInsteadOfPublished($member, $resources)`, `assertMemberShowsPublishedOnly($member, $resources)`, `assertMemberShowsPublishedOnlyWithoutDraftResources`, `placeInComponentPosition($component)` | PublishableContext |
| `CollectionTrait` | `createDummyComponents($n)`, `createDummyResourcesWithPagination($n)`, `createDummyResourcesWithFilters($n)`, `createCollection($resourceIri = '/component/dummy_components', ?$defaultQueryParameters)` | CollectionContext |
| `UploadsTrait` | `assetPath($name)`, `assetFile($name)` (`features/assets/files`), `dataUriOf($name)`, `base64Of($name)`, `createUploadable($resource, ['file' => 'image.png', …])`, `filesystem($adapter)`, `storedPathOf($resource)`, `storedFilesOf($resource)` → `[[$adapter, $path], …]`, `assertStoredFilesOfResourceExist($resource)` (returns them), `assertStoredFilesRemoved($files)`, `removeStoredFilesOf($resource)`, `assertStoredFileExists($path, $adapter = 'local')`, `assertStoredFileNotExists`, `writeStoredFile($path, $olderThanMinimumAge = false)`, `ageStoredFile($path)`, `emptyUploadFilestores()`, `addFileInfo($path, …, $filter = null)`, `countFileInfoRows(?$filter)`; removes this process's upload directory after every test | UploadsContext, RestContext's `base64(…)`/`base64string(…)` transforms |
| `CacheTagsTrait` | `purgedCacheTags()`, `assertCacheTagPurged($tag)`, `assertCacheTagNotPurged($tag)`, `assertCacheTagPurgedTimes($tag, $n)`, `assertOnlyCacheTagsPurged([...])`, `assertNoCachePurgeRequestSent()`; `manifestCacheTag($iri)`; `responseCacheTags()`, `assertResponseHasCacheTag($tag)`, `assertResponseLacksCacheTag($tag)`, `assertResponseCarriesNoCacheTags()`; `traceOutsideAnHttpRequest(fn (ContainerInterface $c) => …)` returns the HTTP client collector, which every purge assertion takes as its last argument | ProfilerContext's purge and response-tag steps, RoutePrefixContext's writes outside a request |
| `CacheControlTrait` | `sharedMaxAge()`, `assertSharedMaxAgeAtMost($s)`, `assertSharedMaxAgeAtLeast($s)`, `assertResponseHeaderIsSecondsBetween($name, $min, $max)` | JsonContext's shared max age and header-seconds steps |
| `CookieTrait` | `responseCookie($name)`, `assertResponseHasNoCookie($name)`, `assertResponseCookieExpiresWithin($name, $s)`, `mercureCookieName()`, `mercureCookiePayload()`, `mercureCookieTopics()` (fails on a grant that is not a URL pattern), `assertMercureCookieGrantsTopicMatching($regex)`, `assertMercureCookieGrantsNoTopicMatching`, `assertMercureCookieCleared()`, `assertMercureCookieAndTokenExpireIn($s)`, `sendMercureCookieWithDraftTopics($client)` | JsonContext's cookie and Mercure cookie steps |
| `MercureTrait` | `mercureUpdates()` (`HubStub`), `mercureUpdatesInLastRequest()` (profiler), `mercureUpdatesFor($iri)` (topic path equals the IRI, draft topic included), `mercureUpdateFor($iri, $private)` (exactly one), `mercureUpdateData($update)`, `mercureTopicsWithoutOrigin($update)`, `assertMercureUpdatePublishedFor($iri)`, `assertNoMercureUpdatePublished()`, `assertNoPublicMercureUpdateContains($text)`, `describeMercureUpdates()` | ProfilerContext's Mercure steps |
| `EmailTrait` | `setEmailLinkDefaultOrigin($origin)`, `sentTemplatedEmails()`, `assertNoEmailSent()`, `assertOneTemplatedEmailSentTo($address = 'user@example.com')` (the common checks of `I should get a … email sent`, returning the email for its type's checks), `sentEmailLink()` | ProfilerContext's email steps |
| `LogRecordsTrait` | `logRecords()`, `assertLogged($description, fn (LogRecord $r): bool => …)`, `assertNotLogged(…)` | ProfilerContext's `… should have been logged` steps |
| `ConsoleTrait` | `runCommand($name, $input = [], $verbosity)` → `CommandTester` (no console events; `I run the console command`, `UserCommandContext`, the purge command), `runApplication($input)` → `ApplicationTester` (through `Application::run()`, with console events, exceptions not caught; `I run the command`) | console steps in DoctrineContext, FixtureContext, UserCommandContext, RoutePrefixContext |
| `CwaFixtureTrait` | `buildSite(fn (CwaFixtureBuilder $cwa) => …)`: builds and flushes with the bundle's own fixture builder | site shapes no `SiteTrait` method builds |

Don't port `DoctrineContext` wholesale.

## Building test data

1. **A `SiteTrait`, `PublishableTrait`, `UploadsTrait` or `CollectionTrait` method when it builds what the step built.** It is the step's own shape, so the port stays equivalent.
2. **`CwaFixtureTrait::buildSite()`** for other site shapes. Layouts, pages, routes, groups, components, drafts, page data, redirects and `liveAt` are all expressible with `CwaFixtureBuilder`, which also timestamps and links both sides. Capture an entity you need afterwards by creating it outside the closure and passing it in with `use`. Its id is set after the flush.
3. **`ResourceTrait::persistAndFlush()`** with `SiteTrait`'s `new*()` builders for anything else (an orphaned group, a position with neither a component nor a page data property). It timestamps, flushes and reboots.
4. Read the matching `DoctrineContext`/`FixtureContext` step to see what the scenario really had. Many steps create more than their name says.

## Replacing each kind of step

| Behat | PHPUnit |
|---|---|
| `@loginAdmin` / `@loginSuperAdmin` | `self::loginAsAdmin($client)` / `self::loginAsSuperAdmin($client)` |
| `@loginUser` | `self::loginAsUser($client)` (Bearer header, as the tag did) |
| `I add the logged in user's token to the request` | `self::addBearerToken($client, $user)` with the user `loginAs*()` returned |
| `Given I add "Accept" header equal to …` (Background) | nothing with `requestJsonLd()`; otherwise a `private const array` of headers passed to every `request()` |
| `When I send a "POST" request to "/x" with body:` / `with data:` | `self::requestJsonLd($client, 'POST', '/x', '…raw JSON…')` or `[...]`. A raw string keeps malformed bodies. `$client->request(..., ['json' => [...]])` only when the content type should be `application/json`. |
| `I send a … request to the resource "x"` (and the postfix) | `self::requestJsonLd($client, $method, $iri . '/postfix', …)` with the IRI from a local variable |
| `with data:` cells `base64(file)`, `base64string(file)`, `characters(n)`, `now`, `resource[x]`, `json_decode(…)` | `self::dataUriOf('file')`, `self::base64Of('file')`, `str_repeat('a', n)`, a captured `$now->format(\DateTimeInterface::ATOM)`, the IRI variable, a PHP array |
| `Then the response status code should be 204` | `self::assertResponseStatusCodeSame(204)` |
| `the header "X" should be equal to "v"` / `should not exist` | `self::assertResponseHeaderSame('x', 'v')` / `self::assertResponseNotHasHeader('x')` |
| `the header "X" should contain "v"` | `self::assertStringContainsString('v', (string) self::getClient()->getResponse()->headers->get('x'))` |
| `the JSON should be equal to:` | `self::assertSame([...], $response->toArray(false))`, or `self::assertJsonEquals()` |
| `the JSON should be a superset of:` | `self::assertJsonContains([...])` |
| `the JSON node "a.b[0].c" should be equal to …` / `should exist` / `should not exist` / `should have N elements` / `should match` | `self::assertJsonNodeSame('a.b[0].c', …)`, `assertJsonNodeExists`, `assertJsonNodeNotExists`, `assertJsonNodeCount`, `assertJsonNodeMatches`. Compare with the real type: Behatch compared strings, so `"1"` in the step is `1` here. |
| `the JSON node … should be equal to the IRI of the resource "x"` | `self::assertJsonNodeSame('node', $iri)` |
| `the JSON node … should list exactly the resources` | `self::assertJsonNodeListsExactly('node', [$iri, …])` |
| `the JSON node … should be now` | `self::assertJsonNodeIsNow('node', $now)` with `$now` captured before the request |
| `the JSON should be valid according to the schema file "x.json"` / `an array with each entry valid …` | `self::assertJsonMatchesSchemaFile('x.json')` / `self::assertEachJsonItemMatchesSchemaFile('x.json')` |
| `the response should be empty` | `self::assertSame('', $response->getContent())` |
| `the response should not contain "x"` | `self::assertStringNotContainsString('x', $response->getContent(false))` |
| `the response resource should be saved as "x"` / `I save the JSON node … as the resource` / `the resource "x"` | keep the IRI in a local variable (`self::jsonNode('@id')`) |
| `the resource "x" should (not) exist` | `self::assertResourceExists($iri)` / `self::assertResourceNotExists($iri)` |
| Purge assertions (`the resource … should be purged`, `the cache tag … should be purged`, `… N times`, `the only cache tags purged`, `no cache purge request`, the manifest tag) | `self::assertCacheTagPurged($iri)`, `assertCacheTagPurgedTimes`, `assertOnlyCacheTagsPurged`, `assertNoCachePurgeRequestSent`, `assertCacheTagPurged(self::manifestCacheTag($pageIri))`. **Never assert an exact purge or tag header**: the helpers split on `/[,\s]+/`. |
| `the response should carry no cache tags` / tag (not) in the response | `self::assertResponseCarriesNoCacheTags()` / `assertResponseHasCacheTag($tag)` / `assertResponseLacksCacheTag($tag)` |
| `the response shared max age should be at most/least N`, `the response header … should be a number of seconds between` | `CacheControlTrait` |
| Mercure (`no Mercure update…`, `a Mercure update should have been published for…`, public/private updates, `there should be N mercure messages`) | `MercureTrait`; clear `HubStub` after set-up |
| Emails (`I should get a … email sent`, `I should not receive any emails`, `the link in the sent email`) | `self::assertOneTemplatedEmailSentTo($address)` plus the type's own checks, `self::assertNoEmailSent()`, `self::sentEmailLink()`; or Symfony's `assertEmailCount()` / `getMailerMessages()` |
| `links in user emails default to the origin` | `self::setEmailLinkDefaultOrigin($origin)` before the request |
| Logged failures (`… should have been logged`, `no … should have been logged`) | `self::assertLogged('description', fn (LogRecord $r): bool => …)` / `self::assertNotLogged(…)` |
| Cookies (`the response should (not) have a "x" cookie`, max age, value, the mercure cookie steps) | `CookieTrait`. Set a cookie with `$client->getKernelBrowser()->getCookieJar()->set(new Cookie(...))`. |
| Static doubles (`Given the database is unreachable`, `the HTTP cache is unreachable`, `the mailer is unreachable`, `the Mercure hub is unreachable`, `the database rejects deletes`) | call the double directly (`UnreachableDatabaseMiddleware::setUnreachable(true)`, `MockClientCallback::setCacheUnreachable(true)`, `SwitchableMailer::setUnreachable(true)`, `HubStub::setUnreachable(true)`, `RejectedDeleteMiddleware::setRejectDeletes(true)`). The base class resets them. |
| `Given the API routes are imported under the prefix "/_api"` | `static::bootKernel(['routePrefix' => '/_api'])` before `createClient()`, then request `/_api/...`; `iriOf()` already carries the prefix |
| `a Route with the path … is written outside an HTTP request` | `self::traceOutsideAnHttpRequest(fn (ContainerInterface $c) => …)`, then pass the collector it returns to the purge assertions |
| Console (`I run the console command`, `UserCommandContext`, `RoutePrefixContext`'s purge command) | `$tester = self::runCommand('name', [...])`, then `$tester->getStatusCode()` / `getDisplay()`. A verbose run passes `OutputInterface::VERBOSITY_VERBOSE`. |
| `I run the command` (console events, like the fixture load listener) | `$tester = self::runApplication(['command' => 'name', ...])`; `Command::run()` does not dispatch console events |
| `@saveNow` / "now" | capture `new \DateTimeImmutable()` in the test |
| A scenario that proves a reset ("… is reachable again in the next scenario") | a test with `#[Depends]` on the one that set the double, so it runs after it |
| Profiler-only assertions | `self::getClient()->getProfile()->getCollector('…')` (the test app collects every request) |

## Step definitions by context

Every context in `features/bootstrap`, by group of steps. A step whose replacement doesn't exist yet becomes a method in the area's own helper (`tests/Functional/<Area>/`), or in a shared trait once a second area needs it.

| Context | Steps | Replacement |
|---|---|---|
| `DoctrineContext` | login tags, `I add the logged in user's token`, the logged in user deleted or recreated | `AuthenticationTrait` (`addBearerToken()`; delete or recreate through the entity manager) |
| | users, their tokens, verification and new email state | `AuthenticationTrait::createUser()`, then set the fields on the entity (area helper in `User/`) |
| | `there is a … form` | persist a `Form` with the form type, in the Users area's helper |
| | `there is a Page`/`Layout`/`SiteConfigParameter`, `there is a Route … with a page`, `there is a ComponentGroup with N components`, `there is a routed Page with a component group and a component …`, `there is a component in a route …`, `there is a PageData resource with the route path …`, route `liveAt` and redirect assertions | `SiteTrait` |
| | other layouts, pages, routes, redirects, page data, groups, positions, nesting, routeless and reachable-without-route pages | `buildSite()` with `CwaFixtureBuilder`; the few shapes it can't build (a position with neither a component nor a page data property, cycles of parent pages, a sortValue set directly) with `SiteTrait`'s `new*()` builders and `persistAndFlush()` |
| | Dummy*/Restricted*/timestamped resources, `there are N Routes` | `persistAndFlush()` |
| | manifest assertions (`the manifest depth …`, `every IRI in the manifest should resolve`) | `ManifestTrait` |
| | `the resource … should (not) exist`, counts, `the Route … should be live/redirect` | `ResourceTrait`, `SiteTrait`; liveness through the `RouteLiveResolver` service |
| | refresh tokens, JWT expired/invalid | persist a `RefreshToken`, or build the token with the JWT manager or encoder |
| | orphaned resources report, notifications, scheduled scan | the report store service and `EmailTrait` / mailer assertions; the scan with `runCommand()` |
| | `I run the console command …`, exit code, output | `ConsoleTrait::runCommand()` |
| | `requests … through one kernel leave nothing …` (worker mode) | one booted kernel, `$client->disableReboot()`, then `services_resetter->reset()` and assertions on the maps |
| `FixtureContext` | the site (`the site has …`), the scaffold (`the scaffold has …`), generate, purge and reload, append, asset store, the load summary | `ConsoleTrait::runApplication()` for `generate-fixtures` and `doctrine:fixtures:load`, with the site built by `buildSite()`; byte-for-byte file comparisons stay as they are |
| `JsonContext` | JSON node and schema assertions | `JsonTrait` (`assertJsonContains()` from API Platform for a superset) |
| | cookies (`mercure` cookie, max age, value) | `CookieTrait` |
| | shared max age, header seconds | `CacheControlTrait` |
| | the order response | assertions on `toArray()` of the order endpoint (Components area) |
| `RestContext` | `I send a … request to the resource …`, `with data:`, `with parameters:`, headers equal to a resource, the transforms | `ApiRequestTrait`, `UploadsTrait::dataUriOf()`/`base64Of()` |
| | orphan deletion and order requests | `requestJsonLd()` with the body |
| `PublishableContext` | published and draft resources, `set to publish at`, the collection assertions, `there is a ComponentPosition with the resource` | `PublishableTrait` |
| `UploadsContext` | uploadable resources with files, stored files, file info, the filesystems, stored-file assertions | `UploadsTrait` |
| | download endpoint, orphaned files report | requests, the report store service (Uploads area) |
| `CollectionContext` | `there are N … resources`, Collection resources | `CollectionTrait` |
| `ProfilerContext` | purges and cache tags | `CacheTagsTrait` |
| | Mercure updates, publish failures | `MercureTrait`, `assertLogged()` |
| | emails and email links | `EmailTrait` |
| | static doubles, memory limit | the double's setter; restore `memory_limit` in the test |
| | HTTP cache warm | `KernelHttpClient::getRequests()` / `respondWith()` |
| | logged warnings and errors | `LogRecordsTrait` |
| `RoutePrefixContext` | the route prefix, writes outside a request, the purge command | `bootKernel(['routePrefix' => …])`; `CacheTagsTrait::traceOutsideAnHttpRequest()`; `ConsoleTrait::runCommand()` inside it for the purge command |
| `UserCommandContext` | `user:create` and its assertions | `ConsoleTrait::runCommand()` plus repository reads |
| `MakerContext` | rename component migration | console test on the migration maker, file assertions (Fixtures and maker area) |
| `CoverageContext` | none (coverage hooks) | PHPUnit's own coverage; nothing to port |
| Behatch / Mink | `I send a … request`, `the response status code`, `the header …`, `the JSON node …`, `print last response` | the rows of the table above; dump with `dump($response->getContent(false))` while writing, never commit it |

## Running

```bash
php vendor/bin/phpunit --testsuite functional                    # the functional suite
php vendor/bin/phpunit --testsuite functional --group debug      # debug-kernel tests only
php vendor/bin/phpunit tests/Functional/HttpCache                 # one area
API_PLATFORM_USE_SYMFONY_LISTENERS=1 php vendor/bin/phpunit --testsuite functional   # listener mode
```

- **The container is not rebuilt** (`debug: false`). After changing a service, a config file or a route, warm the cache again: `php tests/Functional/app/bin/console cache:clear -e test` (and delete `tests/Functional/app/var/cache/test_*` for the prefixed and debug kernels).
- **A full `phpunit` run (both suites, one process) sees `TokenGeneratorTest`'s `Silverback\ApiComponentsBundle\Security\bin2hex()` stub in every later test**, so a token in an email link or a stored token reads `16_bin2hex`. Assert a token's shape loosely (`[^/]+`), or the test passes alone and fails in the full run.
- Warm the cache before a coverage run, as CI does. On a cold cache the first test compiles the container under coverage, and Infection then counts compile-time code as covered by tests that can't kill it.

## Equivalence checklist (every port PR)

1. **Coverage.** On a clean cache, run the old feature alone with coverage:
   `rm -rf tests/Functional/app/var/cache && php -d pcov.enabled=1 vendor/bin/behat --profile=default-coverage features/<area>/<feature>.feature`, then copy `build/logs/behat/clover.xml` away.
   Then the new class, on a clean cache too:
   `rm -rf tests/Functional/app/var/cache && php -d pcov.enabled=1 vendor/bin/phpunit -c phpunit.coverage.xml.dist tests/Functional/<Area>/<Feature>Test.php --coverage-clover <new>.xml`.
   Every `src/` line the old run covered must be covered by the new one. Both runs need the same cache state: on a warm cache, the container compile and the metadata caches (compiler passes, `Configuration`, resource metadata factories, serializer and validator loaders) don't run, so they show up as a false shortfall. List any real shortfall with a reason.
2. **Mutation.** Generate coverage for the new class on a warm cache (`--coverage-xml=<dir>/coverage-xml --log-junit=<dir>/junit.xml`), then
   `php infection.phar --coverage=<dir> --skip-initial-tests --test-framework-options="--testsuite=functional" --filter=<the src files the old run covered, comma-separated> --threads=8 --only-covering-test-cases`, as CI runs it.
   Kill the survivors that belong to the feature's behaviour, or list them with the reason they belong to another feature's port.
3. **Behaviour.** A scenario → test method table in the PR. Every `Then` has an assertion.

## What a port PR contains

Every port PR targets the integration branch **`test/504-phpunit`**, not `main`: branch from it, merge `origin/test/504-phpunit` into your branch right before pushing, and open the PR with `--base test/504-phpunit`. The coordinator merges `main` into it regularly, and one final PR takes it into `main`.

- The new test class(es) and any trait methods they needed.
- The deleted scenarios, and the feature file once it is empty.
- Step definitions no remaining feature uses, deleted; a static reset in a Behat hook removed once no feature sets that double.
- The equivalence table (coverage numbers, covered MSI, survivors, scenario → test map) and anything you were unsure of.
- A `### Tooling` line under `## Unreleased` in `CHANGELOG.md`; it reaches `main` with the final merge.
- Nothing in `src/`. If a port shows a real bug, stop and report it on #504.
