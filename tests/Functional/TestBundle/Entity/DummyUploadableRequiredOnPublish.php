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
 * Two independent uploadable fields, each requiring a file before the resource can be published:
 *  - $file: uses a custom requiredOnPublishMessage.
 *  - $preview: uses the default fallback message and its own storage property.
 *
 * @author Daniel West <daniel@silverback.is>
 */
#[Silverback\Publishable]
#[Silverback\Uploadable]
#[ApiResource]
#[ORM\Entity]
class DummyUploadableRequiredOnPublish extends AbstractComponent
{
    use PublishableTrait;
    use UploadableTrait;

    public ?string $previewFilename = null;

    #[Silverback\UploadableField(adapter: 'local', requiredOnPublish: true, requiredOnPublishMessage: 'You must upload a file before publishing.')]
    public ?File $file = null;

    #[Silverback\UploadableField(adapter: 'local', property: 'previewFilename', requiredOnPublish: true)]
    public ?File $preview = null;
}
