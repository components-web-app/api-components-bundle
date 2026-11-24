<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;

#[ApiResource(operations: [
    new Get(cacheHeaders: ['public' => false, 'no_store' => true]),
    new GetCollection(cacheHeaders: ['public' => false, 'no_store' => true]),
])]
#[ORM\Entity]
class DummyVisitorDependentResource
{
    use IdTrait;

    #[ORM\Column]
    public string $label = '';
}
