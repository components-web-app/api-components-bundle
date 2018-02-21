<?php

namespace Silverback\ApiComponentBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;

interface ValidComponentInterface
{
    public function getValidComponents(): ArrayCollection;
    public function addValidComponent(AbstractComponent $component): self;
    public function removeValidComponent(AbstractComponent $component): self;
}
