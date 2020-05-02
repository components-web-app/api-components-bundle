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

namespace Silverback\ApiComponentsBundle\Entity\Core;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\TimestampedTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @Silverback\Timestamped
 * @ApiResource
 */
class ComponentGroup
{
    use IdTrait;
    use TimestampedTrait;

    public string $reference;

    /**
     * @var Collection|Layout[]
     */
    public $layouts;

    /**
     * @var Collection|PageTemplate[]
     */
    public Collection $pageTemplates;

    /**
     * @ApiProperty(writable=false)
     *
     * @var Collection|AbstractComponent[]
     */
    public Collection $components;

    /**
     * @var Collection|ComponentLocation[]
     */
    public Collection $componentLocations;

    public function __construct()
    {
        $this->setId();
        $this->componentLocations = new ArrayCollection();
    }
}
