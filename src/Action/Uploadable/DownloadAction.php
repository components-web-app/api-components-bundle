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

use Silverback\ApiComponentsBundle\AnnotationReader\UploadableAnnotationReader;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Uploadable\UploadableHelper;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class DownloadAction
{
    public function __invoke(object $data, string $property, Request $request, UploadableAnnotationReader $annotationReader, UploadableHelper $uploadableHelper)
    {
        if (!$annotationReader->isConfigured($data)) {
            throw new InvalidArgumentException(sprintf('%s is not an uploadable resource. It should not be configured to use %s.', \get_class($data), __CLASS__));
        }

        try {
            $file = $uploadableHelper->getFile($data, $property);
        } catch (InvalidArgumentException $e) {
            // Property not configured, convert to not found URL
            throw new NotFoundHttpException($e->getMessage());
        }

        if (!$file) {
            throw new NotFoundHttpException('File not found.');
        }

        return new BinaryFileResponse($file);
    }
}
