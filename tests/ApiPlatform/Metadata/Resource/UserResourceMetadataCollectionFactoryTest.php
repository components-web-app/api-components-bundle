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
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource\UserResourceMetadataCollectionFactory;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;

class UserResourceMetadataCollectionFactoryTest extends TestCase
{
    private const string MANAGE = "(is_granted('ROLE_USER_ADMIN') and is_granted('manage_user', object))";
    private const string SELF = '(user and object.getId() == user.getId())';

    /**
     * @return iterable<string, array{HttpOperation, string}>
     */
    public static function userOperations(): iterable
    {
        yield 'a collection' => [new GetCollection(name: 'op'), "is_granted('ROLE_USER_ADMIN')"];
        yield 'a create' => [new Post(name: 'op'), "is_granted('ROLE_USER_ADMIN')"];
        yield 'a read' => [new Get(name: 'op'), "is_granted('ROLE_USER_ADMIN') or " . self::SELF];
        yield 'a patch' => [new Patch(name: 'op'), self::MANAGE . ' or ' . self::SELF];
        yield 'a put' => [new Put(name: 'op'), self::MANAGE . ' or ' . self::SELF];
        yield 'a delete' => [new Delete(name: 'op'), self::MANAGE];
    }

    #[DataProvider('userOperations')]
    public function test_every_user_operation_needs_the_user_admin_role_with_no_security_of_its_own(HttpOperation $operation, string $security): void
    {
        self::assertSame($security, self::created($operation)->getSecurity());
    }

    #[DataProvider('userOperations')]
    public function test_an_operations_own_security_is_anded_with_the_user_admin_security(HttpOperation $operation, string $security): void
    {
        self::assertSame(\sprintf("(%s) and (is_granted('ROLE_SUPER_ADMIN'))", $security), self::created($operation->withSecurity("is_granted('ROLE_SUPER_ADMIN')"))->getSecurity());
    }

    #[DataProvider('userOperations')]
    public function test_an_operation_that_opts_out_keeps_only_its_own_security(HttpOperation $operation, string $security): void
    {
        self::assertNotSame('own', $security);
        $operation = $operation->withSecurity('own')->withExtraProperties([UserResourceMetadataCollectionFactory::OPT_OUT_EXTRA_PROPERTY => false]);

        self::assertSame('own', self::created($operation)->getSecurity());
    }

    public function test_a_user_operation_of_another_kind_keeps_only_its_own_security(): void
    {
        self::assertSame('own', self::created(new HttpOperation(method: HttpOperation::METHOD_POST, name: 'op', security: 'own'))->getSecurity());
    }

    public function test_the_me_operation_needs_only_a_signed_in_user(): void
    {
        $metadata = self::factory(User::class, new Get(name: 'op', security: 'own'))->create(User::class);

        self::assertSame('is_granted("IS_AUTHENTICATED_FULLY")', $metadata->getOperation('_api_me')->getSecurity());
    }

    public function test_a_resource_that_is_not_a_user_is_left_alone(): void
    {
        $metadata = self::factory(DummyComponent::class, new Delete(name: 'op', security: 'own'))->create(DummyComponent::class);

        self::assertSame('own', $metadata->getOperation('op')->getSecurity());
    }

    private static function created(HttpOperation $operation): HttpOperation
    {
        $created = self::factory(User::class, $operation)->create(User::class)->getOperation('op');
        self::assertInstanceOf(HttpOperation::class, $created);

        return $created;
    }

    private static function factory(string $class, HttpOperation $operation): UserResourceMetadataCollectionFactory
    {
        $operation = $operation->withClass($class)->withShortName('User')->withUriTemplate('/items/{id}');
        $decorated = new class($class, $operation) implements ResourceMetadataCollectionFactoryInterface {
            public function __construct(private readonly string $class, private readonly HttpOperation $operation)
            {
            }

            public function create(string $resourceClass): ResourceMetadataCollection
            {
                return new ResourceMetadataCollection($this->class, [
                    (new ApiResource(shortName: 'User', class: $this->class))->withOperations(new Operations(['op' => $this->operation])),
                ]);
            }
        };

        return new UserResourceMetadataCollectionFactory($decorated);
    }
}
