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

namespace Silverback\ApiComponentBundle\Resources\config;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\EventListener\EventPriorities;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\PathResolver\OperationPathResolverInterface;
use ApiPlatform\Core\Serializer\SerializerContextBuilderInterface;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Service\FilterService;
use Silverback\ApiComponentBundle\Action\PasswordRequestAction;
use Silverback\ApiComponentBundle\Action\PasswordUpdateAction;
use Silverback\ApiComponentBundle\Command\FormCachePurgeCommand;
use Silverback\ApiComponentBundle\DataTransformer\CollectionOutputDataTransformer;
use Silverback\ApiComponentBundle\DataTransformer\FileOutputDataTransformer;
use Silverback\ApiComponentBundle\DataTransformer\FormOutputDataTransformer;
use Silverback\ApiComponentBundle\DataTransformer\PageTemplateOutputDataTransformer;
use Silverback\ApiComponentBundle\Doctrine\Extension\TablePrefixExtension;
use Silverback\ApiComponentBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentBundle\EventListener\Doctrine\UserListener;
use Silverback\ApiComponentBundle\EventListener\TimestampedListener;
use Silverback\ApiComponentBundle\Factory\FileDataFactory;
use Silverback\ApiComponentBundle\Factory\FormFactory;
use Silverback\ApiComponentBundle\Factory\FormViewFactory;
use Silverback\ApiComponentBundle\Factory\ImagineMetadataFactory;
use Silverback\ApiComponentBundle\Form\Cache\FormCachePurger;
use Silverback\ApiComponentBundle\Form\Type\ChangePasswordType;
use Silverback\ApiComponentBundle\Form\Type\LoginType;
use Silverback\ApiComponentBundle\Form\Type\NewUsernameType;
use Silverback\ApiComponentBundle\Imagine\PathResolver;
use Silverback\ApiComponentBundle\Mailer\UserNotificationsMailer;
use Silverback\ApiComponentBundle\Metadata\AutoRoutePrefixMetadataFactory;
use Silverback\ApiComponentBundle\Metadata\FileInterfaceOutputClassMetadataFactory;
use Silverback\ApiComponentBundle\Repository\Core\LayoutRepository;
use Silverback\ApiComponentBundle\Repository\Core\RouteRepository;
use Silverback\ApiComponentBundle\Repository\User\UserRepository;
use Silverback\ApiComponentBundle\Security\PasswordManager;
use Silverback\ApiComponentBundle\Security\TokenAuthenticator;
use Silverback\ApiComponentBundle\Security\TokenGenerator;
use Silverback\ApiComponentBundle\Security\UserChecker;
use Silverback\ApiComponentBundle\Serializer\SuperAdminContextBuilder;
use Silverback\ApiComponentBundle\Validator\Constraints\FormTypeClassValidator;
use Silverback\ApiComponentBundle\Validator\Constraints\NewUsernameValidator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Twig\Environment;

/*
 * @author Daniel West <daniel@silverback.is>
 */
return static function (ContainerConfigurator $configurator) {
    $services = $configurator->services();

    $services
        ->defaults()
        ->autoconfigure()
        ->private()
        // ->bind('$projectDir', '%kernel.project_dir%')
;

    $services
        ->set(ChangePasswordType::class)
        ->args([new Reference(Security::class)]);

    $services
        ->set(CollectionOutputDataTransformer::class)
        ->tag('api_platform.data_transformer')
        ->args([
            new Reference(RequestStack::class),
            new Reference(ResourceMetadataFactoryInterface::class),
            new Reference(OperationPathResolverInterface::class),
            new Reference(ContextAwareCollectionDataProviderInterface::class),
            new Reference(IriConverterInterface::class),
            new Reference(NormalizerInterface::class),
        ]);

    $services
        ->set(AutoRoutePrefixMetadataFactory::class)
        ->decorate('api_platform.metadata.resource.metadata_factory')
        ->args([
            new Reference(AutoRoutePrefixMetadataFactory::class . '.inner'),
        ]);

    $services
        ->set(FileDataFactory::class)
        ->args([
            new Reference(IriConverterInterface::class),
            new Reference(RouterInterface::class),
            new Reference(ImagineMetadataFactory::class),
            new Reference(UrlHelper::class),
        ]);

    $services
        ->set(FileInterfaceOutputClassMetadataFactory::class)
        ->decorate('api_platform.metadata.resource.metadata_factory')
        ->args([
            new Reference(FileInterfaceOutputClassMetadataFactory::class . '.inner'),
        ]);

    $services
        ->set(FileOutputDataTransformer::class)
        ->tag('api_platform.data_transformer')
        ->args([new Reference(FileDataFactory::class)]);

    $services
        ->set(FormCachePurgeCommand::class)
        ->tag('console.command')
        ->args([
            new Reference(FormCachePurger::class),
            new Reference(EventDispatcherInterface::class),
        ]);

    $services
        ->set(FormCachePurger::class)
        ->args([
            new Reference(EntityManagerInterface::class),
            new Reference(EventDispatcherInterface::class),
        ]);

    $services
        ->set(FormFactory::class)
        ->args([
            new Reference(FormFactoryInterface::class),
            new Reference(RouterInterface::class),
        ]);

    $services
        ->set(FormOutputDataTransformer::class)
        ->args([new Reference(FormViewFactory::class)]);

    $services
        ->set(FormTypeClassValidator::class)
        ->tag('validator.constraint_validator')
        ->args(
            [
                '$formTypes' => new TaggedIteratorArgument('silverback_api_component.form_type'),
            ]
        );

    $services
        ->set(FormViewFactory::class)
        ->args([new Reference(FormFactory::class)]);

    $services
        ->set(ImagineMetadataFactory::class)
        ->args([
            new Reference(CacheManager::class),
            new Reference(PathResolver::class),
            '%kernel.project_dir%',
            new Reference(FilterService::class),
            new Reference(UrlHelper::class),
        ]);

    $services
        ->set(LayoutRepository::class)
        ->args([
            new Reference(ManagerRegistry::class),
        ]);

    $services
        ->set(LoginType::class)
        ->args([new Reference(RouterInterface::class)]);

    $services
        ->set(NewUsernameType::class)
        ->args([new Reference(Security::class)]);

    $services
        ->set(NewUsernameValidator::class)
        ->args([
            new Reference(UserRepository::class),
        ]);

    $services
        ->set(PageTemplateOutputDataTransformer::class)
        ->tag('api_platform.data_transformer')
        ->args([
            new Reference(LayoutRepository::class),
        ]);

    $services
        ->set(PasswordManager::class)
        ->args([
            new Reference(MailerInterface::class),
            new Reference(EntityManagerInterface::class),
            new Reference(ValidatorInterface::class),
            new Reference(TokenGenerator::class),
            new Reference(RequestStack::class),
            '%env(MAILER_EMAIL)%',
        ]);

    $services
        ->set(PasswordRequestAction::class)
        ->args($passwordActionArgs = [
            new Reference(UserRepository::class),
            new Reference(PasswordManager::class),
        ]);

    $services
        ->set(PasswordUpdateAction::class)
        ->args($passwordActionArgs);

    $services
        ->set(PathResolver::class);

    $services
        ->set(RouteRepository::class)
        ->args([
            new Reference(ManagerRegistry::class),
        ]);

    $services
        ->set(SuperAdminContextBuilder::class)
        ->decorate(SerializerContextBuilderInterface::class)
        ->args([
            new Reference(SuperAdminContextBuilder::class . '.inner'),
            new Reference(AuthorizationCheckerInterface::class),
        ])
        ->autoconfigure(false);

    $services
        ->set(TablePrefixExtension::class)
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata']);

    $services
        ->set(TimestampedListener::class)
        ->args([new Reference(EntityManagerInterface::class)])
        ->tag('kernel.event_listener', ['event' => ViewEvent::class, 'priority' => EventPriorities::PRE_VALIDATE]);

    $services
        ->set(TokenAuthenticator::class)
        ->args([
            new Reference(Security::class),
        ]);

    $services
        ->set(TokenGenerator::class);

    $services
        ->set(UserChecker::class);

    $getUserListenerTagArgs = static function ($event) {
        return [
            'event' => $event,
            'method' => $event,
            'entity' => AbstractUser::class,
            'lazy' => true,
        ];
    };
    $services
        ->set(UserListener::class)
        ->tag('doctrine.event_listener', $getUserListenerTagArgs('prePersist'))
        ->tag('doctrine.event_listener', $getUserListenerTagArgs('postPersist'))
        ->tag('doctrine.event_listener', $getUserListenerTagArgs('preUpdate'))
        ->tag('doctrine.event_listener', $getUserListenerTagArgs('postUpdate'))
        ->args([
            new Reference(UserPasswordEncoderInterface::class),
        ]);

    $services
        ->set(UserNotificationsMailer::class)
        ->args([
            new Reference(MailerInterface::class),
        ]);

    $services
        ->set(UserRepository::class)
        ->args([
            new Reference(ManagerRegistry::class),
        ]);

    $services->alias(ContextAwareCollectionDataProviderInterface::class, 'api_platform.collection_data_provider');
    $services->alias(Environment::class, 'twig');
    $services->alias(FilterService::class, 'liip_imagine.service.filter');
    $services->alias(OperationPathResolverInterface::class, 'api_platform.operation_path_resolver.router');
    $services->alias(RoleHierarchy::class, 'security.role_hierarchy');
    $services->alias(SlugifyInterface::class, 'slugify');
};
