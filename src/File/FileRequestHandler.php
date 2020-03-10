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

namespace Silverback\ApiComponentBundle\File;

use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use ApiPlatform\Core\Exception\ResourceClassNotSupportedException;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use Silverback\ApiComponentBundle\Entity\Utility\FileInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FileRequestHandler
{
    private UrlMatcherInterface $urlMatcher;
    private ItemDataProviderInterface $itemDataProvider;
    private FileUploader $fileUploader;
    private ResourceMetadataFactoryInterface $resourceMetadataFactory;
    private SerializerInterface $serializer;

    public function __construct(
        UrlMatcherInterface $urlMatcher,
        ItemDataProviderInterface $itemDataProvider,
        FileUploader $fileUploader,
        ResourceMetadataFactoryInterface $resourceMetadataFactory,
        SerializerInterface $serializer)
    {
        $this->urlMatcher = $urlMatcher;
        $this->itemDataProvider = $itemDataProvider;
        $this->fileUploader = $fileUploader;
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->serializer = $serializer;
    }

    public function handle(Request $request, ?string $_format, string $field, string $id): Response
    {
        try {
            $routeParams = $this->getRouteParamsByIri($request, $id);
            $entity = $this->getEntityByRouteParams($routeParams);

//            if (!$this->restrictedResourceVoter->vote($entity)) {
//                throw new AccessDeniedException('You are not permitted to download this file');
//            }

            if (Request::METHOD_GET === ($requestMethod = $request->getMethod())) {
                return $this->getFileResponse($entity, $field);
            }

            $resourceMetadata = $this->resourceMetadataFactory->create($routeParams['_api_resource_class']);
            $this->handleFileUpload($request, $entity, $field, $requestMethod);

            return $this->getSerializedResourceResponse($entity, $_format, $requestMethod, $resourceMetadata);
        } catch (\InvalidArgumentException $exception) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $exception) {
            return new Response($exception->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (ResourceClassNotFoundException $exception) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    private function getSerializedResourceResponse(FileInterface $entity, string $_format, string $requestMethod, ResourceMetadata $resourceMetadata): Response
    {
        $serializerGroups = $resourceMetadata->getOperationAttribute(
            ['item_operation_name' => $requestMethod],
            'serializer_groups',
            [],
            true
        );
//        $customGroups = $this->apiContextBuilder->getGroups($routeParams['_api_resource_class'], true);
//        if (\count($customGroups)) {
//            $serializerGroups = array_merge($serializerGroups ?? [], ...$customGroups);
//        }
        $serializedData = $this->serializer->serialize($entity, $_format, ['groups' => $serializerGroups]);

        return new Response($serializedData, Response::HTTP_OK);
    }

    private function handleFileUpload(Request $request, FileInterface $entity, string $field, string $requestMethod): void
    {
        if (!$request->files->count()) {
            throw new \InvalidArgumentException('No files have been submitted');
        }

        $files = $request->files->all();
        $this->fileUploader->upload($entity, $field, reset($files), $requestMethod);
    }

    private function getFileResponse(object $entity, string $field): BinaryFileResponse
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $filePath = $propertyAccessor->getValue($entity, $field);

        return new BinaryFileResponse($filePath);
    }

    private function getEntityByRouteParams(array $routeParams): FileInterface
    {
        $resourceClass = $routeParams['_api_resource_class'];
        $resourceId = $routeParams['id'];
        try {
            $entity = $this->itemDataProvider->getItem($resourceClass, $resourceId);
        } catch (ResourceClassNotSupportedException $exception) {
            throw new \InvalidArgumentException($exception->getMessage());
        }
        if (!$entity) {
            $message = sprintf('Entity not found from provider %s (ID: %s)', $resourceClass, $resourceId);
            throw new \InvalidArgumentException($message);
        }
        if (!($entity instanceof FileInterface)) {
            $message = sprintf('Provider %s does not implement %s', $resourceClass, FileInterface::class);
            throw new \InvalidArgumentException($message);
        }

        return $entity;
    }

    private function getRouteParamsByIri(Request $request, string $id): array
    {
        $ctx = new RequestContext();
        $ctx->fromRequest($request);
        $ctx->setMethod('GET');
        $this->urlMatcher->setContext($ctx);
        $route = $this->urlMatcher->match($id);
        if (empty($route) || !isset($route['_api_resource_class'])) {
            $message = sprintf('No route/resource found for id %s', $id);
            throw new \InvalidArgumentException($message);
        }

        return $route;
    }
}
