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
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;

/**
 * @author Daniel West <daniel@silverback.is>
 */
#[ApiResource]
#[ORM\Entity]
class PageDataWithComponent extends AbstractPageData
{
    #[ORM\ManyToOne(targetEntity: DummyComponent::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?DummyComponent $component = null;

    #[ORM\ManyToOne(targetEntity: DummyPublishableComponent::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?DummyPublishableComponent $publishableComponent = null;
}
