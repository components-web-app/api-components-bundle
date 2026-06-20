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

    public function test_supports_normalization_returns_true_for_abstract_page_data(): void
    {
        // Mutant 103: inverts the object/traversable condition — for a plain AbstractPageData (not traversable),
        // the mutant would return false; this test kills it
        $pageData = new class extends AbstractPageData {
            public Page $page;
        };

        $this->assertTrue($this->normalizer->supportsNormalization($pageData));
    }

    public function test_supports_normalization_returns_false_when_already_called_context(): void
    {
        // Mutant 104: !isset(ALREADY_CALLED) → isset(ALREADY_CALLED) — the context key is set but
        // with the mutant the check is inverted, meaning the array wouldn't be initialized
        // This test verifies that if the same page data ID is already in context, false is returned
        $pageData = new class extends AbstractPageData {
            public Page $page;
        };

        // First call to get the ID
        $id = $pageData->getId();

        // Pass the ID in the ALREADY_CALLED context (simulates a second call for the same object)
        $context = ['PAGE_DATA_NORMALIZER_ALREADY_CALLED' => [$id]];

        $this->assertFalse($this->normalizer->supportsNormalization($pageData, null, $context));
    }

    public function test_supports_normalization_returns_false_for_object_without_id(): void
    {
        // Mutant 105: return false removed in NoSuchPropertyException catch — the normalizer
        // would continue processing an object that has no 'id' property
        // Use a non-AbstractPageData object that has no 'id' property to trigger the exception path
        // Note: stdClass is handled by the !is_object check... actually objects WITH id go through
        // To truly test this: any object that triggers NoSuchPropertyException on 'id' lookup
        $objectWithoutId = new class {
            // no id property or getter
        };

        $this->assertFalse($this->normalizer->supportsNormalization($objectWithoutId));
    }
}
