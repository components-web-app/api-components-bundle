<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 */
#[ApiResource(
    paginationClientEnabled: true,
    paginationMaximumItemsPerPage: 40
)]
#[ApiFilter(SearchFilter::class, properties: ['reference' => 'partial'])]
#[ORM\Entity]
class DummyResourceWithFilters
{
    use IdTrait;

    #[Orm\Column]
    public string $reference;
}
