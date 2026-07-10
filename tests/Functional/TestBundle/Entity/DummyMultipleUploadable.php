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

/**
 * A resource with two independent uploadable fields:
 *  - $file: a generic file field (no imagine filters — could be a PDF/docx), stored in the
 *    UploadableTrait `filename` column (default `property`).
 *  - $preview: an image field with imagine filters, stored in its own `previewFilename` column.
 *
 * Each field has a distinct storage `property`, so uploading to one never touches the other.
 * The bundle's Doctrine UploadableListener auto-maps the `previewFilename` column — no
 * #[ORM\Column] needed, mirroring how UploadableTrait's `filename` is mapped.
 *
 * @author Daniel West <daniel@silverback.is>
 */
#[Silverback\Uploadable]
#[ApiResource(mercure: true)]
#[ORM\Entity]
class DummyMultipleUploadable
{
    use IdTrait;
    use UploadableTrait;

    public ?string $previewFilename = null;

    #[Silverback\UploadableField(adapter: 'local')]
    public ?File $file = null;

    #[Silverback\UploadableField(adapter: 'local', property: 'previewFilename', imagineFilters: ['thumbnail'])]
    public ?File $preview = null;
}
