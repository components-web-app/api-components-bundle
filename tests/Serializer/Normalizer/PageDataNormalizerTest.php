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

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Metadata\Factory\PageDataMetadataFactoryInterface;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\PageDataNormalizer;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadataProvider;

class PageDataNormalizerTest extends TestCase
{
    private PageDataNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new PageDataNormalizer(
            $this->createStub(PageDataMetadataFactoryInterface::class),
            $this->createStub(ResourceMetadataProvider::class)
        );
    }

    public function test_supports_normalization_returns_false_for_traversable_object(): void
    {
        $traversablePageData = new class extends AbstractPageData implements \IteratorAggregate {
            public Page $page;

            public function getIterator(): \Traversable
            {
                return new \ArrayIterator([]);
            }
        };

        $this->assertFalse($this->normalizer->supportsNormalization($traversablePageData));
    }

    public function test_supports_normalization_returns_false_for_non_object(): void
    {
        $this->assertFalse($this->normalizer->supportsNormalization('not an object'));
        $this->assertFalse($this->normalizer->supportsNormalization(42));
        $this->assertFalse($this->normalizer->supportsNormalization(null));
    }

    public function test_supports_normalization_returns_false_for_non_page_data_object(): void
    {
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }
}
