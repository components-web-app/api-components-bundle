<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Resources\config;

use Silverback\ApiComponentsBundle\Maker\MakeApiComponent;
use Silverback\ApiComponentsBundle\Maker\MakeCwaScaffold;
use Silverback\ApiComponentsBundle\Maker\MakePageData;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('silverback.api_components.maker.make_api_component', MakeApiComponent::class)
        ->tag('maker.command')
        ->public();

    $services->alias(MakeApiComponent::class, 'silverback.api_components.maker.make_api_component');

    $services->set('silverback.api_components.maker.make_page_data', MakePageData::class)
        ->tag('maker.command')
        ->public();

    $services->alias(MakePageData::class, 'silverback.api_components.maker.make_page_data');

    $services->set('silverback.api_components.maker.make_cwa_scaffold', MakeCwaScaffold::class)
        ->tag('maker.command')
        ->public();

    $services->alias(MakeCwaScaffold::class, 'silverback.api_components.maker.make_cwa_scaffold');
};
