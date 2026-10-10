<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Imagine;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Liip\ImagineBundle\Imagine\Cache\SignerInterface;
use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;
use Silverback\ApiComponentsBundle\Entity\Core\FileInfo;
use Silverback\ApiComponentsBundle\Event\ImagineRemoveEvent;
use Silverback\ApiComponentsBundle\EventListener\Imagine\ImagineEventListener;
use Silverback\ApiComponentsBundle\Helper\Uploadable\FileInfoCacheManager;
use Silverback\ApiComponentsBundle\Imagine\CacheManager;
use Silverback\ApiComponentsBundle\Imagine\FlysystemCacheResolver;
use Silverback\ApiComponentsBundle\Repository\Core\FileInfoRepository;
use Silverback\ApiComponentsBundle\Tests\Helper\OrphanedResource\OrphanedResourceDatabaseTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Routing\RouterInterface;

class CacheManagerRemoveTest extends OrphanedResourceDatabaseTestCase
{
    private Filesystem $cache;
    private CacheManager $cacheManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = new Filesystem(new InMemoryFilesystemAdapter());
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ImagineRemoveEvent::class, (new ImagineEventListener(new FileInfoCacheManager($this->entityManager, new FileInfoRepository($this->registry))))->onRemove(...));
        $this->cacheManager = new CacheManager(
            new FilterConfiguration(['thumbnail' => ['filters' => []], 'square' => ['filters' => []]]),
            $this->createStub(RouterInterface::class),
            $this->createStub(SignerInterface::class),
            $dispatcher,
        );
        $this->cacheManager->addResolver('default', new FlysystemCacheResolver($this->cache, '/'));

        foreach (['a.png', 'b.png'] as $path) {
            $this->entityManager->persist(new FileInfo($path, 'image/png', 1, 1, 1, null));
            foreach (['thumbnail', 'square'] as $filter) {
                $this->cache->write('media/cache/' . $filter . '/' . $path, 'variant');
                $this->entityManager->persist(new FileInfo($path, 'image/png', 1, 1, 1, $filter));
            }
        }
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    public function test_a_single_path_and_filter_given_as_strings_removes_that_variant_and_its_row(): void
    {
        $this->cacheManager->remove('a.png', 'thumbnail');

        self::assertSame(['a/square', 'b/square', 'b/thumbnail'], $this->variants());
        self::assertSame(['a.png|', 'a.png|square', 'b.png|', 'b.png|square', 'b.png|thumbnail'], $this->rows());
    }

    public function test_a_filter_with_no_paths_removes_its_variants_and_rows_for_every_path(): void
    {
        $this->cacheManager->remove(null, ['thumbnail']);

        self::assertSame(['a/square', 'b/square'], $this->variants());
        self::assertSame(['a.png|', 'a.png|square', 'b.png|', 'b.png|square'], $this->rows());
    }

    public function test_no_paths_and_no_filters_removes_every_variant_and_variant_row_and_keeps_the_originals_rows(): void
    {
        $this->cacheManager->remove();

        self::assertSame([], $this->variants());
        self::assertSame(['a.png|', 'b.png|'], $this->rows());
    }

    public function test_a_path_with_no_filters_removes_its_variants_and_their_rows_and_keeps_its_originals_row(): void
    {
        $this->cacheManager->remove(['a.png']);

        self::assertSame(['b/square', 'b/thumbnail'], $this->variants());
        self::assertSame(['a.png|', 'b.png|', 'b.png|square', 'b.png|thumbnail'], $this->rows());
    }

    public function test_an_empty_path_is_ignored_as_liip_ignores_it(): void
    {
        $this->cacheManager->remove('', 'thumbnail');

        self::assertSame(['a/square', 'b/square'], $this->variants());
        self::assertSame(['a.png|', 'a.png|square', 'b.png|', 'b.png|square'], $this->rows());
    }

    public function test_an_empty_filter_is_ignored_as_liip_ignores_it_and_never_matches_an_originals_row(): void
    {
        $this->cacheManager->remove('a.png', '');

        self::assertCount(4, $this->variants());
        self::assertCount(6, $this->rows());
    }

    /**
     * @return list<string>
     */
    private function variants(): array
    {
        $variants = [];
        foreach (['thumbnail', 'square'] as $filter) {
            foreach (['a', 'b'] as $name) {
                if ($this->cache->fileExists('media/cache/' . $filter . '/' . $name . '.png')) {
                    $variants[] = $name . '/' . $filter;
                }
            }
        }
        sort($variants);

        return $variants;
    }

    /**
     * @return list<string>
     */
    private function rows(): array
    {
        return $this->entityManager->getConnection()->fetchFirstColumn(\sprintf("SELECT path || '|' || filter FROM %s ORDER BY path, filter", $this->entityManager->getClassMetadata(FileInfo::class)->getTableName()));
    }
}
