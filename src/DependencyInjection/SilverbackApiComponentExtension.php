<?php

namespace Silverback\ApiComponentBundle\DependencyInjection;

use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Factory\Entity\AbstractFactory;
use Silverback\ApiComponentBundle\Factory\Entity\FactoryInterface;
use Silverback\ApiComponentBundle\Form\FormTypeInterface;
use Silverback\ApiComponentBundle\Form\Handler\FormHandlerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class SilverbackApiComponentExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @param array $configs
     * @param ContainerBuilder $container
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
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
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.php');

        $container->registerForAutoconfiguration(FormHandlerInterface::class)
            ->addTag('silverback_api_component.form_handler')
            ->setLazy(true);
        $container->registerForAutoconfiguration(FormTypeInterface::class)
            ->addTag('silverback_api_component.form_type');
        $container->registerForAutoconfiguration(FactoryInterface::class)
            ->setParent(AbstractFactory::class);
        $container->register(AbstractFactory::class)
            ->setAbstract(true)
            ->addArgument(new Reference(ObjectManager::class));
    }

    /**
     * @param ContainerBuilder $container
     * @throws \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    public function prepend(ContainerBuilder $container): void
    {
        $uploadsDir = $container->getParameter('kernel.project_dir') . '/var/uploads';
        if (!@mkdir($uploadsDir) && !is_dir($uploadsDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $uploadsDir));
        }

        $bundles = $container->getParameter('kernel.bundles');
        $container->prependExtensionConfig(
            'api_platform',
            [
                'eager_loading' => [
                    'force_eager' => false
                ]
            ]
        );
        if (isset($bundles['LiipImagineBundle'])) {
            $container->prependExtensionConfig(
                'liip_imagine',
                [
                    'loaders' => [
                        'default' => [
                            'filesystem' => [
                                'data_root' => [
                                    'uploads' => $uploadsDir,
                                    'default' => $container->getParameter('kernel.project_dir') . '/public'
                                ]
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
                ]
            );
        }
    }
}
