<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Nav;

interface NavInterface {
    public function createNavItem(): NavItemInterface;
}
