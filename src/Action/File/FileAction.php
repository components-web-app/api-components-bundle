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

use Silverback\ApiComponentBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentBundle\Helper\FileHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FileAction
{
    public function __invoke(Request $request, FileHelper $fileHelper)
    {
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
