<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\OrphanedResource;

use Psr\Cache\CacheItemPoolInterface;
use Silverback\ApiComponentsBundle\ApiResource\OrphanedResourceReport;

class OrphanedResourceReportStore
{
    public const string CACHE_KEY = 'silverback_api_components.orphaned_resource_report';

    public function __construct(private readonly CacheItemPoolInterface $cachePool)
    {
    }

    public function save(OrphanedResourceReport $report): void
    {
        $item = $this->cachePool->getItem(self::CACHE_KEY);
        $item->set([
            'generatedAt' => $report->generatedAt->format(\DateTimeInterface::ATOM),
            'componentGroups' => $report->componentGroups,
            'componentPositions' => $report->componentPositions,
            'components' => $report->components,
        ]);
        $this->cachePool->save($item);
    }

    public function fetch(): ?OrphanedResourceReport
    {
        $item = $this->cachePool->getItem(self::CACHE_KEY);
        if (!$item->isHit()) {
            return null;
        }
        $data = $item->get();
        if (!\is_array($data)) {
            return null;
        }

        return new OrphanedResourceReport(
            new \DateTimeImmutable($data['generatedAt']),
            $data['componentGroups'],
            $data['componentPositions'],
            $data['components'],
        );
    }

    public function clear(): void
    {
        $this->cachePool->deleteItem(self::CACHE_KEY);
    }
}
