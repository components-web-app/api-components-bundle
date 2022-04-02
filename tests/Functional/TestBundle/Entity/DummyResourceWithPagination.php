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

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 */
#[ApiResource(
    paginationClientEnabled: true,
    paginationClientItemsPerPage: true,
    paginationItemsPerPage: 10,
    paginationMaximumItemsPerPage: 40
)]
#[Get]
#[GetCollection]
#[ORM\Entity]
class DummyResourceWithPagination
{
    use IdTrait;
}
