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
use Silverback\ApiComponentsBundle\Entity\Component\Form;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class OpenApiFactory implements OpenApiFactoryInterface
{
    private $decorated;

    public function __construct(OpenApiFactoryInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = $this->decorated->__invoke($context);
        $version = sprintf('%s (%s)', $openApi->getInfo()->getVersion(), Versions::getVersion('silverbackis/api-components-bundle'));

        return $openApi->withInfo($openApi->getInfo()->withVersion($version));
//        $components = $openApi->getComponents();
//        $classes = [];
//        $unsupported = [Form::class, AbstractComponent::class];
//        foreach ($components as $className => $component) {
//            if (\in_array($className, $unsupported, true)) {
//                continue;
//            }
//            $classes[] = $className;
//        }
//        $newResourceNameCollection = new ResourceNameCollection($classes);
    }
}
