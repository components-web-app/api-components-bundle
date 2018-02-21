<?php

namespace Silverback\ApiComponentBundle\DependencyInjection;

use Doctrine\Common\Persistence\ObjectManager;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Component\ComponentInterface;
use Silverback\ApiComponentBundle\Entity\Navigation\Route\RouteAwareInterface;
use Silverback\ApiComponentBundle\Factory\Component\AbstractComponentFactory;
use Silverback\ApiComponentBundle\Factory\Component\ComponentFactoryInterface;
use Silverback\ApiComponentBundle\Form\FormTypeInterface;
use Silverback\ApiComponentBundle\Form\Handler\FormHandlerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class SilverbackApiComponentExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @param array $configs
     * @param ContainerBuilder $container
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $this->loadServiceConfig($container);
    }

    /**
     * @param ContainerBuilder $container
     * @throws \Exception
     */
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

        $container->registerForAutoconfiguration(FormTypeInterface::class)
            ->addTag('silverback_api_component.form_type')
        ;

        $container->registerForAutoconfiguration(ComponentInterface::class)
            ->addTag('silverback_api_component.component')
        ;

        $container->registerForAutoconfiguration(ComponentFactoryInterface::class)
            ->setParent(AbstractComponentFactory::class)
        ;

        $container->register(AbstractComponentFactory::class)
            ->setAbstract(true)
            ->addArgument(new Reference(ObjectManager::class))
        ;
    }

    /**
     * @param ContainerBuilder $container
     * @throws \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    public function prepend(ContainerBuilder $container)
    {
        $bundles = $container->getParameter('kernel.bundles');
        if (isset($bundles['LiipImagineBundle'])) {
            $container->prependExtensionConfig('liip_imagine', [
                'loaders' => [
                    'default' => [
                        'filesystem' => [
                            'data_root' => '%kernel.project_dir%/public'
                        ]
                    ]
                ],
                'filter_sets' => [
                    'placeholder_square' => [
                        'jpeg_quality' => 10,
                        'png_compression_level' => 9,
                        'filters' => [
                            'thumbnail' => [
                                'size' => [80, 80],
                                'mode' => 'outbound'
                            ]
                        ]
                    ],
                    'placeholder' => [
                        'jpeg_quality' => 10,
                        'png_compression_level' => 9,
                        'filters' => [
                            'thumbnail' => [
                                'size' => [100, 100],
                                'mode' => 'inset'
                            ]
                        ]
                    ],
                    'thumbnail' => [
                        'jpeg_quality' => 95,
                        'filters' => [
                            'upscale' => [
                                'min' => [636, 636]
                            ],
                            'thumbnail' => [
                                'size' => [636, 636],
                                'mode' => 'inset',
                                'allow_upscale' => true
                            ]
                        ]
                    ]
                ]
            ]);
        }
    }
}
