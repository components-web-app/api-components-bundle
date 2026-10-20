<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Page;

use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyImageComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\UploadsTrait;

trait ImagePageSiteTrait
{
    use SiteTrait;
    use UploadsTrait;

    /**
     * @return array{route: Route, layout: Layout, page: Page, groups: list<ComponentGroup>, positions: list<ComponentPosition>, images: list<DummyImageComponent>}
     */
    protected static function createImagePageWithNestedGroups(string $path, int $images = 3, int $nestedImages = 2): array
    {
        $fileManager = static::getContainer()->get(UploadableFileManager::class);
        $layout = static::newLayout('image-layout');
        $page = static::newPage('image-page');
        $page->uiComponent = 'TestComponent';
        $page->layout = $layout;
        $route = static::newRoute($path)->setPage($page);
        $pageGroup = static::newComponentGroup('image-page-group');
        $page->addComponentGroup($pageGroup);
        $groups = [$pageGroup];
        $positions = [];
        $allImages = [];

        $newImage = static function () use ($fileManager): DummyImageComponent {
            $image = new DummyImageComponent();
            $image->file = static::assetFile();
            $fileManager->persistFiles($image);

            return $image;
        };

        for ($i = 0; $i < $images; ++$i) {
            $allImages[] = $image = $newImage();
            $positions[] = static::newComponentPosition($pageGroup, $image, $i);
            $nestedGroup = static::newComponentGroup('image-group-' . $i);
            $nestedGroup->addComponent($image);
            $groups[] = $nestedGroup;
            for ($j = 0; $j < $nestedImages; ++$j) {
                $allImages[] = $nestedImage = $newImage();
                $positions[] = static::newComponentPosition($nestedGroup, $nestedImage, $j);
            }
        }

        static::persistAndFlush($layout, $page, $route, ...$groups, ...$allImages, ...$positions);

        return ['route' => $route, 'layout' => $layout, 'page' => $page, 'groups' => $groups, 'positions' => $positions, 'images' => $allImages];
    }
}
