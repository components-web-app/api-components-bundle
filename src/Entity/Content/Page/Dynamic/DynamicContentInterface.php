<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Page\Dynamic;

use Silverback\ApiComponentBundle\Entity\Content\Page\PageInterface;
use Silverback\ApiComponentBundle\Entity\PublishableInterface;
use Silverback\ApiComponentBundle\Entity\Route\RouteAwareInterface;
use Silverback\ApiComponentBundle\Entity\SortableInterface;
use Silverback\ApiComponentBundle\Entity\TimestampedEntityInterface;

interface DynamicContentInterface extends SortableInterface, TimestampedEntityInterface, RouteAwareInterface, PageInterface, PublishableInterface
{}
