<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Component\Navigation;

use ApiPlatform\Core\Annotation\ApiProperty;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Content\ComponentGroup\ComponentGroup;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class AbstractNavigation
 * @package Silverback\ApiComponentBundle\Entity\Navigation
 * @author Daniel West <daniel@silverback.is>
 */
abstract class AbstractNavigation extends AbstractComponent
{
    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Content\ComponentGroup\ComponentGroup", cascade={"persist"})
     * @ORM\JoinColumn(nullable=true, onDelete="SET NULL")
     * @ApiProperty(attributes={"fetchEager": false})
     * @Groups({"default"})
     * @var ComponentGroup
     */
    protected $childComponentGroup;

    public function __construct()
    {
        parent::__construct();
        $this->childComponentGroup = new ComponentGroup();
    }

    /**
     * @return \Silverback\ApiComponentBundle\Entity\Content\ComponentGroup\ComponentGroup
     */
    public function getChildComponentGroup(): ComponentGroup
    {
        return $this->childComponentGroup;
    }
}
