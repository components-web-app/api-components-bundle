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
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Utility\PublishableTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 * @Silverback\Publishable
 * @ApiResource
 * @ORM\Entity
 */
class DummyPublishableComponent extends AbstractComponent
{
    use PublishableTrait;

    /**
     * @var string a reference for this component
     *
     * @ORM\Column
     */
    public string $reference = '';
}
