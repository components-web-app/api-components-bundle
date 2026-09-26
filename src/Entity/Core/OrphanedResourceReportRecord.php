<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Entity\Core;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\ApiResource\OrphanedResourceReport;

#[ORM\Entity]
#[ORM\Table(name: 'orphaned_resource_report')]
class OrphanedResourceReportRecord
{
    public const int ID = 1;
    public const string GENERATED_AT_FORMAT = 'Y-m-d\TH:i:s.uP';

    #[ORM\Id]
    #[ORM\Column(type: 'smallint')]
    private int $id = self::ID;

    #[ORM\Column(name: 'generated_at', length: 32)]
    private string $generatedAt = '';

    /** @var list<string> */
    #[ORM\Column(name: 'component_groups', type: 'json')]
    private array $componentGroups = [];

    /** @var list<string> */
    #[ORM\Column(name: 'component_positions', type: 'json')]
    private array $componentPositions = [];

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $components = [];

    /** @var array{generatedAt: string, componentGroups: list<string>, componentPositions: list<string>, components: list<string>}|null */
    #[ORM\Column(name: 'last_notified', type: 'json', nullable: true)]
    private ?array $lastNotified = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function update(OrphanedResourceReport $report): void
    {
        $this->generatedAt = $report->generatedAt->format(self::GENERATED_AT_FORMAT);
        $this->componentGroups = $report->componentGroups;
        $this->componentPositions = $report->componentPositions;
        $this->components = $report->components;
    }

    public function markNotified(OrphanedResourceReport $report): void
    {
        $this->lastNotified = [
            'generatedAt' => $report->generatedAt->format(self::GENERATED_AT_FORMAT),
            'componentGroups' => $report->componentGroups,
            'componentPositions' => $report->componentPositions,
            'components' => $report->components,
        ];
    }

    public function toNotifiedReport(): ?OrphanedResourceReport
    {
        if (null === $this->lastNotified) {
            return null;
        }

        return new OrphanedResourceReport(
            new \DateTimeImmutable($this->lastNotified['generatedAt']),
            $this->lastNotified['componentGroups'],
            $this->lastNotified['componentPositions'],
            $this->lastNotified['components'],
        );
    }

    public function toReport(): OrphanedResourceReport
    {
        return new OrphanedResourceReport(
            new \DateTimeImmutable($this->generatedAt),
            $this->componentGroups,
            $this->componentPositions,
            $this->components,
        );
    }
}
