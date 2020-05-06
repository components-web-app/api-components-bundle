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

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @ApiResource(attributes={
 *     "maximum_items_per_page"=40,
 *     "pagination_items_per_page"=10,
 *     "pagination_client_items_per_page"=true,
 *     "pagination_client_enabled"=true,
 * })
 * @ORM\Entity
 */
class DummyResourceWithPagination
{
    use IdTrait;
}
