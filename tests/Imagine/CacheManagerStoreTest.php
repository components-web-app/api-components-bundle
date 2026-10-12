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
use Liip\ImagineBundle\Model\Binary;
use Silverback\ApiComponentsBundle\Entity\Core\FileInfo;
use Silverback\ApiComponentsBundle\Event\ImagineStoreEvent;
use Silverback\ApiComponentsBundle\EventListener\Imagine\ImagineEventListener;
use Silverback\ApiComponentsBundle\Helper\Uploadable\FileInfoCacheManager;
use Silverback\ApiComponentsBundle\Imagine\CacheManager;
use Silverback\ApiComponentsBundle\Imagine\FlysystemCacheResolver;
use Silverback\ApiComponentsBundle\Repository\Core\FileInfoRepository;
use Silverback\ApiComponentsBundle\Tests\Helper\OrphanedResource\OrphanedResourceDatabaseTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Routing\RouterInterface;

class CacheManagerStoreTest extends OrphanedResourceDatabaseTestCase
{
    private CacheManager $cacheManager;

    protected function setUp(): void
    {
        parent::setUp();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ImagineStoreEvent::class, (new ImagineEventListener(new FileInfoCacheManager($this->entityManager, new FileInfoRepository($this->registry))))->onStore(...));
        $this->cacheManager = new CacheManager(
            new FilterConfiguration(['thumbnail' => ['filters' => []], 'square' => ['filters' => []]]),
            $this->createStub(RouterInterface::class),
            $this->createStub(SignerInterface::class),
            $dispatcher,
        );
        $this->cacheManager->addResolver('default', new FlysystemCacheResolver(new Filesystem(new InMemoryFilesystemAdapter()), '/'));

        foreach ([null, 'thumbnail', 'square'] as $filter) {
            $this->entityManager->persist(new FileInfo('a.png', 'image/png', 1, 1, 1, $filter));
        }
        $this->entityManager->persist(new FileInfo('b.png', 'image/png', 1, 1, 1, 'thumbnail'));
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    public function test_storing_a_variant_again_replaces_its_file_info_with_the_stored_binarys(): void
    {
        $webp = $this->webp(4, 3);

        $this->cacheManager->store(new Binary($webp, 'image/webp', 'webp'), 'a.png', 'thumbnail');

        self::assertSame([
            'a.png||image/png|1|1|1',
            'a.png|square|image/png|1|1|1',
            'a.png|thumbnail|image/webp|' . \strlen($webp) . '|4|3',
            'b.png|thumbnail|image/png|1|1|1',
        ], $this->rows());
    }

    public function test_storing_a_variant_with_no_file_info_inserts_it(): void
    {
        $webp = $this->webp(4, 3);

        $this->cacheManager->store(new Binary($webp, 'image/webp', 'webp'), 'b.png', 'square');

        self::assertContains('b.png|square|image/webp|' . \strlen($webp) . '|4|3', $this->rows());
        self::assertCount(5, $this->rows());
    }

    private function webp(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagewebp($image);

        return (string) ob_get_clean();
    }

    /**
     * @return list<string>
     */
    private function rows(): array
    {
        return $this->entityManager->getConnection()->fetchFirstColumn(\sprintf("SELECT path || '|' || filter || '|' || mime_type || '|' || file_size || '|' || width || '|' || height FROM %s ORDER BY path, filter", $this->entityManager->getClassMetadata(FileInfo::class)->getTableName()));
    }
}
