<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Loader\Configurator;

use Silverback\ApiComponentsBundle\Action\Health\HealthAction;

return static function (RoutingConfigurator $routes): void {
    $routes
        ->add('api_components_health', '/_/health')
        ->methods(['GET'])
        ->controller(HealthAction::class);
};
