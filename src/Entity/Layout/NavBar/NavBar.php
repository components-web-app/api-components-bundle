<?php

namespace Silverback\ApiComponentBundle\Entity\Layout\NavBar;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Navigation\AbstractNavigation;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

/**
 * Class NavBar
 * @package Silverback\ApiComponentBundle\Entity\Layout\NavBar
 * @author Daniel West <daniel@silverback.is
 * @ApiResource()
 * @ORM\Entity()
 */
class NavBar extends AbstractNavigation
{
}
