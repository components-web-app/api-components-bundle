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

namespace Silverback\ApiComponentsBundle\Action\Uploadable;

use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Uploadable\UploadableHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UploadableAction
{
    public function __invoke(?object $data, Request $request, UploadableHelper $uploadableHelper)
    {
        $contentType = $request->headers->get('CONTENT_TYPE');
        if (null === $contentType) {
            throw new UnsupportedMediaTypeHttpException('The "Content-Type" header must exist.');
        }

        $formats = ['multipart/form-data'];
        if (!\in_array(strtolower($contentType), $formats, true)) {
            throw new UnsupportedMediaTypeHttpException(sprintf('The content-type "%s" is not supported. Supported MIME type is "%s".', $contentType, implode('", "', $formats)));
        }

        $resourceClass = $request->attributes->get('_api_resource_class');
        $resource = $data ?? new $resourceClass();

        try {
            $uploadableHelper->setUploadedFilesFromFileBag($resource, $request->files);
        } catch (InvalidArgumentException $exception) {
            throw new BadRequestHttpException($exception->getMessage());
        }

        return $resource;
    }
}
