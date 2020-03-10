<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Component;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Core\AbstractComponent;
use Traversable;

/**
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity
 */
class Collection extends AbstractComponent
{
    /**
     * @var array|Traversable
     */
    private $collection;

    public function getCollection()
    {
        return $this->collection;
    }

    public function setCollection($collection): self
    {
        if (!$collection instanceof Traversable && !\is_array($collection)) {
            return $this;
        }
        $this->collection = $collection;

        return $this;
    }
}
