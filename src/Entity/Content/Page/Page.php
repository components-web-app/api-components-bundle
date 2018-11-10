<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Page;

use ApiPlatform\Core\Annotation\ApiProperty;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class Page
 * @package Silverback\ApiComponentBundle\Entity\Content
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity()
 */
class Page extends AbstractPage
{
    /**
     * @Groups({"default"})
     */
    protected $componentLocations;

    /**
     * @ApiProperty()
     * @Groups({"content","route"})
     */
    public function isDynamic(): bool
    {
        return false;
    }
}
