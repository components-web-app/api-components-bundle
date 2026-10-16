<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\ComponentPosition;

use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Exception\ItemNotFoundException;
use ApiPlatform\Metadata\IriConverterInterface;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentPositionIriResolver;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;

class ComponentPositionIriResolverTest extends TestCase
{
    private ComponentPosition $position;

    protected function setUp(): void
    {
        $this->position = new ComponentPosition();
    }

    public function test_a_position_iri_resolves_to_the_position(): void
    {
        self::assertSame($this->position, $this->resolver()->resolve('/position'));
    }

    public function test_no_iri_resolves_to_nothing_without_asking_the_converter(): void
    {
        $iriConverter = $this->createMock(IriConverterInterface::class);
        $iriConverter->expects(self::never())->method('getResourceFromIri');
        $resolver = new ComponentPositionIriResolver($iriConverter);

        self::assertNull($resolver->resolve(null));
        self::assertNull($resolver->resolve(''));
    }

    public function test_an_iri_of_another_resource_resolves_to_nothing(): void
    {
        self::assertNull($this->resolver()->resolve('/component'));
    }

    public function test_an_iri_that_does_not_resolve_resolves_to_nothing(): void
    {
        self::assertNull($this->resolver()->resolve('/missing'));
        self::assertNull($this->resolver()->resolve('/invalid'));
    }

    public function test_only_an_item_that_is_not_found_is_missing(): void
    {
        $resolver = $this->resolver();

        self::assertTrue($resolver->isMissing('/missing'));
        self::assertFalse($resolver->isMissing('/invalid'));
        self::assertFalse($resolver->isMissing('/position'));
    }

    private function resolver(): ComponentPositionIriResolver
    {
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getResourceFromIri')->willReturnCallback(fn (string $iri): object => match ($iri) {
            '/position' => $this->position,
            '/component' => new DummyComponent(),
            '/missing' => throw new ItemNotFoundException('Item not found'),
            default => throw new InvalidArgumentException('No route matches'),
        });

        return new ComponentPositionIriResolver($iriConverter);
    }
}
