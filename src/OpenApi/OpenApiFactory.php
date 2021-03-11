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

use ApiPlatform\Core\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\Core\OpenApi\OpenApi;
use PackageVersions\Versions;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class OpenApiFactory implements OpenApiFactoryInterface
{
    private OpenApiFactoryInterface $decorated;

    public function __construct(OpenApiFactoryInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    private function removePath(OpenApi $openApi, string $path): void
    {
        $pathItem = $openApi->getPaths()->getPath($path);
        if ($pathItem) {
            $openApi->getPaths()->addPath(
                $path,
                $pathItem->withGet(null)->withPut(null)->withPost(null)->withDelete(null)->withPatch(null)
            );
        }
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = $this->decorated->__invoke($context);
        $version = sprintf('%s (%s)', $openApi->getInfo()->getVersion(), Versions::getVersion('silverbackis/api-components-bundle'));

        $this->removePath($openApi, '/_/abstract_components/{id}');
        $this->removePath($openApi, '/component/forms/{id}');

        return $openApi->withInfo($openApi->getInfo()->withVersion($version));
    }
}
