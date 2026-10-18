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
| A client on the test app | `static::createClient()`, then `$client->request($method, $url, ['headers' => [...], 'body' => '...'])` |
| An isolated database | An in-memory SQLite database per PHP process, with the schema created once. Each test runs in a transaction that is rolled back (DAMA `StaticDriver`). Nothing to call. |
| Static test doubles reset | `HubStub`, `UnreachableDatabaseMiddleware`, `RejectedDeleteMiddleware`, `MockClientCallback`, `SwitchableMailer` and `KernelHttpClient`, plus the `EMAIL_LINK_DEFAULT_ORIGIN` and `ORPHANED_RESOURCES_NOTIFY_RECIPIENTS` env vars, are reset before and after every test. A new static switch must be added to `resetTestDoubles()`. |
| A kernel with a route prefix | `static::bootKernel(['routePrefix' => '/_api'])` before `createClient()` |
| A debug kernel | `static::bootDebugKernel()`: `debug: true` and `framework.profiler.collect: false`, its own cache directory. Put the class in `#[Group('debug')]` under `tests/Functional/Debug`. |
| A fresh container after set-up | `static::rebootKernel()`. The traits below call it for you. |
| The entity manager | `static::getEntityManager()` |

Traits in `tests/Functional/Trait`:

| Trait | Methods |
|---|---|
| `AuthenticationTrait` | `loginAsAdmin($client)`, `loginAsSuperAdmin($client)` (JWT cookie `api_components`), `loginAsUser($client)` (`Authorization: Bearer`), `createUser($roles, …)`, `createToken($user)` |
| `CwaFixtureTrait` | `buildSite(fn (CwaFixtureBuilder $cwa) => …)`: builds and flushes with the bundle's own fixture builder |
| `CacheTagsTrait` | `purgedCacheTags()`, `assertOnlyCacheTagsPurged([...])`, `assertCacheTagPurgedTimes($tag, $n)`, `assertCacheTagNotPurged($tag)`, `assertNoCachePurgeRequestSent()` |
| `LogRecordsTrait` | `logRecords()`, `assertLogged($description, fn (LogRecord $r): bool => …)` |

Add a method to a trait when a second test needs it; keep one-off helpers private in the test class. Don't port `DoctrineContext` wholesale.

### Set-up runs in its own container

Set-up (users, sites, data) runs in the kernel's container, and each set-up helper ends with `rebootKernel()`. So the request under test starts with a fresh container: the profile, the log handler and the HTTP client traces hold only what the request did, as when Behat's contexts set up data in their own kernel. If you write set-up by hand, call `static::rebootKernel()` before the request. Otherwise a purge sent by your set-up flush shows up in `purgedCacheTags()`.

`HubStub` is static, so Mercure updates published by set-up stay recorded. Call `HubStub::clearPublished()` after set-up when the test asserts on what the request published, as Behat's "only Mercure updates published from now are recorded" did.

## Building test data

1. **`CwaFixtureTrait::buildSite()` first.** Layouts, pages, routes, groups, components, drafts, page data, redirects and `liveAt` are all expressible with `CwaFixtureBuilder`, which also timestamps and links both sides. Capture an entity you need afterwards by creating it outside the closure and passing it in with `use`. Its id is set after the flush.
2. **A small private method, or a trait method, for anything the builder can't express** (users, a SiteConfigParameter, an orphaned group, a stored file). Persist through `static::getEntityManager()`, timestamp with `TimestampedDataPersister` when the entity is `#[Timestamped]`, flush, then `static::rebootKernel()`.
3. Read the matching `DoctrineContext`/`FixtureContext` step to see what the scenario really had. Many steps create more than their name says.

## Replacing each kind of step

| Behat | PHPUnit |
|---|---|
| `@loginAdmin` / `@loginSuperAdmin` | `self::loginAsAdmin($client)` / `self::loginAsSuperAdmin($client)` |
| `@loginUser` | `self::loginAsUser($client)` (Bearer header, as the tag did) |
| `Given I add "Accept" header equal to …` (Background) | a `private const array` of headers passed to every `request()` |
| `When I send a "POST" request to "/x" with body:` | `$client->request('POST', '/x', ['headers' => self::JSON_LD, 'body' => '…'])`. Use `'body'` for raw JSON (malformed bodies included), and `'json' => [...]` only when the content type should be `application/json`. |
| `Then the response status code should be 204` | `self::assertResponseStatusCodeSame(204)` |
| `the header "X" should be equal to "v"` / `should not exist` | `self::assertResponseHeaderSame('x', 'v')` / `self::assertResponseNotHasHeader('x')` |
| `the header "X" should contain "v"` | `self::assertStringContainsString('v', (string) self::getClient()->getResponse()->headers->get('x'))` |
| `the JSON should be equal to:` | `self::assertSame([...], $response->toArray(false))`, or `self::assertJsonEquals()` |
| `the JSON should be a superset of:` | `self::assertJsonContains([...])` |
| `the JSON node "a.b[0].c" should be equal to …` | `self::assertSame(…, $response->toArray(false)['a']['b'][0]['c'])` |
| `the JSON should be valid according to the schema file` | `self::assertMatchesJsonSchema()` |
| `the response should be empty` | `self::assertSame('', $response->getContent())` |
| `the response should not contain "x"` | `self::assertStringNotContainsString('x', $response->getContent(false))` |
| `the response resource should be saved as "x"` / `the resource "x"` | keep the IRI in a local variable (`$response->toArray()['@id']`) |
| Purge assertions (`ProfilerContext`) | `CacheTagsTrait`. **Never assert an exact purge or tag header**: the helpers split on `/[,\s]+/`. |
| `the response should carry no cache tags` / tag in the response | read `xkey`/`surrogate-key`/`cache-tags` from `self::getClient()->getResponse()` and split on `/[,\s]+/` |
| Mercure (`no Mercure update…`, `a Mercure update should have been published for…`) | `HubStub::getPublished()` (a list of `Update`); clear it after set-up |
| Emails (`I should get a … email sent`) | `self::assertEmailCount()` / `self::getMailerMessages()` (Symfony's `MailerAssertionsTrait`), or the `TemplatedEmailMessageEventSubscriber` service for the template context |
| Logged failures (`… should have been logged`) | `self::assertLogged('description', fn (LogRecord $r): bool => …)` |
| Cookies (`the response should have a "x" cookie`) | `self::getClient()->getResponse()->headers->getCookies()`. Set a cookie with `$client->getKernelBrowser()->getCookieJar()->set(new Cookie(...))`. |
| Static doubles (`Given the database is unreachable`, `the HTTP cache is unreachable`, `the mailer is unreachable`, `the Mercure hub is unreachable`, `the database rejects deletes`) | call the double directly (`UnreachableDatabaseMiddleware::setUnreachable(true)`, `MockClientCallback::setCacheUnreachable(true)`, `SwitchableMailer::setUnreachable(true)`, `HubStub::setUnreachable(true)`, `RejectedDeleteMiddleware::setRejectDeletes(true)`). The base class resets them. |
| `Given the API routes are imported under the prefix "/_api"` | `static::bootKernel(['routePrefix' => '/_api'])` before `createClient()`, then request `/_api/...` |
| Console (`I run the console command`, `I run the command`, `UserCommandContext`, `RoutePrefixContext`'s purge command) | `$tester = new CommandTester((new Application(static::bootKernel()))->find('name')); $tester->execute([...]);` then `$tester->getStatusCode()` / `getDisplay()`. A test that needs console events (`console.command`/`console.terminate`, like the fixture load listener) runs `$application->setAutoExit(false); $application->run(new ArrayInput([...]), $output)` instead, which `Command::run()` does not dispatch. |
| `@saveNow` / "now" | capture `new \DateTimeImmutable()` in the test |
| A scenario that proves a reset ("… is reachable again in the next scenario") | a test with `#[Depends]` on the one that set the double, so it runs after it |
| Profiler-only assertions | `self::getClient()->getProfile()->getCollector('…')` (the test app collects every request) |

## Step definitions by context

Every context in `features/bootstrap`, by group of steps. A step whose replacement doesn't exist yet becomes a trait method in the first port that needs it.

| Context | Steps | Replacement |
|---|---|---|
| `DoctrineContext` | login tags, `I add the logged in user's token`, the logged in user deleted or recreated | `AuthenticationTrait` (`createToken()` for the token; delete or recreate through the entity manager) |
| | users, their tokens, verification and new email state | `AuthenticationTrait::createUser()`, then set the fields on the entity |
| | `there is a … form` | persist a `Form` with the form type, in a private method or a trait once shared |
| | layouts, pages, routes, redirects, page data, groups, positions, nesting, `liveAt`, routeless and reachable-without-route pages | `buildSite()` with `CwaFixtureBuilder`; the few shapes it can't build (a position with neither a component nor a page data property, cycles of parent pages, a sortValue set directly) are written through the entity manager |
| | Dummy*/Restricted*/timestamped resources, SiteConfigParameters, `there are N Routes` | persist directly |
| | manifest assertions (`the manifest depth …`, `every IRI in the manifest should resolve`) | assertions on `toArray()` of `GET /_/resource_manifest/…`; add a `ManifestTrait` in the manifest port |
| | `the resource … should (not) exist`, counts, `the Route … should be live/redirect` | repository reads through `static::getEntityManager()` after `clear()` |
| | refresh tokens, JWT expired/invalid | persist a `RefreshToken`, or build the token with the JWT manager or encoder |
| | orphaned resources report, notifications, scheduled scan | the report store service and the `TemplatedEmailMessageEventSubscriber` / mailer assertions; the scan as a console test |
| | `I run the console command …`, exit code, output | `CommandTester` (see above) |
| | `requests … through one kernel leave nothing …` (worker mode) | one booted kernel, `$client->disableReboot()`, then `services_resetter->reset()` and assertions on the maps |
| `FixtureContext` | the site (`the site has …`), the scaffold (`the scaffold has …`), generate, purge and reload, append, asset store, the load summary | console tests that run `generate-fixtures` and `doctrine:fixtures:load` through `Application::run()`, with the site built by `buildSite()`; byte-for-byte file comparisons stay as they are |
| `JsonContext` | JSON superset/schema/node assertions | `ApiTestAssertionsTrait` (`assertJsonContains`, `assertMatchesJsonSchema`) or `toArray()` |
| | cookies (`mercure` cookie, max age, value) | `headers->getCookies()`; decode the Mercure JWT in a `MercureCookieTrait` |
| | shared max age, header seconds | `Response::getMaxAge()` / header parsing |
| | the order response | assertions on `toArray()` of the order endpoint |
| `RestContext` | `I send a … request to the resource …`, `with data:`, `with parameters:`, headers equal to a resource | `$client->request()` with the IRI from a local variable |
| | orphan deletion and order requests | `$client->request('POST'/'PATCH', …, ['json' => …])` |
| `PublishableContext` | published and draft resources, `set to publish at` | `buildSite()` with `$cwa->publish($c, at: …)` and `$cwa->component($c)->draft($d)`, or persist directly |
| | `the response should include the draft/published resources` | assertions on `toArray()` |
| `UploadsContext` | uploadable resources with files, stored files, file info, imagine rows | persist through the entity manager with `UploadableFileManager`; files from `features/assets/files` |
| | download endpoint, stored-file assertions, orphaned files report | requests, the filesystem service and repository reads |
| `CollectionContext` | `there are N … resources`, Collection resources | persist directly in a loop |
| `ProfilerContext` | purges and cache tags | `CacheTagsTrait` |
| | Mercure updates, publish failures | `HubStub::getPublished()`, `assertLogged()` |
| | emails and email links | Symfony's mailer assertions / `TemplatedEmailMessageEventSubscriber` |
| | static doubles, memory limit | the double's setter; restore `memory_limit` in the test |
| | HTTP cache warm | `KernelHttpClient::getRequests()` / `respondWith()` |
| | logged warnings and errors | `assertLogged()` |
| `RoutePrefixContext` | the route prefix, writes outside a request, the purge command | `bootKernel(['routePrefix' => …])`; `CommandTester` on the prefixed kernel; read `data_collector.http_client` after `lateCollect()` for traces outside a request |
| `UserCommandContext` | `user:create` and its assertions | `CommandTester` plus repository reads |
| `MakerContext` | rename component migration | console test on the migration maker, file assertions |
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
- Warm the cache before a coverage run, as CI does. On a cold cache the first test compiles the container under coverage, and Infection then counts compile-time code as covered by tests that can't kill it.

## Equivalence checklist (every port PR)

1. **Coverage.** On a clean cache, run the old feature alone with coverage:
   `rm -rf tests/Functional/app/var/cache && php -d pcov.enabled=1 vendor/bin/behat --profile=default-coverage features/<area>/<feature>.feature`, then copy `build/logs/behat/clover.xml` away.
   Then the new class, on a clean cache too:
   `rm -rf tests/Functional/app/var/cache && php -d pcov.enabled=1 vendor/bin/phpunit -c phpunit.coverage.xml.dist tests/Functional/<Area>/<Feature>Test.php --coverage-clover <new>.xml`.
   Every `src/` line the old run covered must be covered by the new one. Both runs need the same cache state: on a warm cache, the container compile and the metadata caches (compiler passes, `Configuration`, resource metadata factories, serializer and validator loaders) don't run, so they show up as a false shortfall. List any real shortfall with a reason.
2. **Mutation.** Generate coverage for the new class on a warm cache (`--coverage-xml=<dir>/coverage-xml --log-junit=<dir>/junit.xml`), then
   `php infection.phar --coverage=<dir> --skip-initial-tests --test-framework-options="--testsuite=functional" --filter=<the src files the old run covered, comma-separated> --threads=8`.
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
