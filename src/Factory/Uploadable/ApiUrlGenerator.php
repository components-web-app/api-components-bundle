<?php

namespace Silverback\ApiComponentsBundle\Factory\Uploadable;

use ApiPlatform\Api\IriConverterInterface;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class ApiUrlGenerator implements UploadableUrlGenerator
{
    public function __construct(private readonly IriConverterInterface $iriConverter, private readonly UrlHelper $urlHelper)
    {}

    public function generateUrl(object $object, string $fileProperty): string {
        $resourceId = $this->iriConverter->getIriFromResource($object);
        $converter = new CamelCaseToSnakeCaseNameConverter();
        return $this->urlHelper->getAbsoluteUrl(sprintf('%s/download/%s', $resourceId, $converter->normalize($fileProperty)));
    }
}
