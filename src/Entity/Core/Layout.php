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

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\TimestampedTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\UiTrait;
use Silverback\ApiComponentsBundle\Filter\OrSearchFilter;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @Silverback\Timestamped
 * @ApiResource(mercure=true)
 * @ApiFilter(OrderFilter::class, properties={"createdAt", "reference"}, arguments={"orderParameterName"="order"})
 * @ApiFilter(OrSearchFilter::class, properties={"reference"="ipartial", "uiComponent"="ipartial"})
 * @UniqueEntity(fields={"reference"}, message="There is already a Layout with that reference.")
 */
class Layout
{
    use IdTrait;
    use TimestampedTrait;
    use UiTrait;

    /**
     * @Assert\NotBlank(message="Please enter a reference.")
     */
    public string $reference;

    /**
     * @var Collection|Page[]
     */
    public Collection $pages;

    public function __construct()
    {
        $this->initComponentCollections();
        $this->pages = new ArrayCollection();
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $metadata->addPropertyConstraint('uiComponent', new Assert\NotBlank([
            'message' => 'You must define the uiComponent for this resource.',
        ]));
    }
}
