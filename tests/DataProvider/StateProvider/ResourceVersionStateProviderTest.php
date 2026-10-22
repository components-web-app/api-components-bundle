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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\ResourceVersionStateProvider;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Exception\ResourceVersionConflictException;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Symfony\Component\HttpFoundation\Request;

final class ResourceVersionStateProviderTest extends TestCase
{
    private DummyPublishableComponent $component;

    private UuidInterface $id;

    protected function setUp(): void
    {
        $this->id = Uuid::uuid4();
        $this->component = new DummyPublishableComponent();
        (new \ReflectionProperty(AbstractComponent::class, 'id'))->setValue($this->component, $this->id);
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
    public function test_a_matching_if_match_returns_the_resource(Operation $operation, string $ifMatch): void
    {
        self::assertSame($this->component, $this->provide($operation, $ifMatch));
    }

    public function test_a_matching_if_match_for_the_row_addressed_by_a_uuid_object_returns_the_resource(): void
    {
        self::assertSame($this->component, $this->provider($this->component)->provide(new Patch(), ['id' => Uuid::fromString($this->id->toString())], ['request' => $this->request('"3"')]));
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
     * @return iterable<string, array{string}>
     */
    public static function anyIfMatch(): iterable
    {
        yield 'the version of the row read' => ['"3"'];
        yield 'any version' => ['*'];
    }

    #[DataProvider('anyIfMatch')]
    public function test_an_if_match_for_a_row_that_was_read_in_place_of_the_row_addressed_is_a_conflict(string $ifMatch): void
    {
        $this->expectException(ResourceVersionConflictException::class);

        $this->provider($this->component)->provide(new Patch(), ['id' => Uuid::uuid4()->toString()], ['request' => $this->request($ifMatch)]);
    }

    public function test_a_row_read_in_place_of_the_row_addressed_without_if_match_is_returned(): void
    {
        self::assertSame($this->component, $this->provider($this->component)->provide(new Patch(), ['id' => Uuid::uuid4()->toString()], ['request' => $this->request(null)]));
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
        self::assertSame($this->component, $this->provider($this->component)->provide($operation, ['id' => Uuid::uuid4()->toString()], ['request' => $this->request($ifMatch)]));
    }

    public function test_the_version_is_not_checked_without_a_request(): void
    {
        self::assertSame($this->component, $this->provider($this->component)->provide(new Patch(), ['id' => Uuid::uuid4()->toString()]));
    }

    public function test_a_resource_that_is_not_versioned_is_not_checked(): void
    {
        $group = new ComponentGroup();

        self::assertSame($group, $this->provider($group)->provide(new Patch(), [], ['request' => $this->request('"99"')]));
    }

    private function provide(Operation $operation, ?string $ifMatch): object|array|null
    {
        return $this->provider($this->component)->provide($operation, ['id' => $this->id->toString()], ['request' => $this->request($ifMatch)]);
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

        return new ResourceVersionStateProvider($decorated);
    }
}
