<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\Realtime;

use ApiPlatform\Metadata\IriConverterInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Helper\Realtime\LastWriteResolver;
use Silverback\ApiComponentsBundle\Helper\Realtime\RealtimeUserResolver;
use Silverback\ApiComponentsBundle\Mercure\AnonymousResourceReader;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class LastWriteResolverTest extends TestCase
{
    private const string CLIENT_ID = '0b9f7a3e-2c4d-4e5f-8a6b-7c8d9e0f1a2b';
    private const string NOW = '2026-10-21T10:00:00.250+00:00';
    private const string AT = '2026-10-21T10:00:00.250+00:00';

    public function test_every_resource_of_one_write_is_attributed_to_the_time_of_the_first_one_attributed(): void
    {
        $clock = new MockClock(self::NOW);
        $requestStack = new RequestStack();
        $requestStack->push($this->request('PATCH', self::CLIENT_ID));
        $resolver = new LastWriteResolver($requestStack, $this->userResolver(true), $clock);

        $first = $resolver->resolve();
        $clock->sleep(1.5);

        self::assertSame(self::AT, $first['at'] ?? null);
        self::assertSame($first, $resolver->resolve());
    }

    public function test_each_write_is_attributed_to_its_own_time_on_the_server_with_milliseconds(): void
    {
        $clock = new MockClock(new \DateTimeImmutable('2026-10-21T12:00:00.123456+02:00'));
        $requestStack = new RequestStack();
        $resolver = new LastWriteResolver($requestStack, $this->userResolver(true), $clock);

        $requestStack->push($this->request('PATCH', self::CLIENT_ID));
        $first = $resolver->resolve();
        $requestStack->pop();
        $clock->sleep(0.25);
        $requestStack->push($this->request('PATCH', self::CLIENT_ID));

        self::assertSame('2026-10-21T12:00:00.123+02:00', $first['at'] ?? null);
        self::assertSame('2026-10-21T12:00:00.373+02:00', $resolver->resolve()['at'] ?? null);
    }

    public function test_a_write_that_is_not_attributed_records_no_time(): void
    {
        $request = $this->request('PATCH', 'tab-1');

        $this->resolver($request)->resolve();

        self::assertSame([], $request->attributes->all());
    }

    public function test_the_header_is_named_x_cwa_client_id(): void
    {
        self::assertSame('X-CWA-Client-Id', LastWriteResolver::HEADER);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeMethods(): iterable
    {
        yield 'POST' => ['POST'];
        yield 'PUT' => ['PUT'];
        yield 'PATCH' => ['PATCH'];
        yield 'DELETE' => ['DELETE'];
    }

    #[DataProvider('unsafeMethods')]
    public function test_a_write_carrying_a_client_id_is_attributed_to_that_client_and_the_signed_in_user(string $method): void
    {
        self::assertSame(['clientId' => self::CLIENT_ID, 'userId' => '/users/1', 'at' => self::AT], $this->resolver($this->request($method, self::CLIENT_ID))->resolve());
    }

    public function test_the_client_id_is_kept_as_sent(): void
    {
        self::assertSame(['clientId' => strtoupper(self::CLIENT_ID), 'userId' => '/users/1', 'at' => self::AT], $this->resolver($this->request('PATCH', strtoupper(self::CLIENT_ID)))->resolve());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function safeMethods(): iterable
    {
        yield 'GET' => ['GET'];
        yield 'HEAD' => ['HEAD'];
        yield 'OPTIONS' => ['OPTIONS'];
    }

    #[DataProvider('safeMethods')]
    public function test_a_read_is_never_attributed_so_no_shared_cache_can_store_it(string $method): void
    {
        self::assertNull($this->resolver($this->request($method, self::CLIENT_ID))->resolve());
    }

    public function test_a_write_without_the_header_is_not_attributed(): void
    {
        self::assertNull($this->resolver(Request::create('/component/dummy_components/1', 'PATCH'))->resolve());
    }

    public function test_a_client_id_that_is_not_a_uuid_is_ignored(): void
    {
        self::assertNull($this->resolver($this->request('PATCH', 'tab-1'))->resolve());
    }

    public function test_the_anonymous_read_for_the_public_update_is_never_attributed_even_if_the_header_reached_it(): void
    {
        $request = $this->request('PATCH', self::CLIENT_ID);
        $request->attributes->set(AnonymousResourceReader::REQUEST_ATTRIBUTE, true);

        self::assertNull($this->resolver($request)->resolve());
    }

    public function test_only_the_current_request_is_read_never_the_main_request(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push($this->request('PATCH', self::CLIENT_ID));
        $requestStack->push(Request::create('/component/dummy_components/1', 'POST'));

        self::assertNull((new LastWriteResolver($requestStack, $this->userResolver(true), new MockClock(self::NOW)))->resolve());
    }

    public function test_nothing_is_attributed_outside_a_request(): void
    {
        self::assertNull((new LastWriteResolver(new RequestStack(), $this->userResolver(true), new MockClock(self::NOW)))->resolve());
    }

    public function test_an_anonymous_write_is_not_attributed(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push($this->request('POST', self::CLIENT_ID));

        self::assertNull((new LastWriteResolver($requestStack, $this->userResolver(false), new MockClock(self::NOW)))->resolve());
    }

    private function request(string $method, string $clientId): Request
    {
        $request = Request::create('/component/dummy_components/1', $method);
        $request->headers->set(LastWriteResolver::HEADER, $clientId);

        return $request;
    }

    private function resolver(Request $request): LastWriteResolver
    {
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new LastWriteResolver($requestStack, $this->userResolver(true), new MockClock(self::NOW));
    }

    private function userResolver(bool $signedIn): RealtimeUserResolver
    {
        $tokenStorage = new TokenStorage();
        if ($signedIn) {
            $user = (new User())->setUsername('editor');
            $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', ['ROLE_ADMIN']));
        }
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturn('/users/1');

        return new RealtimeUserResolver($tokenStorage, $iriConverter);
    }
}
