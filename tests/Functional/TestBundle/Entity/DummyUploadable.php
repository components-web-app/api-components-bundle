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

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Annotation as Silverback;
use Silverback\ApiComponentBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentBundle\Entity\Utility\UploadableTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @Silverback\Uploadable
 * @ORM\Entity
 */
class DummyUploadable
{
    use IdTrait;
    use UploadableTrait;

    public function __construct()
    {
        $this->setId();
    }
}
