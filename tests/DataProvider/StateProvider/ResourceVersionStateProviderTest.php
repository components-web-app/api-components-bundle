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

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProviderInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\OptimisticLockException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\ResourceVersionStateProvider;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Utility\VersionedInterface;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\ResourceVersionListener;
use Silverback\ApiComponentsBundle\Exception\ResourceVersionConflictException;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Helper\Publishable\PublishableDatabaseTrait;
use Symfony\Component\HttpFoundation\Request;

final class ResourceVersionStateProviderTest extends TestCase
{
    use PublishableDatabaseTrait;

    private ResourceVersionListener $listener;

    private DummyPublishableComponent $component;

    protected function setUp(): void
    {
        $this->setUpPublishableDatabase();
        $this->listener = new ResourceVersionListener(new PublishableAttributeReader($this->registry));
        $this->entityManager->getEventManager()->addEventListener(Events::onFlush, $this->listener);
        $this->component = new DummyPublishableComponent();
        $this->entityManager->persist($this->component);
        $this->entityManager->flush();
        (new \ReflectionProperty(AbstractComponent::class, 'resourceVersion'))->setValue($this->component, 3);
    }

    /**
     * @return iterable<string, array{Operation, string}>
     */
    public static function matching(): iterable
    {
        yield 'a patch' => [new Patch(), '"3"'];
        yield 'a put' => [new Put(), '"3"'];
        yield 'a delete' => [new Delete(), '"3"'];
        yield 'any version' => [new Patch(), '*'];
        yield 'one of a list' => [new Patch(), '"2", "3"'];
        yield 'one of a list without spaces' => [new Patch(), '"2","3"'];
    }

    #[DataProvider('matching')]
    public function test_a_matching_if_match_returns_the_resource_and_expects_its_version_at_commit(Operation $operation, string $ifMatch): void
    {
        $result = $this->provide($operation, $ifMatch);

        self::assertSame($this->component, $result);
        $this->expectException(OptimisticLockException::class);
        $this->editAfterAnotherWrite();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function stale(): iterable
    {
        yield 'an older version' => ['"2"'];
        yield 'a weak entity tag' => ['W/"3"'];
        yield 'an unquoted version' => ['3'];
        yield 'an empty header' => [''];
        yield 'a list without it' => ['"1", "2"'];
    }

    #[DataProvider('stale')]
    public function test_an_if_match_that_does_not_match_is_a_conflict(string $ifMatch): void
    {
        $this->expectException(ResourceVersionConflictException::class);

        $this->provide(new Patch(), $ifMatch);
    }

    /**
     * @return iterable<string, array{Operation, ?string}>
     */
    public static function unchecked(): iterable
    {
        yield 'a read' => [new Get(), '"2"'];
        yield 'a create' => [new Post(), '"2"'];
        yield 'an operation that is not HTTP' => [new Mutation(name: 'update'), '"2"'];
        yield 'no if-match' => [new Patch(), null];
    }

    #[DataProvider('unchecked')]
    public function test_the_version_is_not_checked_for(Operation $operation, ?string $ifMatch): void
    {
        self::assertSame($this->component, $this->provide($operation, $ifMatch));

        $this->editAfterAnotherWrite();
        self::assertSame(6, $this->component->getResourceVersion());
    }

    public function test_the_version_is_not_checked_without_a_request(): void
    {
        self::assertSame($this->component, $this->provider($this->component)->provide(new Patch()));

        $this->editAfterAnotherWrite();
        self::assertSame(6, $this->component->getResourceVersion());
    }

    public function test_a_resource_that_is_not_versioned_is_not_checked(): void
    {
        $group = new ComponentGroup();

        self::assertSame($group, $this->provider($group)->provide(new Patch(), [], ['request' => $this->request('"99"')]));
    }

    private function provide(Operation $operation, ?string $ifMatch): object|array|null
    {
        return $this->provider($this->component)->provide($operation, [], ['request' => $this->request($ifMatch)]);
    }

    private function request(?string $ifMatch): Request
    {
        $request = new Request();
        if (null !== $ifMatch) {
            $request->headers->set('If-Match', $ifMatch);
        }

        return $request;
    }

    private function provider(object $data): ResourceVersionStateProvider
    {
        $decorated = $this->createStub(ProviderInterface::class);
        $decorated->method('provide')->willReturn($data);

        return new ResourceVersionStateProvider($decorated, $this->listener);
    }

    private function editAfterAnotherWrite(): void
    {
        $column = $this->entityManager->getClassMetadata(AbstractComponent::class)->getColumnName(VersionedInterface::VERSION_FIELD);
        $this->entityManager->getConnection()->executeStatement(\sprintf('UPDATE _acb_abstract_component SET %s = 5', $column));
        $this->component->reference = 'edited';
        $this->entityManager->flush();
    }
}
