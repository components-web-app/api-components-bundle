<?php

namespace Silverback\ApiComponentBundle\Resources\config;

use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

return function (ContainerConfigurator $configurator) {
    $services = $configurator->services();

    $services
        ->load('Silverback\\ApiComponentBundle\\Factory\\Component\\', '../../Factory/Component')
        ->exclude('../../Factory/Component/Item')
        ->call('load', [
            new Reference(ObjectManager::class)
        ])
    ;
};
