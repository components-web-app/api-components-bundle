<?php

namespace Silverback\ApiComponentBundle\Entity\Component\News;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;

/**
 * Class News
 * @package Silverback\ApiComponentBundle\Entity\Component\News
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity()
 * @ApiResource()
 */
class News extends AbstractComponent
{}
