<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\RamseyUuid\UuidUriVariableTransformer;

use ApiPlatform\Metadata\Exception\InvalidUriVariableException;
use ApiPlatform\Metadata\Get;
use ApiPlatform\RamseyUuid\UriVariableTransformer\UuidUriVariableTransformer as ApiPlatformUuidUriVariableTransformer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Silverback\ApiComponentsBundle\Entity\Component\Form;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\RamseyUuid\UuidUriVariableTransformer\UuidUriVariableTransformer;

class UuidUriVariableTransformerTest extends TestCase
{
    public function test_a_uuid_is_transformed(): void
    {
        $uuid = Uuid::uuid4();

        self::assertEquals($uuid, self::transformer()->transform($uuid->toString(), [UuidInterface::class], ['operation' => new Get(class: Route::class)]));
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function classesWhoseProviderResolvesAnyIdentifier(): iterable
    {
        yield 'a route, by path' => [Route::class];
        yield 'a component group, by reference' => [ComponentGroup::class];
        yield 'a form, by name' => [Form::class];
    }

    #[DataProvider('classesWhoseProviderResolvesAnyIdentifier')]
    public function test_an_identifier_that_is_not_a_uuid_is_kept_for(string $class): void
    {
        self::assertSame('not-a-uuid', self::transformer()->transform('not-a-uuid', [UuidInterface::class], ['operation' => new Get(class: $class)]));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function contextsOfAnyOtherResource(): iterable
    {
        yield 'another resource' => [['operation' => new Get(class: Page::class)]];
        yield 'no operation' => [[]];
    }

    /**
     * @param array<string, mixed> $context
     */
    #[DataProvider('contextsOfAnyOtherResource')]
    public function test_an_identifier_that_is_not_a_uuid_is_refused_for(array $context): void
    {
        $this->expectException(InvalidUriVariableException::class);

        self::transformer()->transform('not-a-uuid', [UuidInterface::class], $context);
    }

    public function test_it_supports_what_api_platforms_transformer_supports(): void
    {
        self::assertTrue(self::transformer()->supportsTransformation('not-a-uuid', [UuidInterface::class]));
        self::assertFalse(self::transformer()->supportsTransformation('not-a-uuid', ['string']));
    }

    private static function transformer(): UuidUriVariableTransformer
    {
        return new UuidUriVariableTransformer(new ApiPlatformUuidUriVariableTransformer());
    }
}
