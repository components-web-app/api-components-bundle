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

use ApiPlatform\Core\Annotation\ApiResource;
use Silverback\ApiComponentsBundle\Entity\Utility\UiTrait;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource
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
}
