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

use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Symfony\Contracts\Service\ResetInterface;

final class TemplatePageDataCounter implements ResetInterface
{
    /**
     * @var \WeakMap<Page, int>
     */
    private \WeakMap $counts;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        $this->counts = new \WeakMap();
    }

    /**
     * @param iterable<mixed> $pages
     */
    public function prefetch(iterable $pages): void
    {
        $templates = [];
        foreach ($pages as $page) {
            if ($page instanceof Page && $page->isTemplate && null !== $page->getId() && !isset($this->counts[$page])) {
                $templates[$page->getId()->toString()] = $page;
            }
        }
        if ([] === $templates) {
            return;
        }

        $counts = $this->countByPageId($templates);
        foreach ($templates as $id => $page) {
            $this->counts[$page] = $counts[$id] ?? 0;
        }
    }

    public function count(Page $page): int
    {
        $this->prefetch([$page]);

        return $this->counts[$page] ?? 0;
    }

    public function reset(): void
    {
        $this->counts = new \WeakMap();
    }

    /**
     * @param array<string, Page> $pages
     *
     * @return array<string, int>
     */
    private function countByPageId(array $pages): array
    {
        return array_map(intval(...), array_column($this->entityManager->createQueryBuilder()
            ->select('IDENTITY(pd.page) AS page', 'COUNT(pd.id) AS total')
            ->from(AbstractPageData::class, 'pd')
            ->andWhere('pd.page IN (:pages)')
            ->setParameter('pages', $pages)
            ->groupBy('pd.page')
            ->getQuery()
            ->getScalarResult(), 'total', 'page'));
    }
}
