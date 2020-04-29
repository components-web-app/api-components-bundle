---
layout: default
title: File Uploads
parent: Custom Components
nav_order: 3
---
# File Uploads

The easiest way to configure an entity resource as uploadable is to use the following annotation and trait:

```php
use Doctrine\Common\Collections\ArrayCollection;
use Silverback\ApiComponentBundle\Annotation as Silverback;
use Silverback\ApiComponentBundle\Entity\Utility\UploadsTrait;

/**
 * @Silverback\Uploads
 */
class UploadsResource
{
    use UploadsTrait;

    public function __construct()
    {
        $this->files = new ArrayCollection();
    }
```

This also requires a `File` resource as the media objects will accept `multipart/form-data` and will be an object not intended to receive or respond in JSON or whatever serialization type you have chosen.

```php
use Doctrine\Common\Collections\ArrayCollection;
use Silverback\ApiComponentBundle\Annotation as Silverback;
use Silverback\ApiComponentBundle\Entity\Utility\FileTrait;

/**
 * @Silverback\File(UploadableResource::class)
 */
class File
{
    use FileTrait;

    public function __construct()
    {
        $this->mediaObjects = new ArrayCollection();
    }
```

You do not need to use the traits and can define your own custom fields. Take a look at the annotation classes for the available configuration parameters and you can base your updated class on the traits.

A file will have `MediaObject` resources appended to it with the IRI/Schema configured.
