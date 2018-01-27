<?php

namespace Silverback\ApiComponentBundle\DependencyInjection;

use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Factory\Component\AbstractComponentFactory;
use Silverback\ApiComponentBundle\Factory\Component\ComponentFactoryInterface;
use Silverback\ApiComponentBundle\Form\FormTypeInterface;
use Silverback\ApiComponentBundle\Form\Handler\FormHandlerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

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
        // $loader->load('servicesComponentFactory.php');

        $container->registerForAutoconfiguration(FormHandlerInterface::class)
            ->addTag('silverback_api_component.form_handler')
            ->setLazy(true)
        ;

        $container->registerForAutoconfiguration(FormTypeInterface::class)
            ->addTag('silverback_api_component.form_type')
        ;

        $container->register(AbstractComponentFactory::class)
            ->setAbstract(true)
            ->addArgument(new Reference(ObjectManager::class))
        ;

        $container->registerForAutoconfiguration(ComponentFactoryInterface::class)
            ->setParent(AbstractComponentFactory::class)
        ;

        /*
         $services
        ->load('Silverback\\ApiComponentBundle\\Factory\\Component\\', '../../Factory/Component')
        ->exclude('../../Factory/Component/Item')
        ->call('load', [
            new Reference(ObjectManager::class)
        ])
    ;
         */
    }
}
