<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\ComponentMove;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\Metadata\ResourceAccessCheckerInterface;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Helper\ComponentMove\ComponentMoveAccessChecker;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ComponentMoveAccessCheckerTest extends TestCase
{
    /** @var list<array{string, string, array<string, mixed>}> */
    private array $checked = [];

    /** @var list<string> */
    private array $denied = [];

    public function test_the_security_of_the_item_operation_with_the_method_is_checked_with_the_object(): void
    {
        $object = new ComponentPosition();

        $this->checker()->denyUnlessGranted(ComponentPosition::class, 'PATCH', $object);

        self::assertSame([[ComponentPosition::class, 'patch', ['object' => $object, 'previous_object' => $object]]], $this->checked);
    }

    public function test_a_create_checks_the_post_with_no_uri_variables(): void
    {
        $this->checker()->denyUnlessGranted(ComponentPosition::class, 'POST', null);

        self::assertSame([[ComponentPosition::class, 'post', ['object' => null, 'previous_object' => null]]], $this->checked);
    }

    public function test_a_delete_checks_the_delete_operation(): void
    {
        $object = new ComponentPosition();

        $this->checker()->denyUnlessGranted(ComponentPosition::class, 'DELETE', $object);

        self::assertSame('delete', $this->checked[0][1]);
    }

    public function test_the_post_denormalize_security_is_checked_with_the_new_and_previous_objects(): void
    {
        $object = new ComponentPosition();
        $previous = new ComponentPosition();

        $this->checker()->denyUnlessGrantedPostDenormalize(ComponentPosition::class, 'PATCH', $object, $previous);

        self::assertSame([[ComponentPosition::class, 'patch_post_denormalize', ['object' => $object, 'previous_object' => $previous]]], $this->checked);
    }

    public function test_an_operation_with_no_security_is_granted_without_a_check(): void
    {
        $this->checker()->denyUnlessGrantedPostDenormalize(ComponentPosition::class, 'POST', new ComponentPosition(), null);

        self::assertSame([], $this->checked);
    }

    public function test_a_refused_expression_denies_access(): void
    {
        $this->denied = ['patch'];

        $this->expectException(AccessDeniedException::class);

        $this->checker()->denyUnlessGranted(ComponentPosition::class, 'PATCH', new ComponentPosition());
    }

    public function test_a_resource_with_no_operation_for_the_method_denies_access(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage(ComponentPosition::class . ' has no PUT operation.');

        $this->checker()->denyUnlessGranted(ComponentPosition::class, 'PUT', new ComponentPosition());
    }

    private function checker(): ComponentMoveAccessChecker
    {
        $operations = new Operations([
            'collection' => new GetCollection(uriTemplate: '/positions', security: 'collection'),
            'post_item' => new Post(uriTemplate: '/positions/{id}/upload', uriVariables: ['id'], security: 'upload'),
            'item' => new Get(uriTemplate: '/positions/{id}', uriVariables: ['id'], security: 'get'),
            'post' => new Post(uriTemplate: '/positions', security: 'post'),
            'patch' => new Patch(uriTemplate: '/positions/{id}', uriVariables: ['id'], security: 'patch', securityPostDenormalize: 'patch_post_denormalize'),
            'delete' => new Delete(uriTemplate: '/positions/{id}', uriVariables: ['id'], security: 'delete'),
        ]);
        $factory = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $factory->method('create')->willReturn(new ResourceMetadataCollection(ComponentPosition::class, [
            new ApiResource(class: ComponentPosition::class),
            (new ApiResource(class: ComponentPosition::class))->withOperations($operations),
        ]));
        $accessChecker = $this->createStub(ResourceAccessCheckerInterface::class);
        $accessChecker->method('isGranted')->willReturnCallback(function (string $class, string $expression, array $variables): bool {
            $this->checked[] = [$class, $expression, $variables];

            return !\in_array($expression, $this->denied, true);
        });

        return new ComponentMoveAccessChecker($factory, $accessChecker);
    }
}
