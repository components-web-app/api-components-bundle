<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Tests\Functional\TestBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Silverback\ApiComponentBundle\Annotation as Silverback;
use Silverback\ApiComponentBundle\Entity\Utility\FileTrait;
use Silverback\ApiComponentBundle\Entity\Utility\IdTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @Silverback\File(DummyUploads::class)
 */
class DummyFile
{
    use IdTrait;
    use FileTrait;

    public function __construct()
    {
        $this->setId();
        $this->mediaObjects = new ArrayCollection();
    }
}
