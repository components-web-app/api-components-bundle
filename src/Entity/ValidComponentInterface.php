<?php

namespace Silverback\ApiComponentBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Silverback\ApiComponentBundle\Entity\Component\Component;

interface ValidComponentInterface
{
    public function getValidComponents(): ArrayCollection;
    public function addValidComponent(Component $component): self;
    public function removeValidComponent(Component $component): self;
}
