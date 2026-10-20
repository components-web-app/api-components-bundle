<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\Page;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;

final class ManifestTreeLoader
{
    public const int CHUNK_SIZE = 500;

    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly int $chunkSize = self::CHUNK_SIZE,
    ) {
    }

    public function load(object $root): void
    {
        $pages = [];
        $layouts = [];
        foreach ($this->renderedPages($root) as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            $pages[(string) $page->getId()] = $page;
            $layout = $page->layout ?? null;
            if ($layout instanceof Layout) {
                $layouts[(string) $layout->getId()] = $layout;
            }
        }

        $owners = $this->loadGroupsOf(Page::class, $pages) + $this->loadGroupsOf(Layout::class, $layouts);
        $loaded = [];
        while ([] !== $owners) {
            $loaded += $owners;
            $owners = array_diff_key($this->loadGroupsOf(AbstractComponent::class, $owners), $loaded);
        }
    }

    /**
     * @return list<AbstractPage>
     */
    private function renderedPages(object $root): array
    {
        $pages = [];
        $seen = [];
        $queue = [$root];
        while (null !== ($item = array_shift($queue))) {
            $key = $item::class . ':' . $item->getId();
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            if ($item instanceof Route) {
                array_push($queue, ...array_filter([$item->getRedirect(), $item->getPage(), $item->getPageData()]));
                continue;
            }
            if (!$item instanceof AbstractPage) {
                continue;
            }
            $pages[] = $item;
            if ($item instanceof AbstractPageData) {
                $queue[] = $item->page;
            }
            array_push($queue, ...array_filter([$item->getParentPage(), $item->getParentPageData()]));
        }

        return $pages;
    }

    /**
     * @param class-string                                 $class
     * @param array<string, Page|Layout|AbstractComponent> $owners
     *
     * @return array<string, AbstractComponent>
     */
    private function loadGroupsOf(string $class, array $owners): array
    {
        if ([] === $owners) {
            return [];
        }

        $manager = $this->registry->getManagerForClass($class);
        if (!$manager instanceof EntityManagerInterface) {
            return [];
        }

        foreach (array_chunk(array_keys($owners), $this->chunkSize) as $ids) {
            $manager->createQueryBuilder()
                ->select('o', 'g', 'p', 'c')
                ->from($class, 'o')
                ->leftJoin('o.componentGroups', 'g')
                ->leftJoin('g.componentPositions', 'p')
                ->leftJoin('p.component', 'c')
                ->where('o.id IN (:ids)')
                ->setParameter('ids', $ids, ArrayParameterType::STRING)
                ->getQuery()
                ->getResult();
        }

        $components = [];
        foreach ($owners as $owner) {
            foreach ($owner->getComponentGroups() as $group) {
                if (!$group instanceof ComponentGroup) {
                    continue;
                }
                foreach ($group->componentPositions as $position) {
                    $component = $position->component;
                    if ($component instanceof AbstractComponent) {
                        $components[(string) $component->getId()] = $component;
                    }
                }
            }
        }

        return $components;
    }
}
