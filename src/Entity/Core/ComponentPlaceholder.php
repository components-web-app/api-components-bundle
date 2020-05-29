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
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This will populate with a component that is defined in a property
 * of an object extending AbstractPageData. We will know which one
 * based on PageDataProvider which uses referer header to determine
 * which route was being loaded.
 *
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource
 */
class ComponentPlaceholder
{
    use IdTrait;

    /**
     * @Assert\NotNull()
     */
    public ?string $pageDataProperty = null;

    /**
     * @ApiProperty(writable=false)
     */
    public ?AbstractComponent $component = null;
}
