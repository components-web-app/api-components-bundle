<?php

namespace Silverback\ApiComponentBundle\Factory\Component;

use Silverback\ApiComponentBundle\Entity\Component\Component;
use Silverback\ApiComponentBundle\Entity\Component\News\News;

class NewsFactory extends AbstractComponentFactory
{
    public function getComponent(): Component
    {
        return new News();
    }
}
