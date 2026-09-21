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

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Utility\PublishableTrait;
use Silverback\ApiComponentsBundle\Filter\OrSearchFilter;

/**
 * @author Daniel West <daniel@silverback.is>
 */
#[Silverback\Publishable]
#[ApiResource(mercure: true)]
#[ApiFilter(OrSearchFilter::class, properties: ['reference' => 'ipartial'])]
#[ORM\Entity]
class DummyPublishableComponent extends AbstractComponent
{
    use PublishableTrait;

    /**
     * @var string a reference for this component
     */
    #[ORM\Column]
    public string $reference = '';
}
