<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Factory\Uploadable;

use ApiPlatform\Api\IriConverterInterface;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class ApiUrlGenerator implements UploadableUrlGenerator
{
    public function __construct(private readonly IriConverterInterface $iriConverter, private readonly UrlHelper $urlHelper)
    {
    }

    public function generateUrl(object $object, string $fileProperty): string
    {
        $resourceId = $this->iriConverter->getIriFromResource($object);
        $converter = new CamelCaseToSnakeCaseNameConverter();

        return $this->urlHelper->getAbsoluteUrl(sprintf('%s/download/%s', $resourceId, $converter->normalize($fileProperty)));
    }
}
