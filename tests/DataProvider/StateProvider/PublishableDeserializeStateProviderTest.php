<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\DataProvider\StateProvider;

use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\PublishableDeserializeStateProvider;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PublishableDeserializeStateProviderTest extends TestCase
{
    /**
     * @return iterable<string, array{Operation}>
     */
    public static function updates(): iterable
    {
        yield 'PATCH' => [new Patch()];
        yield 'PUT' => [new Put()];
    }

    #[DataProvider('updates')]
    public function test_changing_the_publication_date_of_the_published_resource_is_unprocessable(Operation $operation): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('You cannot change the publication date of a published resource.');

        $this->provide(new DummyPublishableComponent(), $operation, true, new \DateTime('2020-01-01'), new \DateTime('2021-01-01'));
    }

    public function test_keeping_the_publication_date_of_the_published_resource_is_allowed(): void
    {
        $data = new DummyPublishableComponent();
        $date = new \DateTime('2020-01-01');

        self::assertSame($data, $this->provide($data, new Patch(), true, $date, $date));
    }

    /**
     * @return iterable<string, array{mixed, Operation, bool}>
     */
    public static function unchecked(): iterable
    {
        yield 'a draft request' => [new DummyPublishableComponent(), new Patch(), false];
        yield 'a create' => [new DummyPublishableComponent(), new Post(), true];
        yield 'a resource that is not publishable' => [new DummyComponent(), new Patch(), true];
        yield 'nothing deserialized' => [null, new Patch(), true];
        yield 'an operation that is not HTTP' => [new DummyPublishableComponent(), new Mutation(), true];
    }

    #[DataProvider('unchecked')]
    public function test_the_publication_date_is_not_compared_for(mixed $data, Operation $operation, bool $forPublished): void
    {
        self::assertSame($data, $this->provide($data, $operation, $forPublished, new \DateTime('2020-01-01'), new \DateTime('2021-01-01')));
    }

    public function test_nothing_is_compared_without_a_request_or_previous_data(): void
    {
        $data = new DummyPublishableComponent();
        $inner = $this->createStub(ProviderInterface::class);
        $inner->method('provide')->willReturn($data);
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::never())->method('getManagerForClass');
        $provider = new PublishableDeserializeStateProvider($inner, $this->statusChecker($registry, true), $registry);

        self::assertSame($data, $provider->provide(new Patch()));
        self::assertSame($data, $provider->provide(new Patch(), [], ['request' => new Request()]));
    }

    private function provide(mixed $data, Operation $operation, bool $forPublished, \DateTime $previousDate, \DateTime $date): mixed
    {
        $previous = new DummyPublishableComponent();
        $request = new Request();
        $request->attributes->set('previous_data', $previous);
        $classMetadata = $this->createStub(ClassMetadata::class);
        $classMetadata->method('getFieldValue')->willReturnCallback(static fn (object $object): \DateTime => $object === $previous ? $previousDate : $date);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn($classMetadata);
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);
        $inner = $this->createStub(ProviderInterface::class);
        $inner->method('provide')->willReturn($data);

        return (new PublishableDeserializeStateProvider($inner, $this->statusChecker($registry, $forPublished), $registry))->provide($operation, [], ['request' => $request]);
    }

    private function statusChecker(ManagerRegistry $registry, bool $forPublished): PublishableStatusChecker
    {
        $statusChecker = $this->createStub(PublishableStatusChecker::class);
        $statusChecker->method('getAttributeReader')->willReturn(new PublishableAttributeReader($registry));
        $statusChecker->method('isRequestForPublished')->willReturn($forPublished);

        return $statusChecker;
    }
}
