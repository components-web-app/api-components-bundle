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
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\UploadableTrait;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;

#[Silverback\Uploadable]
#[ApiResource]
#[ORM\Entity]
class DummyUploadableWithConstraints
{
    use IdTrait;
    use UploadableTrait;

    #[Silverback\UploadableField(adapter: 'local')]
    #[Assert\File(mimeTypes: ['image/png', 'image/svg+xml'])]
    #[Assert\When(
        expression: 'value !== null && value.getMimeType() !== "image/svg+xml"',
        constraints: [new Assert\Image(maxPixels: 300_000)],
    )]
    public ?File $file = null;
}
