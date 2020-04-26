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
use Silverback\ApiComponentBundle\Entity\Core\ComponentInterface;
use Silverback\ApiComponentBundle\EventListener\Doctrine\UserListener;
use Silverback\ApiComponentBundle\Extension\Doctrine\ORM\TablePrefixExtension;
use Silverback\ApiComponentBundle\Factory\Mailer\User\ChangeEmailVerificationEmailFactory;
use Silverback\ApiComponentBundle\Factory\Mailer\User\PasswordChangedEmailFactory;
use Silverback\ApiComponentBundle\Factory\Mailer\User\PasswordResetEmailFactory;
use Silverback\ApiComponentBundle\Factory\Mailer\User\UserEnabledEmailFactory;
use Silverback\ApiComponentBundle\Factory\Mailer\User\UsernameChangedEmailFactory;
use Silverback\ApiComponentBundle\Factory\Mailer\User\WelcomeEmailFactory;
use Silverback\ApiComponentBundle\Factory\User\UserFactory;
use Silverback\ApiComponentBundle\Form\FormTypeInterface;
use Silverback\ApiComponentBundle\Form\Type\User\ChangePasswordType;
use Silverback\ApiComponentBundle\Form\Type\User\NewEmailAddressType;
use Silverback\ApiComponentBundle\Form\Type\User\UserRegisterType;
use Silverback\ApiComponentBundle\Mailer\UserMailer;
use Silverback\ApiComponentBundle\Manager\User\PasswordManager;
use Silverback\ApiComponentBundle\Publishable\PublishableHelper;
use Silverback\ApiComponentBundle\Repository\User\UserRepository;
use Silverback\ApiComponentBundle\Security\TokenAuthenticator;
use Silverback\ApiComponentBundle\Security\UserChecker;
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

        $definition = $container->getDefinition(TablePrefixExtension::class);
        $definition->setArgument('$prefix', $config['table_prefix']);

        $definition = $container->getDefinition(TokenAuthenticator::class);
        $definition->setArgument('$tokens', $config['security']['tokens']);

        $definition = $container->getDefinition(PasswordManager::class);
        $definition->setArgument('$tokenTtl', $config['user']['password_reset']['repeat_ttl_seconds']);

        $definition = $container->getDefinition(UserRepository::class);
        $definition->setArgument('$passwordRequestTimeout', $config['user']['password_reset']['request_timeout_seconds']);
        $definition->setArgument('$entityClass', $config['user']['class_name']);

        $definition = $container->getDefinition(PublishableHelper::class);
        $definition->setArgument('$permission', $config['publishable']['permission']);

        $this->setEmailVerificationArguments($container, $config['user']['email_verification']);
        $this->setUserClassArguments($container, $config['user']['class_name']);
        $this->setMailerServiceArguments($container, $config);
    }

    private function setEmailVerificationArguments(ContainerBuilder $container, array $emailVerificationConfig): void
    {
        $definition = $container->getDefinition(UserChecker::class);
        $definition->setArgument('$denyUnverifiedLogin', $emailVerificationConfig['deny_unverified_login']);

        $definition = $container->getDefinition(UserListener::class);
        $definition->setArgument('$initialEmailVerifiedState', $emailVerificationConfig['default_value']);
        $definition->setArgument('$verifyEmailOnRegister', $emailVerificationConfig['verify_on_register']);
        $definition->setArgument('$verifyEmailOnChange', $emailVerificationConfig['verify_on_change']);
    }

    private function setUserClassArguments(ContainerBuilder $container, string $userClass): void
    {
        $definition = $container->getDefinition(UserFactory::class);
        $definition->setArgument('$userClass', $userClass);

        $definition = $container->getDefinition(ChangePasswordType::class);
        $definition->setArgument('$userClass', $userClass);

        $definition = $container->getDefinition(NewEmailAddressType::class);
        $definition->setArgument('$userClass', $userClass);

        $definition = $container->getDefinition(UserRegisterType::class);
        $definition->setArgument('$userClass', $userClass);
    }

    private function setMailerServiceArguments(ContainerBuilder $container, array $config): void
    {
        $definition = $container->getDefinition(UserMailer::class);
        $definition->setArgument('$context', [
            'website_name' => $config['website_name'],
        ]);

        $mapping = [
            PasswordChangedEmailFactory::class => 'password_changed',
            UserEnabledEmailFactory::class => 'user_enabled',
            UsernameChangedEmailFactory::class => 'username_changed',
            WelcomeEmailFactory::class => 'welcome',
        ];
        foreach ($mapping as $class => $key) {
            $definition = $container->getDefinition($class);
            $definition->setArgument('$subject', $config['user']['emails'][$key]['subject']);
            $definition->setArgument('$enabled', $config['user']['emails'][$key]['enabled']);
            if (WelcomeEmailFactory::class === $class) {
                $definition->setArgument('$defaultRedirectPath', $config['user']['email_verification']['email']['default_redirect_path']);
                $definition->setArgument('$redirectPathQueryKey', $config['user']['email_verification']['email']['redirect_path_query']);
            }
        }

        $mapping = [
            ChangeEmailVerificationEmailFactory::class => 'email_verification',
            PasswordResetEmailFactory::class => 'password_reset',
        ];
        foreach ($mapping as $class => $key) {
            $definition = $container->getDefinition($class);
            $definition->setArgument('$subject', $config['user'][$key]['email']['subject']);
            $definition->setArgument('$enabled', true);
            $definition->setArgument('$defaultRedirectPath', $config['user'][$key]['email']['default_redirect_path']);
            $definition->setArgument('$redirectPathQueryKey', $config['user'][$key]['email']['redirect_path_query']);
        }
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
        $srcBase = __DIR__ . '/..';
        $configBasePath = $srcBase . '/Resources/config/api_platform';
        $mappingPaths = [$srcBase . '/Entity/Core'];
        foreach ($config['enabled_components'] as $key => $enabled_component) {
            if (true === $enabled_component) {
                $mappingPaths[] = sprintf('%s/%s.yaml', $configBasePath, $key);
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
