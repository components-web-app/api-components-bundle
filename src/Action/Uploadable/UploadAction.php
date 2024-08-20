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
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\PublishableNormalizer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UploadAction
{
    public function __construct(private NormalizerInterface|PublishableNormalizer $publishableNormalizer)
    {
    }

    public function __invoke(?object $data, Request $request, UploadableFileManager $uploadableFileManager, PublishableStatusChecker $publishableStatusChecker)
    {
        $contentType = $request->headers->get('CONTENT_TYPE');
        if (null === $contentType) {
            throw new UnsupportedMediaTypeHttpException('The "Content-Type" header must exist.');
        }

        $contentType = explode(';', $contentType)[0];
        $formats = ['multipart/form-data'];
        if (!\in_array(strtolower($contentType), $formats, true)) {
            throw new UnsupportedMediaTypeHttpException(\sprintf('The content-type "%s" is not supported. Supported MIME type is "%s".', $contentType, implode('", "', $formats)));
        }

        $resourceClass = $request->attributes->get('_api_resource_class');
        $resource = $data ?? new $resourceClass();

        /**
         * if it IS publishable
         * if NOT asking to update published ?published=true
         * if it IS currently published
         * if the user DOES have permission.
         */
        $publishableAnnotationReader = $publishableStatusChecker->getAttributeReader();
        if ($publishableAnnotationReader->isConfigured($resource)) {
            $configuration = $publishableAnnotationReader->getConfiguration($resource);
            $isGranted = $publishableStatusChecker->isGranted($resource);
            if (!$data) {
                if (!$isGranted) {
                    $accessor = PropertyAccess::createPropertyAccessor();
                    $accessor->setValue($resource, $configuration->fieldName, date('Y-m-d H:i:s'));
                }
            } elseif (
                $isGranted
                && !$publishableStatusChecker->isRequestForPublished($request)
                && $publishableStatusChecker->isActivePublishedAt($resource)
            ) {
                $resource = $this->publishableNormalizer->createDraft($resource, $configuration, $resourceClass);
            }
        }

        try {
            $uploadableFileManager->setUploadedFilesFromFileBag($resource, $request->files);
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage());
        }

        $request->attributes->set('data', $resource);

        return $resource;
    }
}
