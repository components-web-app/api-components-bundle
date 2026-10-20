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

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\ApiResource\ResourceManifest;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Helper\Page\ManifestTreeLoader;
use Silverback\ApiComponentsBundle\HttpCache\CwaTagCollector;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\ResourceManifestNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ResourceManifestNormalizerTest extends TestCase
{
    public function test_the_manifest_is_the_depth_groups_of_its_entity_normalized_in_the_manifest_context(): void
    {
        $page = new Page();
        $page->reference = 'page';
        $page->layout = null;
        $calls = [];
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->createStub(ObjectManager::class));
        $loader = new ManifestTreeLoader($registry, new PublishableAttributeReader($registry));

        $inner = $this->createMock(NormalizerInterface::class);
        $inner->expects(self::once())->method('normalize')
            ->with($page, 'jsonld', self::callback(static fn (array $context): bool => true === $context[CwaTagCollector::MANIFEST_CONTEXT_KEY]))
            ->willReturnCallback(static function () use (&$calls): array {
                $calls[] = 'normalize';

                return ['@id' => '/_/pages/1'];
            });

        $manifest = new ResourceManifest();
        $manifest->entity = $page;
        $normalizer = new ResourceManifestNormalizer($loader);
        $normalizer->setNormalizer($inner);

        self::assertSame(['resource_iris' => [['iri' => '/_/pages/1', 'children' => []]]], $normalizer->normalize($manifest, 'jsonld'));
        self::assertSame(['normalize'], $calls);
    }

    public function test_the_tree_of_the_manifests_entity_is_loaded_before_it_is_normalized(): void
    {
        $page = new Page();
        $page->reference = 'page';
        $page->layout = null;
        $calls = [];
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::once())->method('getManagerForClass')->with(Page::class)
            ->willReturnCallback(function () use (&$calls): ObjectManager {
                $calls[] = 'load';

                return $this->createStub(ObjectManager::class);
            });
        $inner = $this->createStub(NormalizerInterface::class);
        $inner->method('normalize')->willReturnCallback(static function () use (&$calls): array {
            $calls[] = 'normalize';

            return [];
        });

        $manifest = new ResourceManifest();
        $manifest->entity = $page;
        $normalizer = new ResourceManifestNormalizer(new ManifestTreeLoader($registry, new PublishableAttributeReader($registry)));
        $normalizer->setNormalizer($inner);
        $normalizer->normalize($manifest, 'jsonld');

        self::assertSame(['load', 'normalize'], $calls);
    }
}
