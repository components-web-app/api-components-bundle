<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\DependencyInjection;

use Silverback\ApiComponentBundle\DataModifier\DataModifierInterface;
use Silverback\ApiComponentBundle\Filter\Doctrine\PublishableFilter;
use Silverback\ApiComponentBundle\Form\FormTypeInterface;
use Silverback\ApiComponentBundle\Form\Handler\FormHandlerInterface;
use Silverback\ApiComponentBundle\Mailer\Mailer;
use Silverback\ApiComponentBundle\Repository\User\UserRepository;
use Silverback\ApiComponentBundle\Security\PasswordManager;
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
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->loadServiceConfig($container);

        $repeatTtl = $config['password_reset']['repeat_ttl_seconds'];
        $timeoutSeconds = $config['password_reset']['request_timeout_seconds'];

        $definition = $container->getDefinition(Mailer::class);
        $definition->setArgument('$logoSrc', $config['mailer']['logo_src']);
        $definition->setArgument('$websiteName', $config['mailer']['website_name']);
        $definition->setArgument('$requestTimeout', $timeoutSeconds);

        $definition = $container->getDefinition(PasswordManager::class);
        $definition->setArgument('$tokenTtl', $repeatTtl);

        $definition = $container->getDefinition(UserRepository::class);
        $definition->setArgument('$passwordRequestTimeout', $timeoutSeconds);
    }

    /**
     * @param ContainerBuilder $container
     */
    private function loadServiceConfig(ContainerBuilder $container)
    {
        $container->registerForAutoconfiguration(FormHandlerInterface::class)
            ->addTag('silverback_api_component.form_handler')
            ->setLazy(true);

        $container->registerForAutoconfiguration(DataModifierInterface::class)
            ->addTag('silverback_api_component.serializer_middleware');

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
        if (isset($bundles['DoctrineBundle'])) {
            $container->prependExtensionConfig(
                'doctrine',
                [
                    'orm' => [
                        'filters' => [
                            'publishable' => [
                                'class' => PublishableFilter::class,
                                'enabled' => false
                            ]
                        ]
                    ]
                ]
            );
        }

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
}
