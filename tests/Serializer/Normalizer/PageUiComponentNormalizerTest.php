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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\PageUiComponentNormalizer;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

#[CoversClass(PageUiComponentNormalizer::class)]
class PageUiComponentNormalizerTest extends TestCase
{
    private array $calls = [];

    private function buildNormalizer(): PageUiComponentNormalizer
    {
        $normalizer = new PageUiComponentNormalizer();
        $normalizer->setDenormalizer(new class($this->calls) implements DenormalizerInterface {
            public function __construct(private array &$calls)
            {
            }

            public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
            {
                $this->calls[] = [$data, $type, $format, $context];

                return new Page();
            }

            public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
            {
                return true;
            }

            public function getSupportedTypes(?string $format): array
            {
                return ['*' => false];
            }
        });

        return $normalizer;
    }

    public function test_a_short_ui_component_is_passed_on_with_the_prefix(): void
    {
        $result = $this->buildNormalizer()->denormalize(['uiComponent' => 'PrimaryPageTemplate', 'reference' => 'home'], Page::class, 'jsonld', ['key' => 'value']);

        self::assertInstanceOf(Page::class, $result);
        self::assertCount(1, $this->calls);
        [$data, $type, $format, $context] = $this->calls[0];
        self::assertSame(['uiComponent' => 'CwaPagePrimaryPageTemplate', 'reference' => 'home'], $data);
        self::assertSame(Page::class, $type);
        self::assertSame('jsonld', $format);
        self::assertSame('value', $context['key']);
    }

    public function test_a_full_ui_component_is_passed_on_unchanged(): void
    {
        $this->buildNormalizer()->denormalize(['uiComponent' => 'CwaPagePrimaryPageTemplate'], Page::class);

        self::assertSame(['uiComponent' => 'CwaPagePrimaryPageTemplate'], $this->calls[0][0]);
    }

    public function test_data_without_a_ui_component_is_passed_on_unchanged(): void
    {
        $this->buildNormalizer()->denormalize(['reference' => 'home'], Page::class);

        self::assertSame(['reference' => 'home'], $this->calls[0][0]);
    }

    public function test_a_non_string_ui_component_is_left_for_the_serializer_to_reject(): void
    {
        $this->buildNormalizer()->denormalize(['uiComponent' => 5], Page::class);

        self::assertSame(['uiComponent' => 5], $this->calls[0][0]);
    }

    public function test_it_supports_page_data_arrays_once_per_denormalization(): void
    {
        $normalizer = $this->buildNormalizer();

        self::assertTrue($normalizer->supportsDenormalization([], Page::class));
        self::assertTrue($normalizer->supportsDenormalization([], PageUiComponentNormalizerPage::class));
        self::assertFalse($normalizer->supportsDenormalization([], Layout::class));
        self::assertFalse($normalizer->supportsDenormalization([], PageData::class));
        self::assertFalse($normalizer->supportsDenormalization('/_/pages/1', Page::class));

        $normalizer->denormalize([], Page::class, null, []);
        $innerContext = $this->calls[0][3];
        self::assertFalse($normalizer->supportsDenormalization([], Page::class, null, $innerContext));
    }

    public function test_it_declares_objects_without_caching_so_page_subclasses_reach_it(): void
    {
        self::assertSame(['object' => false], (new PageUiComponentNormalizer())->getSupportedTypes(null));
    }
}

class PageUiComponentNormalizerPage extends Page
{
}
