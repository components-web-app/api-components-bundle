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
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use Silverback\ApiComponentsBundle\Entity\Utility\UiTrait;
use Silverback\ApiComponentsBundle\Filter\OrSearchFilter;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource(mercure=true)
 * @ApiFilter(OrderFilter::class, properties={"createdAt", "reference"}, arguments={"orderParameterName"="order"})
 * @ApiFilter(OrSearchFilter::class, properties={"title"="ipartial", "reference"="ipartial", "uiComponent"="ipartial", "layout.reference"="ipartial"})
 */
class Page extends AbstractPage
{
    use UiTrait;

    /**
     * @Assert\NotBlank(message="Please specify a layout.")
     */
    public ?Layout $layout;

    /**
     * @Assert\NotBlank(message="Please enter a reference.")
     */
    public string $reference;

    public function __construct()
    {
        $this->initComponentCollections();
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $metadata->addPropertyConstraint('uiComponent', new Assert\NotBlank([
            'message' => 'You must define the uiComponent for this resource.',
        ]));
    }
}
