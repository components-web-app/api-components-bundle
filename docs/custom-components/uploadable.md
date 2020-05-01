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
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;

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

Install the adapters you need from Flysystem and remember to use adapters supporting version 2 (e.g. `composer require league/flysystem-aws-s3-v3:^2`)

## Usage

The easiest way to configure an entity resource be an uploadable file is to use the following annotation and trait:

```php
use Silverback\ApiComponentsBundle\Entity\Utility\UploadableTrait;
use Silverback\ApiComponentsBundle\Annotation as Silverback;

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
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\Entity\Utility\UploadableTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\ImagineFiltersInterface;
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

## Updating your resource

You can, and usually should send your file to the API as a Base64 encoded string, adhering to the REST API standards. However, for every resource configured, you can also submit just your file to your resources endpoint and `/upload` appended. This endpoint accepts `multipart/form-data` mime type.

E.g. if your component's endpoint is `/component/image_uploadable_components` you can submit POST requests to `/component/image_uploadable_components/upload` and `PUT` or `PATCH` requests to `/component/image_uploadable_components/{id}/upload`

## Output

Your `Uploadable` resources will not return your configured file paths. These are for internal use and do not provide a publicly accessible endpoint to the files. Instead, we populate media objects into the returned metadata. There will be an array for every `UploadableField` configured. Here is an example of a resource just using the example configuration shown above.
```json
{
    "@context": "/contexts/DummyUploadable",
    "@id": "/component/dummy_uploadables/0a1b1d75c1114be285b037b4f8e0d6c4",
    "@type": "DummyUploadable",
    "_metadata": {
        "persisted": true,
        "media_objects": {
            "filename": [
                {
                    "@context": {
                        "@vocab": "http://example.com/docs.jsonld#",
                        "hydra": "http://www.w3.org/ns/hydra/core#",
                        "contentUrl": "http://schema.org/contentUrl",
                        "fileSize": "http://schema.org/contentSize",
                        "mimeType": "http://schema.org/encodingFormat",
                        "width": "http://schema.org/width",
                        "height": "http://schema.org/height",
                        "imagineFilter": "MediaObject/imagineFilter"
                    },
                    "@id": "/_/media_objects/124274e17a264763a61758c31462a259",
                    "@type": "http://schema.org/MediaObject",
                    "contentUrl": "https://www.website.com/path",
                    "fileSize": 632,
                    "mimeType": "octet/stream",
                    "width": 200,
                    "height": 100,
                    "imagineFilter": "filter_name",
                    "_metadata": {
                        "persisted": false
                    }
                }
            ]
        }
    }
    // More resource properties would appear here if you have set them. (e.g. description, type)
}
```

If the `MediaObject` is not an image, you will not receive with `width` or `height` properties. If the object is not a result of an ImagineBundle filter, you will not receive the property `imagineFilter`.

## Future features

The `@context` has been embedded into every MediaObject instance returned for your convenience. There is a pending feature that we would like to add where we could define a schema definition that is a subclass of `MediaObject` in the `@ApiProperty` annotation but this has not been developed yet.
