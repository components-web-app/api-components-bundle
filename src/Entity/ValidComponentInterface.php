<?php

namespace Silverback\ApiComponentBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;

interface ValidComponentInterface
{
    public function getValidComponents(): ArrayCollection;
    public function addValidComponent(string $component);
    public function removeValidComponent(string $component);
}
