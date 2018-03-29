<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Component\Collection;

use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Annotation\ApiSubresource;
use Silverback\ApiComponentBundle\Entity\Content\Component\AbstractComponent;

/**
 * @ApiResource()
 * @author Daniel West <daniel@silverback.is>
 */
class Collection extends AbstractComponent
{
    /**
     * @var mixed
     */
    private $resource;

    /**
     * @return mixed
     */
    public function getResource()
    {
        return $this->resource;
    }
}
