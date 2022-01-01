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

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @ApiResource(attributes={
 *     "pagination_maximum_items_per_page"=40,
 *     "pagination_client_enabled"=true,
 * })
 * @ApiFilter(SearchFilter::class, properties={"reference"="partial"})
 * @ORM\Entity
 */
class DummyResourceWithFilters
{
    use IdTrait;

    /**
     * @ORM\Column
     */
    public string $reference;
}
