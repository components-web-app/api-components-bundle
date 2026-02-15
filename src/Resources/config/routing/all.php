<?php

namespace Symfony\Component\Routing\Loader\Configurator;

return static function (RoutingConfigurator $routes): void {
    $routes->import('@SilverbackApiComponentsBundle/Resources/config/routing/security.php');
};
