<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Serializer\Normalizer;

use ApiPlatform\Metadata\IriConverterInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DataProvider\PageDataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\ComponentPositionNormalizer;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadataProvider;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class ComponentPositionNormalizerTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function requests(): iterable
    {
        yield 'a sortValue' => [['sortValue' => 3, 'after' => '/a'], true];
        yield 'a null sortValue' => [['sortValue' => null], true];
        yield 'no sortValue' => [['after' => '/a'], false];
        yield 'an IRI' => ['/_/component_positions/1', false];
    }

    #[DataProvider('requests')]
    public function test_a_sort_value_in_the_request_is_recorded_on_the_position(mixed $data, bool $sent): void
    {
        $position = new ComponentPosition();
        $denormalizer = $this->createMock(DenormalizerInterface::class);
        $denormalizer->expects(self::once())->method('denormalize')->willReturnCallback(static function (mixed $given, string $type, ?string $format, array $context) use ($data, $position): ComponentPosition {
            self::assertSame($data, $given);
            self::assertTrue($context['COMPONENT_POSITION_NORMALIZER_ALREADY_CALLED']);

            return $position;
        });
        $normalizer = new ComponentPositionNormalizer(
            $this->createStub(PageDataProvider::class),
            new RequestStack(),
            $this->createStub(PublishableStatusChecker::class),
            $this->createStub(ManagerRegistry::class),
            $this->createStub(IriConverterInterface::class),
            $this->createStub(ResourceMetadataProvider::class),
        );
        $normalizer->setDenormalizer($denormalizer);

        self::assertTrue($normalizer->supportsDenormalization($data, ComponentPosition::class));
        self::assertSame($position, $normalizer->denormalize($data, ComponentPosition::class, 'jsonld'));
        self::assertSame($sent, $position->wasSortValueSent());
    }
}
