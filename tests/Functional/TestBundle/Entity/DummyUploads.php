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
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Annotation as Silverback;
use Silverback\ApiComponentBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentBundle\Entity\Utility\UploadsTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @Silverback\Uploads
 * @ORM\Entity
 */
class DummyUploads
{
    use IdTrait;
    use UploadsTrait;

    public function __construct()
    {
        $this->setId();
        $this->files = new ArrayCollection();
    }
}
