<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\DependencyInjection;

use Exception;
use RuntimeException;
use Silverback\ApiComponentBundle\Doctrine\Extension\TablePrefixExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class SilverbackApiComponentExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @param array $configs
     * @param ContainerBuilder $container
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->loadServiceConfig($container);

        $definition = $container->getDefinition(TablePrefixExtension::class);
        $definition->setArgument('$prefix', $config['table_prefix']);
    }

    /**
     * @throws Exception
     */
    private function loadServiceConfig(ContainerBuilder $container): void
    {
//        $container->registerForAutoconfiguration(FormHandlerInterface::class)
//            ->addTag('silverback_api_component.form_handler')
//            ->setLazy(true);
//
//        $container->registerForAutoconfiguration(DataTransformerInterface::class)
//            ->addTag('silverback_api_component.data_transformer')
//        ;
//
//        $container->registerForAutoconfiguration(FormTypeInterface::class)
//            ->addTag('silverback_api_component.form_type');
//
//        $container->registerForAutoconfiguration(ComponentInterface::class)
//            ->addTag('silverback_api_component.entity.component');

        $loader = new PhpFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.php');
    }

    /**
     * @param ContainerBuilder $container
     */
    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig(
            'api_platform',
            [
//                'eager_loading' => [
//                    'force_eager' => false
//                ]
//                'mapping' => [
//                    'paths' => [
//                        __DIR__ . '/../Entity'
//                    ]
//                ]
            ]
        );

        $bundles = $container->getParameter('kernel.bundles');
        if (isset($bundles['DoctrineBundle'])) {
            $this->prependDoctrineConfig($container);
        }
        if (isset($bundles['LiipImagineBundle'])) {
            $this->prependLiipConfig($container);
        }
    }

    private function prependDoctrineConfig(ContainerBuilder $container)
    {
        $container->prependExtensionConfig(
            'doctrine',
            [
//                'orm' => [
//                    'filters' => [
//                        'publishable' => [
//                            'class' => PublishableFilter::class,
//                            'enabled' => false
//                        ]
//                    ]
//                ]
            ]
        );
    }

    private function prependLiipConfig(ContainerBuilder $container)
    {
        $projectDir = $container->getParameter('kernel.project_dir');
        $uploadsDir = $projectDir . '/var/uploads';
        if (!@mkdir($uploadsDir) && !is_dir($uploadsDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $uploadsDir));
        }
        $container->prependExtensionConfig(
            'liip_imagine',
            [
                'loaders' => [
                    'default' => [
                        'filesystem' => [
                            'data_root' => [
                                'uploads' => $uploadsDir,
                                'default' => $projectDir. '/public'
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
                        'jpeg_quality' => 100,
                        'png_compression_level' => 0,
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
