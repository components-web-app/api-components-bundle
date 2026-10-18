<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\Publishable;

use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableWithSecurityGroups;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

class PublishableStatusCheckerTest extends TestCase
{
    /** @var list<string> */
    private array $checked = [];

    private function checker(bool $granted = true, ?\Throwable $exception = null): PublishableStatusChecker
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturnCallback(function (mixed $attribute) use ($granted, $exception): bool {
            $this->checked[] = (string) $attribute;
            if ($exception) {
                throw $exception;
            }

            return $granted;
        });
        $registry = $this->createStub(ManagerRegistry::class);

        return new PublishableStatusChecker($registry, new PublishableAttributeReader($registry), $authorizationChecker, "is_granted('ROLE_ADMIN')");
    }

    public function test_a_class_that_is_not_publishable_is_checked_against_the_bundle_permission(): void
    {
        self::assertTrue($this->checker()->isGranted(Layout::class));
        self::assertSame(["is_granted('ROLE_ADMIN')"], $this->checked);
    }

    public function test_a_publishable_class_without_its_own_permission_is_checked_against_the_bundle_permission(): void
    {
        self::assertFalse($this->checker(false)->isGranted(DummyPublishableComponent::class));
        self::assertSame(["is_granted('ROLE_ADMIN')"], $this->checked);
    }

    public function test_a_publishable_class_with_its_own_permission_is_checked_against_it(): void
    {
        $this->checker()->isGranted(new DummyPublishableWithSecurityGroups());
        self::assertSame(["is_granted('ROLE_USER')"], $this->checked);
    }

    public function test_no_token_is_not_granted(): void
    {
        self::assertFalse($this->checker(true, new AuthenticationCredentialsNotFoundException())->isGranted(Layout::class));
    }

    public function test_the_permission_is_passed_as_an_expression(): void
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects(self::once())->method('isGranted')->with(self::isInstanceOf(Expression::class))->willReturn(true);
        $registry = $this->createStub(ManagerRegistry::class);

        self::assertTrue((new PublishableStatusChecker($registry, new PublishableAttributeReader($registry), $authorizationChecker, "is_granted('ROLE_ADMIN')"))->isGranted(Layout::class));
    }
}
