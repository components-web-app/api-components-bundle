<?php

namespace Silverback\ApiComponentBundle\DataFixtures\Component;

use Silverback\ApiComponentBundle\Entity\Component\Component;
use Silverback\ApiComponentBundle\Entity\Component\News\News;

class NewsComponent extends AbstractComponent
{
    public function getComponent(): Component
    {
        return new News();
    }
}
