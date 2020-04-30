---
layout: default
title: Uploadable
parent: Custom Components
nav_order: 3
---
# Uploadable

## Requirements
This bundle uses FlySystem v2. It does not use additional bundles. Instead, you create your adapters as Symfony services with a specific tag, so that they are injected into the `FilesystemProvider`.

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
The easiest way to configure an entity resource be an uploadable file is to use the following annotation and trait:

```php
use Silverback\ApiComponentBundle\Entity\Utility\UploadableTrait;
use Silverback\ApiComponentBundle\Annotation as Silverback;

/**
 * @Silverback\Uploadable()
 */
class File
{
    use UploadableTrait;

    /** @Silverback\UploadableField(adapter="local") */
    public ?File $file;
```



> **A file will have `MediaObject` resources appended to it with the IRI/Schema configured.**

You can configure your `File` object to use ImagineBundle filters. You will receive an additional `MediaObject` for every filter configured. The method `getImagineFilters` receives a `Request` object and can return different filters depending on the resource state. If the resource is not an image, this will be silently ignored.

```php
use Doctrine\Common\Collections\ArrayCollection;
use Silverback\ApiComponentBundle\Annotation as Silverback;
use Silverback\ApiComponentBundle\Entity\Utility\UploadableTrait;
use Silverback\ApiComponentBundle\Entity\Utility\ImagineFiltersInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Silverback\Uploadable()
 */
class File implements ImagineFiltersInterface
{
    use UploadableTrait;

    public function __construct()
    {
        $this->mediaObjects = new ArrayCollection();
    }

    public function getImagineFilters(Request $request): array
    {
        return ['thumbnail', 'square_placeholder'];
    }
```
