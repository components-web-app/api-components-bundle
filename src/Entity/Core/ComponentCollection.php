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
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @Silverback\Timestamped
 * @ApiResource
 * @UniqueEntity(fields={"reference"}, message="There is already a ComponentCollection resource with that reference.")
 */
class ComponentCollection
{
    use IdTrait;
    use TimestampedTrait;

    /**
     * @Assert\NotBlank(message="A component collection must have a reference")
     */
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
     * @var Collection|ComponentPosition[]
     */
    public Collection $componentPositions;

    public function __construct()
    {
        $this->componentPositions = new ArrayCollection();
    }
}
