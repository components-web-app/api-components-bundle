<?php

namespace Silverback\ApiComponentBundle\DependencyInjection;

use Silverback\ApiComponentBundle\Form\Handler\FormHandlerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class SilverbackApiComponentExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $this->loadServiceConfig($container);
    }

    private function loadServiceConfig(ContainerBuilder $container)
    {
        $loader = new PhpFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );
        $loader->load('services.php');

        $container->registerForAutoconfiguration(FormHandlerInterface::class)
            ->addTag('silverback_api_component.form_handler')
            ->setLazy(true)
        ;
    }
}
