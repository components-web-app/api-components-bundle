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

namespace Silverback\ApiComponentsBundle\OpenApi;

use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\Core\OpenApi\Model\PathItem;
use ApiPlatform\Core\OpenApi\OpenApi;
use PackageVersions\Versions;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Model\Uploadable\MediaObject;
use Symfony\Component\Form\Forms;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class OpenApiFactory implements OpenApiFactoryInterface
{
    private OpenApiFactoryInterface $decorated;
    private ResourceMetadataFactoryInterface $resourceMetadataFactory;

    public function __construct(OpenApiFactoryInterface $decorated, ResourceMetadataFactoryInterface $resourceMetadataFactory)
    {
        $this->decorated = $decorated;
        $this->resourceMetadataFactory = $resourceMetadataFactory;
    }

    private function getResourceClassShortNames(array $resourceClassNames): array
    {
        $shortNames = [];
        foreach ($resourceClassNames as $resourceClassName) {
            try {
                $metadata = $this->resourceMetadataFactory->create($resourceClassName);
                $shortNames[] = $metadata->getShortName();
            } catch (ResourceClassNotFoundException $exception) {
                // the component may not be enabled
            }
        }

        return $shortNames;
    }

    private function removeResources(OpenApi $openApi, array $resourceClassNames): void
    {
        $shortNames = $this->getResourceClassShortNames($resourceClassNames);
        $openApiPaths = $openApi->getPaths();
        $paths = $openApiPaths->getPaths();
        foreach ($paths as $path => $pathItem) {
            $operation = $pathItem->getGet() ?: $pathItem->getPost();
            if (!$operation) {
                continue;
            }
            $tags = $operation->getTags();
            if (!empty(array_intersect($tags, $shortNames))) {
                $openApiPaths->addPath($path, new PathItem());
            }
        }
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = $this->decorated->__invoke($context);
        $version = sprintf('%s (%s)', $openApi->getInfo()->getVersion(), Versions::getVersion('silverbackis/api-components-bundle'));

        $this->removeResources($openApi, [
            AbstractComponent::class,
            AbstractPageData::class,
            Forms::class,
            MediaObject::class,
        ]);

        return $openApi->withInfo($openApi->getInfo()->withVersion($version));
    }
}
