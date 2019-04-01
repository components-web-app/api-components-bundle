<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Page\Dynamic;

use Silverback\ApiComponentBundle\Entity\Content\Page\PageTrait;
use Silverback\ApiComponentBundle\Entity\PublishableTrait;
use Silverback\ApiComponentBundle\Entity\Route\RouteAwareTrait;
use Silverback\ApiComponentBundle\Entity\SortableTrait;
use Silverback\ApiComponentBundle\Entity\TimestampedEntityTrait;

abstract class DynamicContentBase implements DynamicContentInterface
{
    use RouteAwareTrait;
    use PublishableTrait;
    use SortableTrait;
    use TimestampedEntityTrait;
    use PageTrait;
}
