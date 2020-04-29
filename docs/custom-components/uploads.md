---
layout: default
title: File Uploads
parent: Custom Components
nav_order: 3
---
# File Uploads

## Requirements
This bundle uses FlySystem v2. It does not use additional bundles to implement this and instead allows you to easily create adapters as services which are injected into our own `FilesystemProvider` using autoconfiguration.

Configuration example in `services.php`
```php
use League\Flysystem\Local\LocalFilesystemAdapter;
use Silverback\ApiComponentBundle\Flysystem\FilesystemProvider;

$services
        ->set(LocalFilesystemAdapter::class)
        ->args([
            '%kernel.project_dir%/var/storage/default'
        ])
        ->tag(FilesystemProvider::FILESYSTEM_ADAPTER_TAG, [ 'alias' => 'local' ]);
```

Or Yaml:
```yaml
app.flysystem.adapter.local:
    class: League\Flysystem\Local\LocalFilesystemAdapter
    arguments: ['%kernel.project_dir%/var/storage/default']
    tag:
      - { name: 'silverback.api_component.filesystem_adapter', alias: 'local' }
```

## Usage
The easiest way to configure an entity resource to contain uploads is to use the following annotation and trait:

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

> **The above will not do anything unless you then configure your File resources as described below. it simply defines the resource as a container for file resources. This gives flexibility to have multiple files associated with an API resource**

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

You do not need to use the traits and can define your own custom fields. Take a look at the annotation classes for the available configuration parameters, and you can base your updated class on the traits.

> **A file will have `MediaObject` resources appended to it with the IRI/Schema configured.**

You can configure your `File` object to use ImagineBundle filters. You will receive an additional `MediaObject` for every filter configured. The method `getImagineFilters` receives a `Request` object and can return different filters depending on the resource state. If the resource is not an image, this will be silently ignored.

```php
use Doctrine\Common\Collections\ArrayCollection;
use Silverback\ApiComponentBundle\Annotation as Silverback;
use Silverback\ApiComponentBundle\Entity\Utility\FileTrait;
use Silverback\ApiComponentBundle\Entity\Utility\ImagineFiltersInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Silverback\File(UploadableResource::class)
 */
class File implements ImagineFiltersInterface
{
    use FileTrait;

    public function __construct()
    {
        $this->mediaObjects = new ArrayCollection();
    }

    public function getImagineFilters(Request $request): array
    {
        return ['thumbnail', 'square_placeholder'];
    }
```
