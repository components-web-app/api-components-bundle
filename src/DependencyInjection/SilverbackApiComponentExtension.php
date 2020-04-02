<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\DependencyInjection;

use Exception;
use RuntimeException;
use Silverback\ApiComponentBundle\Doctrine\Extension\TablePrefixExtension;
use Silverback\ApiComponentBundle\Entity\Core\ComponentInterface;
use Silverback\ApiComponentBundle\Form\FormTypeInterface;
use Silverback\ApiComponentBundle\Mailer\UserMailer;
use Silverback\ApiComponentBundle\Repository\User\UserRepository;
use Silverback\ApiComponentBundle\Security\PasswordManager;
use Silverback\ApiComponentBundle\Security\TokenAuthenticator;
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
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->loadServiceConfig($container);

        $repeatTtl = $config['user']['password_reset']['repeat_ttl_seconds'];
        $timeoutSeconds = $config['user']['password_reset']['request_timeout_seconds'];

        $definition = $container->getDefinition(TablePrefixExtension::class);
        $definition->setArgument('$prefix', $config['table_prefix']);

        $definition = $container->getDefinition(PasswordManager::class);
        $definition->setArgument('$tokenTtl', $repeatTtl);

        $definition = $container->getDefinition(TokenAuthenticator::class);
        $definition->setArgument('$tokens', $config['security']['tokens']);

        $definition = $container->getDefinition(UserRepository::class);
        $definition->setArgument('$passwordRequestTimeout', $timeoutSeconds);
        $definition->setArgument('$entityClass', $config['user']['class_name']);

        $definition = $container->getDefinition(UserMailer::class);
        $definition->setArgument('$websiteName', $config['website_name']);
        $definition->setArgument('$defaultPasswordResetPath', $config['user']['password_reset']['default_reset_path']);
        $definition->setArgument('$defaultChangeEmailVerifyPath', $config['user']['change_email_address']['default_verify_path']);
        $definition->setArgument('$sendUserWelcomeEmailEnabled', $config['user']['emails']['user_welcome']);
        $definition->setArgument('$sendUserEnabledEmailEnabled', $config['user']['emails']['user_enabled']);
        $definition->setArgument('$sendUserUsernameChangedEmailEnabled', $config['user']['emails']['user_username_changed']);
        $definition->setArgument('$sendUserPasswordChangedEmailEnabled', $config['user']['emails']['user_password_changed']);
    }

    /**
     * @throws Exception
     */
    private function loadServiceConfig(ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(FormTypeInterface::class)
            ->addTag('silverback_api_component.form_type');

        $container->registerForAutoconfiguration(ComponentInterface::class)
            ->addTag('silverback_api_component.entity.component');

        $loader = new PhpFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.php');
    }

    public function prepend(ContainerBuilder $container): void
    {
        $configs = $container->getExtensionConfig($this->getAlias());
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        $configBasePath = '%kernel.project_dir%/vendor/silverbackis/api-component-bundle/src/Resources/config/api_platform/';
        $mappingPaths = [];
        foreach ($config['enabled_components'] as $key => $enabled_component) {
            if (true === $enabled_component) {
                $mappingPaths[] = sprintf('%s%s.yaml', $configBasePath, $key);
            }
        }
        $websiteName = $config['website_name'];
        $container->prependExtensionConfig(
            'api_platform',
            [
                'title' => $websiteName,
                'description' => sprintf('API for %s', $websiteName),
                'collection' => [
                    'pagination' => [
                        'client_items_per_page' => true,
                        'items_per_page_parameter_name' => 'perPage',
                        'maximum_items_per_page' => 100,
                    ],
                ],
                'mapping' => [
                    'paths' => $mappingPaths,
                ],
                'swagger' => [
                    'api_keys' => [
                        'API Token' => [
                            'name' => 'X-AUTH-TOKEN',
                            'type' => 'header',
                        ],
                        'JWT (use prefix `Bearer`)' => [
                            'name' => 'Authorization',
                            'type' => 'header',
                        ],
                    ],
                ],
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
                                'default' => $projectDir . '/public',
                            ],
                        ],
                    ],
                ],
                'filter_sets' => [
                    'placeholder_square' => [
                        'jpeg_quality' => 10,
                        'png_compression_level' => 9,
                        'filters' => [
                            'thumbnail' => [
                                'size' => [80, 80],
                                'mode' => 'outbound',
                            ],
                        ],
                    ],
                    'placeholder' => [
                        'jpeg_quality' => 10,
                        'png_compression_level' => 9,
                        'filters' => [
                            'thumbnail' => [
                                'size' => [100, 100],
                                'mode' => 'inset',
                            ],
                        ],
                    ],
                    'thumbnail' => [
                        'jpeg_quality' => 100,
                        'png_compression_level' => 0,
                        'filters' => [
                            'upscale' => [
                                'min' => [636, 636],
                            ],
                            'thumbnail' => [
                                'size' => [636, 636],
                                'mode' => 'inset',
                                'allow_upscale' => true,
                            ],
                        ],
                    ],
                ],
            ]
        );
    }
}
