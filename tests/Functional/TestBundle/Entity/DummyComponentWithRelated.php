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
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;

#[ApiResource]
#[ORM\Entity]
class DummyComponentWithRelated extends AbstractComponent
{
    #[ORM\Column(nullable: true)]
    public ?string $name = null;

    #[ORM\ManyToOne(targetEntity: AbstractComponent::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?AbstractComponent $related = null;
}
