<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\ApiPlatform\Metadata\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource\ContentWriteSecurityResourceMetadataCollectionFactory;
use Silverback\ApiComponentsBundle\ApiResource\ComponentGroupOrder;
use Silverback\ApiComponentsBundle\ApiResource\OrphanedResourceReport;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Component\Form;
use Silverback\ApiComponentsBundle\Entity\Core\CollaborationState;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Entity\Core\SiteConfigParameter;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableWithSecurityGroups;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;

final class ContentWriteSecurityResourceMetadataCollectionFactoryTest extends TestCase
{
    private const string PERMISSION = "is_granted('ROLE_ADMIN')";

    #[DataProvider('contentClasses')]
    public function test_every_write_operation_of_a_content_class_requires_the_permission_and_reads_are_unchanged(string $resourceClass): void
    {
        $collection = $this->factory(self::collection($resourceClass, self::operations()))->create($resourceClass);

        self::assertSame([
            'get' => null,
            'get_collection' => null,
            'post' => self::PERMISSION,
            'put' => self::PERMISSION,
            'patch' => self::PERMISSION,
            'delete' => self::PERMISSION,
        ], self::securities($collection));
    }

    public static function contentClasses(): iterable
    {
        yield 'a page' => [Page::class];
        yield 'a layout' => [Layout::class];
        yield 'a route' => [Route::class];
        yield 'a component group' => [ComponentGroup::class];
        yield 'a component group order' => [ComponentGroupOrder::class];
        yield 'a component position' => [ComponentPosition::class];
        yield 'a component' => [DummyComponent::class];
        yield 'a bundle component' => [Form::class];
        yield 'page data' => [PageData::class];
        yield 'a publishable component using the configured permission' => [DummyPublishableComponent::class];
    }

    #[DataProvider('otherClasses')]
    public function test_a_class_that_is_not_content_is_unchanged(string $resourceClass): void
    {
        $collection = $this->factory(self::collection($resourceClass, self::operations()))->create($resourceClass);

        self::assertSame([
            'get' => null,
            'get_collection' => null,
            'post' => null,
            'put' => null,
            'patch' => null,
            'delete' => null,
        ], self::securities($collection));
    }

    public static function otherClasses(): iterable
    {
        yield 'a user' => [User::class];
        yield 'a collaboration state' => [CollaborationState::class];
        yield 'a site config parameter' => [SiteConfigParameter::class];
        yield 'an orphaned resource report' => [OrphanedResourceReport::class];
    }

    public function test_an_operation_with_its_own_security_requires_the_permission_as_well(): void
    {
        $collection = $this->factory(self::collection(Route::class, [
            'patch' => new Patch(security: "is_granted('read_route', object)"),
            'get' => new Get(security: "is_granted('read_route', object)"),
        ]))->create(Route::class);

        self::assertSame([
            'patch' => "(is_granted('ROLE_ADMIN')) and (is_granted('read_route', object))",
            'get' => "is_granted('read_route', object)",
        ], self::securities($collection));
    }

    public function test_a_publishable_class_with_its_own_permission_requires_that_permission_instead(): void
    {
        $collection = $this->factory(self::collection(DummyPublishableWithSecurityGroups::class, [
            'patch' => new Patch(),
            'delete' => new Delete(security: "is_granted('something', object)"),
        ]))->create(DummyPublishableWithSecurityGroups::class);

        self::assertSame([
            'patch' => "is_granted('ROLE_USER')",
            'delete' => "(is_granted('ROLE_USER')) and (is_granted('something', object))",
        ], self::securities($collection));
    }

    public function test_an_empty_security_is_replaced_by_the_permission(): void
    {
        $collection = $this->factory(self::collection(Page::class, ['patch' => new Patch(security: '')]))->create(Page::class);

        self::assertSame(['patch' => self::PERMISSION], self::securities($collection));
    }

    public function test_an_operation_that_opts_out_is_left_alone(): void
    {
        $collection = $this->factory(self::collection(Form::class, [
            'submit' => new Post(extraProperties: [ContentWriteSecurityResourceMetadataCollectionFactory::OPT_OUT_EXTRA_PROPERTY => false]),
            'secured_submit' => new Patch(security: "is_granted('ROLE_USER')", extraProperties: [ContentWriteSecurityResourceMetadataCollectionFactory::OPT_OUT_EXTRA_PROPERTY => false]),
            'opted_in' => new Patch(extraProperties: [ContentWriteSecurityResourceMetadataCollectionFactory::OPT_OUT_EXTRA_PROPERTY => true]),
            'other_extra_property' => new Delete(extraProperties: ['something' => false]),
        ]))->create(Form::class);

        self::assertSame([
            'submit' => null,
            'secured_submit' => "is_granted('ROLE_USER')",
            'opted_in' => self::PERMISSION,
            'other_extra_property' => self::PERMISSION,
        ], self::securities($collection));
    }

    public function test_every_resource_of_the_class_is_changed_and_one_without_operations_is_kept(): void
    {
        $collection = new ResourceMetadataCollection(Page::class, [
            (new ApiResource())->withOperations(new Operations(['first' => new Patch()])),
            new ApiResource(),
            (new ApiResource())->withOperations(new Operations(['second' => new Delete()])),
        ]);

        $created = $this->factory($collection)->create(Page::class);

        self::assertCount(3, $created);
        self::assertSame(['first' => self::PERMISSION], self::securities(new ResourceMetadataCollection(Page::class, [$created[0]])));
        self::assertNull($created[1]->getOperations());
        self::assertSame(['second' => self::PERMISSION], self::securities(new ResourceMetadataCollection(Page::class, [$created[2]])));
    }

    /**
     * @return array<string, \ApiPlatform\Metadata\HttpOperation>
     */
    private static function operations(): array
    {
        return [
            'get' => new Get(),
            'get_collection' => new GetCollection(),
            'post' => new Post(),
            'put' => new Put(),
            'patch' => new Patch(),
            'delete' => new Delete(),
        ];
    }

    /**
     * @param array<string, \ApiPlatform\Metadata\HttpOperation> $operations
     */
    private static function collection(string $resourceClass, array $operations): ResourceMetadataCollection
    {
        return new ResourceMetadataCollection($resourceClass, [(new ApiResource())->withOperations(new Operations($operations))]);
    }

    private function factory(ResourceMetadataCollection $collection): ContentWriteSecurityResourceMetadataCollectionFactory
    {
        $decorated = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $decorated->expects(self::once())->method('create')->willReturn($collection);

        return new ContentWriteSecurityResourceMetadataCollectionFactory($decorated, new PublishableAttributeReader($this->createStub(ManagerRegistry::class)), self::PERMISSION);
    }

    /**
     * @return array<string, ?string>
     */
    private static function securities(ResourceMetadataCollection $collection): array
    {
        $securities = [];
        foreach ($collection as $resource) {
            foreach ($resource->getOperations() ?? [] as $key => $operation) {
                $securities[$key] = $operation->getSecurity();
            }
        }

        return $securities;
    }
}
