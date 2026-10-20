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
use Silverback\ApiComponentsBundle\Entity\Utility\UploadableTrait;
use Symfony\Component\HttpFoundation\File\File;

#[Silverback\Uploadable]
#[ApiResource]
#[ORM\Entity]
class DummyImageComponent extends AbstractComponent
{
    use UploadableTrait;

    #[Silverback\UploadableField(adapter: 'local', prefix: 'images/', imagineFilters: ['thumbnail', 'square_thumbnail'])]
    public ?File $file = null;
}
