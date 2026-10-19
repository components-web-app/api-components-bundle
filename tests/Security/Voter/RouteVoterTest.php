<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Security\Voter;

use ApiPlatform\Metadata\ResourceAccessCheckerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Security\Voter\ComponentVoter;
use Silverback\ApiComponentsBundle\Security\Voter\RouteVoter;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class RouteVoterTest extends TestCase
{
    private const string USER = "is_granted('ROLE_USER')";

    private const string ADMIN = "is_granted('ROLE_ADMIN')";

    public function test_a_vote_on_another_attribute_or_subject_abstains(): void
    {
        $voter = $this->voter(null, []);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote(new NullToken(), $this->route('/page'), [ComponentVoter::READ_COMPONENT]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote(new NullToken(), new DummyComponent(), [RouteVoter::READ_ROUTE]));
    }

    public function test_a_live_route_is_granted_when_no_route_security_is_configured(): void
    {
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote(null, [], $this->route('/page')));
    }

    public function test_a_route_which_is_not_live_is_denied_to_a_caller_without_the_publication_permission(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(null, [], $this->route('/page', null)));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(null, [], $this->route('/page', new \DateTimeImmutable('2999-01-01T00:00:00+00:00'))));
    }

    public function test_a_route_which_is_not_live_is_granted_to_a_caller_with_the_publication_permission(): void
    {
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote(null, [self::ADMIN], $this->route('/page', null)));
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function routeSecurityPaths(): iterable
    {
        yield 'the path of the rule itself' => ['/user-area*', '/user-area', VoterInterface::ACCESS_DENIED];
        yield 'a path below the rule' => ['/user-area*', '/user-area/my-page', VoterInterface::ACCESS_DENIED];
        yield 'a path the rule\'s characters would match as a regular expression' => ['/user-area*', '/user-are', VoterInterface::ACCESS_GRANTED];
        yield 'a dot in the rule matching only a dot' => ['/v1.0/*', '/v1x0/page', VoterInterface::ACCESS_GRANTED];
        yield 'a dot in the rule matching a dot' => ['/v1.0/*', '/v1.0/page', VoterInterface::ACCESS_DENIED];
        yield 'the delimiter in the rule' => ['/a#b*', '/a#b/page', VoterInterface::ACCESS_DENIED];
        yield 'a path the rule does not match' => ['/user-area*', '/public', VoterInterface::ACCESS_GRANTED];
        yield 'a path holding the rule further in' => ['/user-area*', '/other/user-area/my-page', VoterInterface::ACCESS_GRANTED];
        yield 'a path holding a rule with a leading wildcard further in' => ['*/user-area*', '/other/user-area/my-page', VoterInterface::ACCESS_DENIED];
        yield 'a path below a rule with no wildcard' => ['/user-area', '/user-area/my-page', VoterInterface::ACCESS_GRANTED];
        yield 'the path of a rule with no wildcard' => ['/user-area', '/user-area', VoterInterface::ACCESS_DENIED];
    }

    #[DataProvider('routeSecurityPaths')]
    public function test_a_route_security_rule_matches_the_whole_path_literally_with_an_asterisk_as_its_only_wildcard(string $rule, string $path, int $expected): void
    {
        self::assertSame($expected, $this->vote([['route' => $rule, 'security' => self::USER]], [], $this->route($path)));
    }

    public function test_a_route_security_rule_the_caller_satisfies_grants_the_route(): void
    {
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote([['route' => '/user-area*', 'security' => self::USER]], [self::USER], $this->route('/user-area/my-page')));
    }

    public function test_every_route_security_rule_is_applied(): void
    {
        $config = [['route' => '/first*', 'security' => self::USER], ['route' => '/second*', 'security' => self::ADMIN]];

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($config, [self::USER], $this->route('/second/page')));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($config, [self::USER], $this->route('/first/page')));
    }

    /**
     * @param list<string> $grantedExpressions
     */
    private function vote(?array $config, array $grantedExpressions, Route $route): int
    {
        return $this->voter($config, $grantedExpressions)->vote(new NullToken(), $route, [RouteVoter::READ_ROUTE]);
    }

    /**
     * @param list<string> $grantedExpressions
     */
    private function voter(?array $config, array $grantedExpressions): RouteVoter
    {
        $accessChecker = $this->createStub(ResourceAccessCheckerInterface::class);
        $accessChecker->method('isGranted')->willReturnCallback(static fn (string $class, string $expression): bool => \in_array($expression, $grantedExpressions, true));

        return new RouteVoter($config, $accessChecker);
    }

    private function route(string $path, ?\DateTimeImmutable $liveAt = new \DateTimeImmutable('2000-01-01T00:00:00+00:00')): Route
    {
        return (new Route())->setPath($path)->setName($path)->setLiveAt($liveAt);
    }
}
