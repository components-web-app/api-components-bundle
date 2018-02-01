<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Feature;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;

/**
 * Class AbstractFeature
 * @package Silverback\ApiComponentBundle\Entity\Component\Feature
 */
abstract class AbstractFeature extends AbstractComponent implements FeatureInterface
{}
