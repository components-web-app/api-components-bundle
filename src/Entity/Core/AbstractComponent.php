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

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\UiTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 */
abstract class AbstractComponent implements ComponentInterface
{
    use IdTrait;
    use UiTrait;

    /**
     * @var Collection|ComponentPosition[]
     */
    public Collection $componentPositions;

    public function __construct()
    {
        $this->initComponentCollections();
        $this->componentPositions = new ArrayCollection();
    }
}
