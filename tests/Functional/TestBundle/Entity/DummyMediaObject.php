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

use Silverback\ApiComponentBundle\Annotation as Silverback;
use Silverback\ApiComponentBundle\Entity\Utility\MediaObjectTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @Silverback\MediaObject(DummyUploadable::class)
 */
class DummyMediaObject
{
    use MediaObjectTrait;

    public function __construct()
    {
        $this->setId();
    }
}
