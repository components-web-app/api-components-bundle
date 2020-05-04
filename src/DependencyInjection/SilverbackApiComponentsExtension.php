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
use Silverback\ApiComponentsBundle\AnnotationReader\UploadableAnnotationReader;
use Silverback\ApiComponentsBundle\Doctrine\Extension\ORM\TablePrefixExtension;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentInterface;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\UserListener;
use Silverback\ApiComponentsBundle\Factory\Mailer\User\ChangeEmailVerificationEmailFactory;
use Silverback\ApiComponentsBundle\Factory\Mailer\User\PasswordChangedEmailFactory;
use Silverback\ApiComponentsBundle\Factory\Mailer\User\PasswordResetEmailFactory;
use Silverback\ApiComponentsBundle\Factory\Mailer\User\UserEnabledEmailFactory;
use Silverback\ApiComponentsBundle\Factory\Mailer\User\UsernameChangedEmailFactory;
use Silverback\ApiComponentsBundle\Factory\Mailer\User\WelcomeEmailFactory;
use Silverback\ApiComponentsBundle\Factory\Uploadable\MediaObjectFactory;
use Silverback\ApiComponentsBundle\Factory\User\UserFactory;
use Silverback\ApiComponentsBundle\Form\FormTypeInterface;
use Silverback\ApiComponentsBundle\Form\Type\User\ChangePasswordType;
use Silverback\ApiComponentsBundle\Form\Type\User\NewEmailAddressType;
use Silverback\ApiComponentsBundle\Form\Type\User\UserRegisterType;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableHelper;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableHelper;
use Silverback\ApiComponentsBundle\Mailer\UserMailer;
use Silverback\ApiComponentsBundle\Manager\User\PasswordManager;
use Silverback\ApiComponentsBundle\Repository\User\UserRepository;
use Silverback\ApiComponentsBundle\Security\TokenAuthenticator;
use Silverback\ApiComponentsBundle\Security\UserChecker;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\MetadataNormalizer;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

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

        $definition = $container->getDefinition(TokenAuthenticator::class);
        $definition->setArgument('$tokens', $config['security']['tokens']);

        $definition = $container->getDefinition(PasswordManager::class);
        $definition->setArgument('$tokenTtl', $config['user']['password_reset']['repeat_ttl_seconds']);

        $definition = $container->getDefinition(UserRepository::class);
        $definition->setArgument('$passwordRequestTimeout', $config['user']['password_reset']['request_timeout_seconds']);
        $definition->setArgument('$entityClass', $config['user']['class_name']);

        $definition = $container->getDefinition(PublishableHelper::class);
        $definition->setArgument('$permission', $config['publishable']['permission']);

        $definition = $container->getDefinition(MetadataNormalizer::class);
        $definition->setArgument('$metadataKey', $config['metadata_key']);

        $this->setEmailVerificationArguments($container, $config['user']['email_verification']);
        $this->setUserClassArguments($container, $config['user']['class_name']);
        $this->setMailerServiceArguments($container, $config);

        $imagineEnabled = $container->getParameter('api_component.imagine_enabled');
        $definition = $container->getDefinition(UploadableAnnotationReader::class);
        $definition->setArgument('$imagineBundleEnabled', $imagineEnabled);

        if ($imagineEnabled) {
            $definition = $container->getDefinition(UploadableHelper::class);
            $definition->setArgument('$filterService', new Reference('liip_imagine.service.filter'));

            $definition = $container->getDefinition(MediaObjectFactory::class);
            $definition->setArgument('$filterService', new Reference('liip_imagine.service.filter'));
        }
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
            ->addTag('silverback_api_components.form_type');

        $container->registerForAutoconfiguration(ComponentInterface::class)
            ->addTag('silverback_api_components.entity.component');

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
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
        $mappingPaths[] = sprintf('%s/%s.xml', $configBasePath, 'uploadable');
        foreach ($config['enabled_components'] as $key => $enabled_component) {
            if (true === $enabled_component) {
                $mappingPaths[] = sprintf('%s/%s.xml', $configBasePath, $key);
            }
        }
        $websiteName = $config['website_name'];
        $container->prependExtensionConfig('api_platform', [
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
        ]);
    }
}
