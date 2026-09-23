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

use ApiPlatform\Doctrine\Orm\Filter\FreeTextQueryFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrFilter;
use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\QueryParameter;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Utility\PublishableTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 */
#[Silverback\Publishable]
#[ApiResource(
    mercure: true,
    parameters: [
        'search' => new QueryParameter(filter: new FreeTextQueryFilter(new OrFilter(new PartialSearchFilter())), properties: ['reference']),
    ],
)]
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
