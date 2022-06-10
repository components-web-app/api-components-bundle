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
#[ORM\Table(name: "`dummy_user`")]
#[ApiFilter(OrSearchFilter::class, properties: [ 'username' => 'ipartial', 'emailAddress' => 'ipartial' ])]
class DummyOrSearchFilterable
{
    use IdTrait;

    #[Orm\Column]
    protected ?string $username;

    #[Orm\Column]
    protected ?string $emailAddress;
}
