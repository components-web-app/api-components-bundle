<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Gallery;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class Gallery
 * @package Silverback\ApiComponentBundle\Entity\Component\Gallery
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity()
 * @ApiResource()
 */
class Gallery extends AbstractComponent
{}
