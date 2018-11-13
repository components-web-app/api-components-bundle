<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\DependencyInjection;

use Silverback\ApiComponentBundle\Form\FormTypeInterface;
use Silverback\ApiComponentBundle\Form\Handler\FormHandlerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

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
        $container->registerForAutoconfiguration(FormHandlerInterface::class)
            ->addTag('silverback_api_component.form_handler')
            ->setLazy(true);

        $container->registerForAutoconfiguration(FormTypeInterface::class)
            ->addTag('silverback_api_component.form_type');

        $loader = new PhpFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.php');
    }

    /**
     * @param ContainerBuilder $container
     * @throws \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig(
            'api_platform',
            [
                'eager_loading' => [
                    'force_eager' => false
                ]
            ]
        );

        $bundles = $container->getParameter('kernel.bundles');
        if (isset($bundles['LiipImagineBundle'])) {
            $uploadsDir = $container->getParameter('kernel.project_dir') . '/var/uploads';
            if (!@mkdir($uploadsDir) && !is_dir($uploadsDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $uploadsDir));
            }

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
