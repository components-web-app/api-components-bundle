<?php

namespace Silverback\ApiComponentBundle\Factory\Component;

use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Component\News\News;

class NewsFactory extends AbstractComponentFactory
{
    public function getComponent(): AbstractComponent
    {
        return new News();
    }
}
