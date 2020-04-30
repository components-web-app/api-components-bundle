<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Action\File;

use ApiPlatform\Core\Api\FormatMatcher;
use Silverback\ApiComponentBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentBundle\Helper\FileHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FileAction
{
    public function __invoke(Request $request, FileHelper $fileHelper)
    {
        $contentType = $request->headers->get('CONTENT_TYPE');
        if (null === $contentType) {
            throw new UnsupportedMediaTypeHttpException('The "Content-Type" header must exist.');
        }

        $formats = ['form-data' => ['multipart/form-data']];
        $formatMatcher = new FormatMatcher($formats);
        $format = $formatMatcher->getFormat($contentType);
        if (null === $format) {
            $supportedMimeTypes = [];
            foreach ($formats as $mimeTypes) {
                foreach ($mimeTypes as $mimeType) {
                    $supportedMimeTypes[] = $mimeType;
                }
            }
            throw new UnsupportedMediaTypeHttpException(sprintf('The content-type "%s" is not supported. Supported MIME type is "%s".', $contentType, implode('", "', $supportedMimeTypes)));
        }

        $resourceClass = $request->attributes->get('_api_resource_class');
        $resource = new $resourceClass();
        try {
            $fileHelper->setUploadedFile($resource, $request->files);
        } catch (InvalidArgumentException $exception) {
            throw new BadRequestHttpException($exception->getMessage());
        }

        return $resource;
    }
}
