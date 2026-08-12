<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Utility\PublishableTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\UploadableTrait;
use Symfony\Component\HttpFoundation\File\File;

/**
 * @author Daniel West <daniel@silverback.is>
 */
#[Silverback\Publishable]
#[Silverback\Uploadable]
#[ApiResource]
#[ORM\Entity]
class DummyUploadableAndPublishable extends AbstractComponent
{
    use PublishableTrait;
    use UploadableTrait;

    // A prefix so the draft-clone and publish-merge paths are exercised against a stored path that
    // has a directory to preserve, not just a bare basename.
    #[Silverback\UploadableField(adapter: 'local', prefix: 'components/')]
    public ?File $file = null;
}
