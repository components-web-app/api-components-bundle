<?php

namespace Silverback\ApiComponentBundle\Controller;

use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use InvalidArgumentException;
use RuntimeException;
use Silverback\ApiComponentBundle\Entity\Component\FileInterface;
use Silverback\ApiComponentBundle\File\Uploader\FileUploader;
use Silverback\ApiComponentBundle\Serializer\ApiContextBuilder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Serializer\SerializerInterface;

class FileUploadAction
{
    private $urlMatcher;
    private $itemDataProvider;
    private $uploader;
    private $serializer;
    private $resourceMetadataFactory;
    private $apiContextBuilder;

    public function __construct(
        UrlMatcherInterface $urlMatcher,
        ItemDataProviderInterface $itemDataProvider,
        FileUploader $uploader,
        SerializerInterface $serializer,
        ResourceMetadataFactoryInterface $resourceMetadataFactory,
        ApiContextBuilder $apiContextBuilder
    ) {
        $this->urlMatcher = $urlMatcher;
        $this->itemDataProvider = $itemDataProvider;
        $this->uploader = $uploader;
        $this->serializer = $serializer;
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->apiContextBuilder = $apiContextBuilder;
    }

    /**
     * @param Request $request
     * @param string $field
     * @param string $id
     * @Route(
     *     name="files_upload",
     *     path="/files/{field}/{id}.{_format}",
     *     requirements={"field"="\w+", "id"=".+"},
     *     defaults={"_format"="jsonld"},
     *     methods={"POST", "PUT", "GET"}
     * )
     * @return Response
     */
    public function __invoke(Request $request, string $field, string $id)
    {
        $contentType = $request->headers->get('CONTENT_TYPE');
        $_format = $request->attributes->get('_format') ?: $request->getFormat($contentType);

        /**
         * MATCH THE ID TO A ROUTE TO FIND RESOURCE CLASS AND ID
         * @var array|null $route
         */
        $ctx = new RequestContext();
        $ctx->fromRequest($request);
        $ctx->setMethod('GET');
        $this->urlMatcher->setContext($ctx);
        $route = $this->urlMatcher->match($id);
        if (empty($route)) {
            return new Response(sprintf('No route found for id %s', $id), Response::HTTP_BAD_REQUEST);
        }

        /**
         * GET THE ENTITY
         */
        $entity = $this->itemDataProvider->getItem($route['_api_resource_class'], $route['id']);
        if (!$entity) {
            return new Response(sprintf('Entity not found from provider %s (ID: %s)', $route['_api_resource_class'], $route['id']), Response::HTTP_BAD_REQUEST);
        }
        if (!($entity instanceof FileInterface)) {
            return new Response(sprintf('Provider %s does not implement %s', $route['_api_resource_class'], FileInterface::class), Response::HTTP_BAD_REQUEST);
        }
        $method = strtolower($request->getMethod());

        if ($method === 'get') {
            $propertyAccessor = PropertyAccess::createPropertyAccessor();
            $filePath = $propertyAccessor->getValue($entity, $field);
            return new BinaryFileResponse($filePath);
        }

        /**
         * CHECK WE HAVE A FILE - WASTE OF TIME DOING ANYTHING ELSE OTHERWISE
         */
        if (!$request->files->count()) {
            return new Response('No files have been submitted', Response::HTTP_BAD_REQUEST);
        }

        /**
         * UPLOAD THE FILE
         */
        $files = $request->files->all();
        try {
            $entity = $this->uploader->upload($entity, $field, reset($files), $method);
        } catch (InvalidArgumentException $exception) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (RuntimeException $exception) {
            return new Response($exception->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        /**
         * Return the entity back in the format requested
         */
        $resourceMetadata = $this->resourceMetadataFactory->create($route['_api_resource_class']);
        $serializerGroups = $resourceMetadata->getOperationAttribute(
            ['item_operation_name' => $method],
            'serializer_groups',
            [],
            true
        );
        $customGroups = $this->apiContextBuilder->getGroups($route['_api_resource_class'], true);
        if (\count($customGroups)) {
            $serializerGroups = array_merge($serializerGroups ?? [], ...$customGroups);
        }
        $serializedData = $this->serializer->serialize($entity, $_format, ['groups' => $serializerGroups]);
        return new Response($serializedData, Response::HTTP_OK);
    }
}
