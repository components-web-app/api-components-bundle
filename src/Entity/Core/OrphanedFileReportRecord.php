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
use Silverback\ApiComponentsBundle\ApiResource\OrphanedFileReport;

#[ORM\Entity]
#[ORM\Table(name: 'orphaned_file_report')]
class OrphanedFileReportRecord
{
    public const int ID = 1;
    public const string GENERATED_AT_FORMAT = OrphanedResourceReportRecord::GENERATED_AT_FORMAT;

    #[ORM\Id]
    #[ORM\Column(type: 'smallint')]
    private int $id = self::ID;

    #[ORM\Column(name: 'generated_at', length: 32)]
    private string $generatedAt = '';

    /** @var list<array{adapter: string, path: string}> */
    #[ORM\Column(name: 'orphaned_files', type: 'json')]
    private array $orphanedFiles = [];

    /** @var list<array{resource: string, adapter: string, path: string}> */
    #[ORM\Column(name: 'missing_files', type: 'json')]
    private array $missingFiles = [];

    /** @var list<array{adapter: string, path: string}> */
    #[ORM\Column(name: 'unknown_files', type: 'json')]
    private array $unknownFiles = [];

    public function getId(): int
    {
        return $this->id;
    }

    public function update(OrphanedFileReport $report): void
    {
        $this->generatedAt = $report->generatedAt->format(self::GENERATED_AT_FORMAT);
        $this->orphanedFiles = $report->orphanedFiles;
        $this->missingFiles = $report->missingFiles;
        $this->unknownFiles = $report->unknownFiles;
    }

    public function toReport(): OrphanedFileReport
    {
        return new OrphanedFileReport(
            new \DateTimeImmutable($this->generatedAt),
            $this->orphanedFiles,
            $this->missingFiles,
            $this->unknownFiles,
        );
    }
}
