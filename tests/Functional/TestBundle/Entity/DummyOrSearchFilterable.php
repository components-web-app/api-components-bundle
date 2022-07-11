<?php

namespace Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiFilter;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentsBundle\Filter\OrSearchFilter;

/**
 * @author Daniel West <daniel@silverback.is>
 */
#[ApiResource]
#[ORM\Entity]
#[ApiFilter(OrSearchFilter::class, properties: [ 'field1' => 'ipartial', 'field2' => 'ipartial' ])]
class DummyOrSearchFilterable
{
    use IdTrait;

    #[Orm\Column]
    public ?string $field1 = null;

    #[Orm\Column]
    public ?string $field2 = null;
}
