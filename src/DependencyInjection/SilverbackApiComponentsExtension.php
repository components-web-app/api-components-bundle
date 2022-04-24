<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\DependencyInjection;

use Exception;
use Ramsey\Uuid\Doctrine\UuidType;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Doctrine\Extension\ORM\RoutableExtension;
use Silverback\ApiComponentsBundle\Doctrine\Extension\ORM\RouteExtension;
use Silverback\ApiComponentsBundle\Doctrine\Extension\ORM\TablePrefixExtension;
use Silverback\ApiComponentsBundle\Exception\ApiPlatformAuthenticationException;
use Silverback\ApiComponentsBundle\Exception\UnparseableRequestHeaderException;
use Silverback\ApiComponentsBundle\Exception\UserDisabledException;
use Silverback\ApiComponentsBundle\Factory\Uploadable\MediaObjectFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\ChangeEmailConfirmationEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\PasswordChangedEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\PasswordResetEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\UserEnabledEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\UsernameChangedEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\VerifyEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\WelcomeEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\UserFactory;
use Silverback\ApiComponentsBundle\Form\FormTypeInterface;
use Silverback\ApiComponentsBundle\Form\Type\User\ChangePasswordType;
use Silverback\ApiComponentsBundle\Form\Type\User\NewEmailAddressType;
use Silverback\ApiComponentsBundle\Form\Type\User\PasswordUpdateType;
use Silverback\ApiComponentsBundle\Form\Type\User\UserRegisterType;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Silverback\ApiComponentsBundle\Helper\User\UserDataProcessor;
use Silverback\ApiComponentsBundle\Helper\User\UserMailer;
use Silverback\ApiComponentsBundle\Repository\Core\RefreshTokenRepository;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;
use Silverback\ApiComponentsBundle\Security\UserChecker;
use Silverback\ApiComponentsBundle\Security\Voter\RoutableVoter;
use Silverback\ApiComponentsBundle\Security\Voter\RouteVoter;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\MetadataNormalizer;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class SilverbackApiComponentsExtension extends Extension implements PrependExtensionInterface
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

        $definition = $container->getDefinition(UserRepositoryInterface::class);
        $definition->setArgument('$entityClass', $config['user']['class_name']);
        $definition->setArgument('$passwordRequestTimeout', $config['user']['password_reset']['request_timeout_seconds']);
        $definition->setArgument('$newEmailConfirmTimeout', $config['user']['new_email_confirmation']['request_timeout_seconds']);

        $cookieProvider = new Reference('lexik_jwt_authentication.cookie_provider.' . $config['refresh_token']['cookie_name']);
        $definition = $container->getDefinition('silverback.security.jwt_event_listener');
        $definition->setArgument('$cookieProvider', $cookieProvider);
        $container->setParameter('silverback.api_components.refresh_token.ttl', (int) $config['refresh_token']['ttl']);

        if (!empty($config['refresh_token']['options'])) {
            $definition = $container->getDefinition($config['refresh_token']['handler_id']);
            $definition->setArgument('$options', $config['refresh_token']['options']);
        }

        if ('silverback.api_components.refresh_token.storage.doctrine' === $config['refresh_token']['handler_id']) {
            $container
                ->register(RefreshTokenRepository::class)
                ->setArguments([new Reference('doctrine'), $config['refresh_token']['options']['class']])
                ->addTag('doctrine.repository_service');
        }

        $definition = $container->getDefinition('silverback.command.refresh_tokens_expire');
        $definition->setArgument('$storage', new Reference($config['refresh_token']['handler_id']));

        if (class_exists(LogoutEvent::class)) {
            $definition = $container->getDefinition('silverback.security.logout_listener');
            $definition->setArgument('$storage', new Reference($config['refresh_token']['handler_id']));
            $definition->setArgument('$cookieProvider', $cookieProvider);
        } else {
            $definition = $container->getDefinition('silverback.security.logout_handler');
            $definition->setArgument('$storage', new Reference($config['refresh_token']['handler_id']));
            $definition->setArgument('$cookieProvider', $cookieProvider);
        }

        $definition = $container->getDefinition('silverback.security.jwt_invalid_event_listener');
        $definition->setArgument('$cookieProvider', $cookieProvider);

        $definition = $container->getDefinition('silverback.security.jwt_manager');
        $definition->setArgument('$userProvider', new Reference(sprintf('security.user.provider.concrete.%s', $config['refresh_token']['database_user_provider'])));
        $definition->setArgument('$storage', new Reference($config['refresh_token']['handler_id']));

        $definition = $container->getDefinition(PublishableStatusChecker::class);
        $definition->setArgument('$permission', $config['publishable']['permission']);

        $definition = $container->getDefinition(MetadataNormalizer::class);
        $definition->setArgument('$metadataKey', $config['metadata_key']);

        $this->setEmailVerificationArguments($container, $config['user']['email_verification'], $config['user']['password_reset']['repeat_ttl_seconds']);
        $this->setUserClassArguments($container, $config['user']['class_name']);
        $this->setMailerServiceArguments($container, $config);

        $imagineEnabled = $container->getParameter('api_components.imagine_enabled');
        $definition = $container->getDefinition(UploadableAttributeReader::class);
        $definition->setArgument('$imagineBundleEnabled', $imagineEnabled);

        if ($imagineEnabled) {
            $definition = $container->getDefinition(UploadableFileManager::class);
            $definition->setArgument('$filterService', new Reference('liip_imagine.service.filter'));
            $definition->setArgument('$imagineCacheManager', new Reference('liip_imagine.cache.manager'));

            $definition = $container->getDefinition(MediaObjectFactory::class);
            $definition->setArgument('$filterService', new Reference('liip_imagine.service.filter'));
        }

        $definition = $container->getDefinition(RouteExtension::class);
        $definition->setArgument('$config', $config['route_security']);

        $definition = $container->getDefinition(RouteVoter::class);
        $definition->setArgument('$config', $config['route_security']);

        $definition = $container->getDefinition(RoutableExtension::class);
        $definition->setArgument('$securityStr', $config['routable_security']);

        $definition = $container->getDefinition(RoutableVoter::class);
        $definition->setArgument('$securityStr', $config['routable_security']);
    }

    private function setEmailVerificationArguments(ContainerBuilder $container, array $emailVerificationConfig, int $passwordRepeatTtl): void
    {
        $definition = $container->getDefinition(UserChecker::class);
        $definition->setArgument('$denyUnverifiedLogin', $emailVerificationConfig['deny_unverified_login']);

        $definition = $container->getDefinition(UserDataProcessor::class);
        $definition->setArgument('$initialEmailVerifiedState', $emailVerificationConfig['default_value']);
        $definition->setArgument('$verifyEmailOnRegister', $emailVerificationConfig['verify_on_register']);
        $definition->setArgument('$verifyEmailOnChange', $emailVerificationConfig['verify_on_change']);
        $definition->setArgument('$tokenTtl', $passwordRepeatTtl);
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

        $definition = $container->getDefinition(PasswordUpdateType::class);
        $definition->setArgument('$userClass', $userClass);
    }

    private function setMailerServiceArguments(ContainerBuilder $container, array $config): void
    {
        $definition = $container->getDefinition(UserMailer::class);
        $definition->setArgument(
            '$context',
            [
                'website_name' => $config['website_name'],
            ]
        );

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
            VerifyEmailFactory::class => 'email_verification',
            ChangeEmailConfirmationEmailFactory::class => 'new_email_confirmation',
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
            ->addTag('silverback_api_components.form_type');

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.php');
        $loader->load('services_normalizers.php');

        // Todo: revert to checking for service id api_platform.http_cache.purger when https://github.com/api-platform/core/pull/4695 is merged
        // dump($container->hasDefinition('api_platform.http_cache.purger'));
        // if ($container->hasDefinition('api_platform.http_cache.purger.varnish.xkey')) {
        $loader->load('services_doctrine_orm_http_cache_purger.php');
        // }
    }

    public function prepend(ContainerBuilder $container): void
    {
        $configs = $container->getExtensionConfig($this->getAlias());
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        $this->prependApiPlatformConfig($container, $config);
        $this->prependDoctrineConfiguration($container);
    }

    private function prependDoctrineConfiguration(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig(
            'doctrine',
            [
                'dbal' => [
                    'types' => [
                        'uuid' => UuidType::class,
                    ],
                ],
            ]
        );
    }

    private function appendMappingPaths(&$mappingPaths, $srcBase, $name): void
    {
        $configBasePath = $srcBase . '/Resources/config/api_platform';
        $mappingPaths[] = sprintf('%s/%s/resource.xml', $configBasePath, $name);
        $propertiesPath = sprintf('%s/%s/properties.xml', $configBasePath, $name);
        if (file_exists($propertiesPath)) {
            $mappingPaths[] = $propertiesPath;
        }
    }

    private function prependApiPlatformConfig(ContainerBuilder $container, array $config): void
    {
        $srcBase = __DIR__ . '/..';
        $mappingPaths = [$srcBase . '/Entity/Core'];
        $this->appendMappingPaths($mappingPaths, $srcBase, 'uploadable');
        $this->appendMappingPaths($mappingPaths, $srcBase, 'page_data_metadata');
        foreach ($config['enabled_components'] as $component => $is_enabled) {
            if (true === $is_enabled) {
                $this->appendMappingPaths($mappingPaths, $srcBase, $component);
            }
        }

        $websiteName = $config['website_name'];
        $container->prependExtensionConfig(
            'api_platform',
            [
                'title' => $websiteName,
                'description' => sprintf('API for %s', $websiteName),
                'defaults' => [
                    'pagination_client_items_per_page' => true,
                    'pagination_maximum_items_per_page' => 100,
                ],
                'collection' => [
                    'pagination' => [
                        'items_per_page_parameter_name' => 'perPage',
                        // 'client_items_per_page' => true,
                        // 'maximum_items_per_page' => 100,
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
                'exception_to_status' => [
                    UnparseableRequestHeaderException::class => 400,
                    ApiPlatformAuthenticationException::class => 401,
                    UserDisabledException::class => 401,
                ],
            ]
        );
    }
}
