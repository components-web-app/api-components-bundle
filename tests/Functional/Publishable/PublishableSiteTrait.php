<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Publishable;

use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableCustomComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;

trait PublishableSiteTrait
{
    use SiteTrait;

    protected static function createCustomPublishable(?\DateTimeInterface $publishedAt): DummyPublishableCustomComponent
    {
        $resource = new DummyPublishableCustomComponent();
        $resource->reference = null !== $publishedAt && $publishedAt <= new \DateTime() ? 'is_published' : 'is_draft';
        $resource->setCustomPublishedAt(null === $publishedAt ? null : \DateTime::createFromInterface($publishedAt));
        static::persistAndFlush($resource);

        return $resource;
    }

    /**
     * @return array{componentPosition: string, pageData: string}
     */
    protected static function createPublishableInPageDataAndPosition(DummyPublishableComponent $component): array
    {
        $group = static::newComponentGroup('test');
        $page = static::newPage('test page', true);
        $pageData = new PageDataWithComponent();
        $pageData->publishableComponent = $component;
        $pageData->page = $page;
        $position = new ComponentPosition();
        $position->pageDataProperty = 'publishableComponent';
        $position->pageDataClass = PageDataWithComponent::class;
        $position->component = $component;
        $position->componentGroup = $group;
        $position->sortValue = 0;
        static::persistAndFlush($group, $page, $component, $pageData, $position);

        return ['componentPosition' => static::iriOf($position), 'pageData' => static::iriOf($pageData)];
    }

    /**
     * @return array{componentPosition: string, component: string}
     */
    protected static function createPageDataWithPublishableInPageDataPropertyPosition(DummyPublishableComponent $component, string $path): array
    {
        $group = static::newComponentGroup('test');
        $position = new ComponentPosition();
        $position->pageDataProperty = 'publishableComponent';
        $position->pageDataClass = PageDataWithComponent::class;
        $position->componentGroup = $group;
        $position->sortValue = 0;
        $page = static::newPage('test page', true);
        $page->addComponentGroup($group);
        $pageData = new PageDataWithComponent();
        $pageData->publishableComponent = $component;
        $pageData->page = $page;
        static::persistAndFlush($group, $position, $page, $component, $pageData, static::newRoute($path)->setPageData($pageData));

        return ['componentPosition' => static::iriOf($position), 'component' => static::iriOf($component)];
    }

    protected static function createPublishedComponentInPositions(int $count): string
    {
        $component = new DummyPublishableComponent();
        $component->setPublishedAt(new \DateTime());
        $entities = [$component];
        for ($i = 0; $i < $count; ++$i) {
            $group = static::newComponentGroup('test_group_' . $i);
            array_push($entities, $group, static::newComponentPosition($group, $component, 0));
        }
        static::persistAndFlush(...$entities);

        return static::iriOf($component);
    }
}
