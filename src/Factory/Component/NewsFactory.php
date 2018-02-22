<?php

namespace Silverback\ApiComponentBundle\Factory\Component;

use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Component\Article\Article;

class NewsFactory extends AbstractComponentFactory
{
    public function getComponent(): AbstractComponent
    {
        return new Article();
    }
}
