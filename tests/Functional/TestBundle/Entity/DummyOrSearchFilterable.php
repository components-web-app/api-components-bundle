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

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentsBundle\Filter\OrSearchFilter;

/**
 * @author Daniel West <daniel@silverback.is>
 */
#[ApiResource]
#[ORM\Entity]
#[ApiFilter(OrSearchFilter::class, properties: ['field1' => 'ipartial', 'field2' => 'ipartial'])]
class DummyOrSearchFilterable
{
    use IdTrait;

    #[ORM\Column]
    public ?string $field1 = null;

    #[ORM\Column]
    public ?string $field2 = null;
}
