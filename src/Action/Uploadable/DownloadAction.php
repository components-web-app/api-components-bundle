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
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableHelper;
use Symfony\Component\HttpFoundation\Request;

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

        return $uploadableHelper->getFileResponse($data, $property, $request->query->getBoolean('download', false));
    }
}
