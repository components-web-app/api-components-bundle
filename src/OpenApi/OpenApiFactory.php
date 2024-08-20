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

use ApiPlatform\Exception\ResourceClassNotFoundException as LegacyResourceClassNotFoundException;
use ApiPlatform\Metadata\Exception\ResourceClassNotFoundException;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\OpenApi;
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
    private ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory;

    public function __construct(OpenApiFactoryInterface $decorated, ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory)
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
                foreach ($metadata as $metadatum) {
                    $shortNames[] = $metadatum->getShortName();
                }
            } catch (LegacyResourceClassNotFoundException|ResourceClassNotFoundException $exception) {
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

    public static function getExtendedVersion(string $version): string
    {
        return \sprintf('%s (%s)', $version, Versions::getVersion('components-web-app/api-components-bundle'));
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = $this->decorated->__invoke($context);
        $version = self::getExtendedVersion($openApi->getInfo()->getVersion());

        $this->removeResources($openApi, [
            AbstractComponent::class,
            AbstractPageData::class,
            Forms::class,
            MediaObject::class,
        ]);

        return $openApi->withInfo($openApi->getInfo()->withVersion($version));
    }
}
