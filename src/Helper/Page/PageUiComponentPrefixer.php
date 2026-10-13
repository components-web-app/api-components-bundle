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
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Utility\PageUiComponent;

final class PageUiComponentPrefixer
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function prefix(): int
    {
        $classMetadata = $this->entityManager->getClassMetadata(Page::class);
        $table = $classMetadata->getTableName();
        $id = $classMetadata->getColumnName('id');
        $uiComponent = $classMetadata->getColumnName('uiComponent');
        $connection = $this->entityManager->getConnection();

        $updates = [];
        foreach ($connection->iterateAssociative(\sprintf('SELECT %s AS id, %s AS ui_component FROM %s WHERE %s IS NOT NULL', $id, $uiComponent, $table, $uiComponent)) as $row) {
            $fullName = PageUiComponent::fullName($row['ui_component']);
            if ($fullName !== $row['ui_component']) {
                $updates[(string) $row['id']] = $fullName;
            }
        }

        foreach ($updates as $pageId => $fullName) {
            $connection->update($table, [$uiComponent => $fullName], [$id => $pageId]);
        }

        return \count($updates);
    }
}
