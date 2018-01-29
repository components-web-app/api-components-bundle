<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Gallery;

use Silverback\ApiComponentBundle\Entity\Component\FileInterface;
use Silverback\ApiComponentBundle\Entity\Component\SortableInterface;

interface GalleryItemInterface extends SortableInterface, FileInterface
{}
