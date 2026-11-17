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

use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Helper\Page\TemplatePageDataCounter;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\HttpCache\CwaTagCollector;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\TemplatePageNormalizer;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadataProvider;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Silverback\ApiComponentsBundle\Tests\Helper\OrphanedResource\OrphanedResourceDatabaseTestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class TemplatePageNormalizerTest extends OrphanedResourceDatabaseTestCase
{
    private ResourceMetadataProvider $metadataProvider;

    /**
     * @var list<array{object, ?string, array<string, mixed>}>
     */
    private array $delegated = [];

    public function test_an_admin_normalizing_a_template_page_gets_its_page_data_count_in_the_metadata(): void
    {
        $template = $this->templateUsedBy(2);

        $normalized = $this->normalizer(true)->normalize($template, 'jsonld', ['groups' => ['a']]);

        self::assertSame(['normalized'], $normalized);
        self::assertSame(2, $this->metadataProvider->findResourceMetadata($template)->getPageDataCount());
        self::assertSame([[$template, 'jsonld', ['groups' => ['a'], 'TEMPLATE_PAGE_NORMALIZER_ALREADY_CALLED' => true]]], $this->delegated);
    }

    public function test_an_unused_template_page_has_a_count_of_zero(): void
    {
        $template = $this->templateUsedBy(0);

        $this->normalizer(true)->normalize($template);

        self::assertSame(0, $this->metadataProvider->findResourceMetadata($template)->getPageDataCount());
    }

    public function test_a_caller_without_the_publishable_permission_gets_no_count(): void
    {
        $template = $this->templateUsedBy(1);

        $this->normalizer(false)->normalize($template);

        self::assertFalse($this->metadataProvider->resourceMetadataExists($template));
        self::assertCount(1, $this->delegated);
    }

    public function test_a_page_which_is_not_a_template_gets_no_count(): void
    {
        $page = $this->templateUsedBy(1);
        $page->isTemplate = false;

        $this->normalizer(true)->normalize($page);

        self::assertFalse($this->metadataProvider->resourceMetadataExists($page));
    }

    public function test_a_template_page_in_a_manifest_gets_no_count(): void
    {
        $template = $this->templateUsedBy(1);

        $this->normalizer(true)->normalize($template, null, [CwaTagCollector::MANIFEST_CONTEXT_KEY => true]);

        self::assertFalse($this->metadataProvider->resourceMetadataExists($template));
    }

    public function test_it_supports_a_page_only_once_per_normalization(): void
    {
        $normalizer = $this->normalizer(true);

        self::assertTrue($normalizer->supportsNormalization(new Page()));
        self::assertFalse($normalizer->supportsNormalization(new Layout()));
        self::assertFalse($normalizer->supportsNormalization(new Page(), null, ['TEMPLATE_PAGE_NORMALIZER_ALREADY_CALLED' => true]));
        self::assertSame([Page::class => false], $normalizer->getSupportedTypes(null));
    }

    private function normalizer(bool $granted): TemplatePageNormalizer
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn($granted);
        $this->metadataProvider = new ResourceMetadataProvider();
        $normalizer = new TemplatePageNormalizer(
            new TemplatePageDataCounter($this->entityManager),
            new PublishableStatusChecker($this->registry, new PublishableAttributeReader($this->registry), $authorizationChecker, 'is_granted("ROLE_ADMIN")'),
            $this->metadataProvider,
        );
        $inner = $this->createStub(NormalizerInterface::class);
        $inner->method('normalize')->willReturnCallback(function (mixed $object, ?string $format = null, array $context = []): array {
            $this->delegated[] = [$object, $format, $context];

            return ['normalized'];
        });
        $normalizer->setNormalizer($inner);

        return $normalizer;
    }

    private function templateUsedBy(int $pageData): Page
    {
        $template = $this->page();
        for ($i = 0; $i < $pageData; ++$i) {
            $item = new PageData();
            $item->page = $template;
            $this->persist($item);
        }
        $this->entityManager->flush();

        return $template;
    }
}
