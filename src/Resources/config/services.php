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
use ApiPlatform\Core\Api\ResourceClassResolverInterface;
use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\EventListener\EventPriorities;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\PathResolver\OperationPathResolverInterface;
use ApiPlatform\Core\Validator\ValidatorInterface as ApiValidator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Service\FilterService;
use Psr\Container\ContainerInterface;
use Silverback\ApiComponentBundle\Action\File\FileAction;
use Silverback\ApiComponentBundle\Action\Form\FormPostPatchAction;
use Silverback\ApiComponentBundle\Action\User\EmailAddressVerifyAction;
use Silverback\ApiComponentBundle\Action\User\PasswordRequestAction;
use Silverback\ApiComponentBundle\Action\User\PasswordUpdateAction;
use Silverback\ApiComponentBundle\Command\FormCachePurgeCommand;
use Silverback\ApiComponentBundle\Command\UserCreateCommand;
use Silverback\ApiComponentBundle\DataTransformer\CollectionOutputDataTransformer;
use Silverback\ApiComponentBundle\DataTransformer\FileOutputDataTransformer;
use Silverback\ApiComponentBundle\DataTransformer\FormOutputDataTransformer;
use Silverback\ApiComponentBundle\DataTransformer\PageTemplateOutputDataTransformer;
use Silverback\ApiComponentBundle\Doctrine\Extension\TablePrefixExtension;
use Silverback\ApiComponentBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentBundle\Event\FormSuccessEvent;
use Silverback\ApiComponentBundle\EventListener\Api\ApiTimestampedListener;
use Silverback\ApiComponentBundle\EventListener\Doctrine\TimestampedListener;
use Silverback\ApiComponentBundle\EventListener\Doctrine\UserListener;
use Silverback\ApiComponentBundle\EventListener\Form\User\NewEmailAddressListener;
use Silverback\ApiComponentBundle\EventListener\Form\User\UserRegisterListener;
use Silverback\ApiComponentBundle\EventListener\Mailer\MessageEventListener;
use Silverback\ApiComponentBundle\Factory\File\FileDataFactory;
use Silverback\ApiComponentBundle\Factory\File\ImagineMetadataFactory;
use Silverback\ApiComponentBundle\Factory\Form\FormFactory;
use Silverback\ApiComponentBundle\Factory\Form\FormViewFactory;
use Silverback\ApiComponentBundle\Factory\Mailer\User\AbstractUserEmailFactory;
use Silverback\ApiComponentBundle\Factory\Mailer\User\ChangeEmailVerificationEmailFactory;
use Silverback\ApiComponentBundle\Factory\Mailer\User\PasswordChangedEmailFactory;
use Silverback\ApiComponentBundle\Factory\Mailer\User\PasswordResetEmailFactory;
use Silverback\ApiComponentBundle\Factory\Mailer\User\UserEnabledEmailFactory;
use Silverback\ApiComponentBundle\Factory\Mailer\User\UsernameChangedEmailFactory;
use Silverback\ApiComponentBundle\Factory\Mailer\User\WelcomeEmailFactory;
use Silverback\ApiComponentBundle\Factory\Response\ResponseFactory;
use Silverback\ApiComponentBundle\Factory\User\UserFactory;
use Silverback\ApiComponentBundle\File\FileRequestHandler;
use Silverback\ApiComponentBundle\File\FileUploader;
use Silverback\ApiComponentBundle\Form\Cache\FormCachePurger;
use Silverback\ApiComponentBundle\Form\Handler\FormSubmitHandler;
use Silverback\ApiComponentBundle\Form\Type\User\ChangePasswordType;
use Silverback\ApiComponentBundle\Form\Type\User\NewEmailAddressType;
use Silverback\ApiComponentBundle\Form\Type\User\UserLoginType;
use Silverback\ApiComponentBundle\Form\Type\User\UserRegisterType;
use Silverback\ApiComponentBundle\Imagine\PathResolver;
use Silverback\ApiComponentBundle\Mailer\UserMailer;
use Silverback\ApiComponentBundle\Manager\User\EmailAddressManager;
use Silverback\ApiComponentBundle\Manager\User\PasswordManager;
use Silverback\ApiComponentBundle\Metadata\AutoRoutePrefixMetadataFactory;
use Silverback\ApiComponentBundle\Metadata\ResourceDtoOutputClassMetadataFactory;
use Silverback\ApiComponentBundle\Repository\Core\LayoutRepository;
use Silverback\ApiComponentBundle\Repository\Core\RouteRepository;
use Silverback\ApiComponentBundle\Repository\User\UserRepository;
use Silverback\ApiComponentBundle\Security\TokenAuthenticator;
use Silverback\ApiComponentBundle\Security\TokenGenerator;
use Silverback\ApiComponentBundle\Security\UserChecker;
use Silverback\ApiComponentBundle\Serializer\ApiNormalizer;
use Silverback\ApiComponentBundle\Serializer\SerializeFormatResolver;
use Silverback\ApiComponentBundle\Serializer\UserContextBuilder;
use Silverback\ApiComponentBundle\Url\RefererUrlHelper;
use Silverback\ApiComponentBundle\Validator\Constraints\FormTypeClassValidator;
use Silverback\ApiComponentBundle\Validator\Constraints\NewEmailAddressValidator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Twig\Environment;

/*
 * @author Daniel West <daniel@silverback.is>
 */
return static function (ContainerConfigurator $configurator) {
    $services = $configurator->services();

    $services
        ->set(AbstractUserEmailFactory::class)
        ->abstract()
        ->args([
            '$container' => new Reference(ContainerInterface::class),
            '$eventDispatcher' => new Reference(EventDispatcherInterface::class),
        ])
        ->tag('container.service_subscriber');

    $services
        ->set(ApiNormalizer::class)
        ->tag('serializer.normalizer', ['priority' => -810])
        ->args([
            new Reference(EntityManagerInterface::class),
            new Reference(ResourceClassResolverInterface::class),
        ])
        ->autoconfigure(false);

    $services
        ->set(ApiTimestampedListener::class)
        ->args([
            new Reference(EntityManagerInterface::class),
            new Reference(TimestampedListener::class),
        ])
        ->tag('kernel.event_listener', ['event' => ViewEvent::class, 'priority' => EventPriorities::PRE_VALIDATE]);

    $services
        ->set(AutoRoutePrefixMetadataFactory::class)
        ->decorate('api_platform.metadata.resource.metadata_factory')
        ->args([
            new Reference(AutoRoutePrefixMetadataFactory::class . '.inner'),
        ]);

    $services
        ->set(ChangeEmailVerificationEmailFactory::class)
        ->parent(AbstractUserEmailFactory::class);

    $services
        ->set(ChangePasswordType::class)
        ->args([new Reference(Security::class)])
        ->tag('form.type');

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
            new Reference(SerializeFormatResolver::class),
            new Reference(UrlHelper::class),
        ]);

    $services
        ->set(EmailAddressManager::class)
        ->args([
            new Reference(EntityManagerInterface::class),
            new Reference(UserRepository::class),
        ]);

    $services
        ->set(EmailAddressVerifyAction::class)
        ->args([
            new Reference(SerializerInterface::class),
            new Reference(SerializeFormatResolver::class),
            new Reference(ResponseFactory::class),
            new Reference(EmailAddressManager::class),
        ]);

    $services
        ->set(FileAction::class)
        ->args([
            new Reference(SerializerInterface::class),
            new Reference(SerializeFormatResolver::class),
            new Reference(ResponseFactory::class),
            new Reference(FileRequestHandler::class),
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
        ->set(ResourceDtoOutputClassMetadataFactory::class)
        ->decorate('api_platform.metadata.resource.metadata_factory')
        ->args([
            new Reference(ResourceDtoOutputClassMetadataFactory::class . '.inner'),
        ])
        ->autoconfigure(false);

    $services
        ->set(FileOutputDataTransformer::class)
        ->tag('api_platform.data_transformer')
        ->args([new Reference(FileDataFactory::class)]);

    $services
        ->set(FileRequestHandler::class)
        ->args([
            new Reference(UrlMatcherInterface::class),
            new Reference(ItemDataProviderInterface::class),
            new Reference(FileUploader::class),
            new Reference(ResourceMetadataFactoryInterface::class),
            new Reference(SerializerInterface::class),
        ]);

    $services
        ->set(FileUploader::class)
        ->args([
            new Reference(EntityManagerInterface::class),
            new Reference(ResourceMetadataFactoryInterface::class),
            new Reference(ValidatorInterface::class),
            new Reference(ApiValidator::class),
            [],
        ]);

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
        ->tag('api_platform.data_transformer')
        ->args([new Reference(FormViewFactory::class)]);

    $services
        ->set(FormPostPatchAction::class)
        ->args([
            new Reference(SerializerInterface::class),
            new Reference(SerializeFormatResolver::class),
            new Reference(ResponseFactory::class),
            new Reference(FormSubmitHandler::class),
        ])
        ->tag('controller.service_arguments');

    $services
        ->set(FormSubmitHandler::class)
        ->args([
            new Reference(FormFactory::class),
            new Reference(EventDispatcherInterface::class),
            new Reference(SerializerInterface::class),
        ]);

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
        ])
        ->tag('doctrine.repository_service');

    $services
        ->set(MessageEventListener::class)
        ->tag('kernel.event_listener', ['event' => MessageEvent::class])
        ->args([
            '%env(MAILER_EMAIL)%',
        ]);

    $services
        ->set(NewEmailAddressListener::class)
        ->args([new Reference(EntityManagerInterface::class)])
        ->tag('kernel.event_listener', ['event' => FormSuccessEvent::class]);

    $services
        ->set(NewEmailAddressType::class)
        ->args([new Reference(Security::class)])
        ->tag('form.type');

    $services
        ->set(NewEmailAddressValidator::class)
        ->args([
            new Reference(UserRepository::class),
        ])
        ->tag('validator.constraint_validator');

    $services
        ->set(PageTemplateOutputDataTransformer::class)
        ->tag('api_platform.data_transformer')
        ->args([
            new Reference(LayoutRepository::class),
        ]);

    $services
        ->set(PasswordChangedEmailFactory::class)
        ->parent(AbstractUserEmailFactory::class);

    $services
        ->set(PasswordManager::class)
        ->args([
            new Reference(UserMailer::class),
            new Reference(EntityManagerInterface::class),
            new Reference(ValidatorInterface::class),
            new Reference(TokenGenerator::class),
            new Reference(UserRepository::class),
        ]);

    $services
        ->set(PasswordResetEmailFactory::class)
        ->parent(AbstractUserEmailFactory::class);

    $services
        ->set(PasswordRequestAction::class)
        ->args($passwordActionArgs = [
            new Reference(SerializerInterface::class),
            new Reference(SerializeFormatResolver::class),
            new Reference(ResponseFactory::class),
            new Reference(PasswordManager::class),
        ]);

    $services
        ->set(PasswordUpdateAction::class)
        ->args($passwordActionArgs);

    $services
        ->set(PathResolver::class);

    $services
        ->set(ResponseFactory::class)
        ->args([
            new Reference(SerializerInterface::class),
            new Reference(SerializeFormatResolver::class),
        ]);

    $services
        ->set(RefererUrlHelper::class)
        ->args([
            new Reference(RequestStack::class),
        ]);

    $services
        ->set(RouteRepository::class)
        ->args([
            new Reference(ManagerRegistry::class),
        ])
        ->tag('doctrine.repository_service');

    $services
        ->set(SerializeFormatResolver::class)
        ->args([
            new Reference(RequestStack::class),
            'jsonld',
        ]);

    $services
        ->set(TablePrefixExtension::class)
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata']);

    $getTimestampedListenerTagArgs = static function ($event) {
        return [
            'event' => $event,
            'method' => $event,
        ];
    };
    $services
        ->set(TimestampedListener::class)
        ->tag('doctrine.event_listener', $getTimestampedListenerTagArgs('prePersist'))
        ->tag('doctrine.event_listener', $getTimestampedListenerTagArgs('preUpdate'));

    $services
        ->set(TokenAuthenticator::class)
        ->args([
            new Reference(Security::class),
            new Reference(ResponseFactory::class),
        ]);

    $services
        ->set(TokenGenerator::class);

    $services
        ->set(UserChecker::class);

    $services
        ->set(UserContextBuilder::class)
        ->decorate('api_platform.serializer.context_builder')
        ->args([
            new Reference(UserContextBuilder::class . '.inner'),
            new Reference(AuthorizationCheckerInterface::class),
        ])
        ->autoconfigure(false);

    $services
        ->set(UserCreateCommand::class)
        ->tag('console.command')
        ->args([
            new Reference(UserFactory::class),
        ]);

    $services
        ->set(UserEnabledEmailFactory::class)
        ->parent(AbstractUserEmailFactory::class);

    $services
        ->set(UserFactory::class)
        ->args([
            new Reference(EntityManagerInterface::class),
            new Reference(ValidatorInterface::class),
            new Reference(UserRepository::class),
        ]);

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
        ->tag('doctrine.orm.entity_listener', $getUserListenerTagArgs('prePersist'))
        ->tag('doctrine.orm.entity_listener', $getUserListenerTagArgs('postPersist'))
        ->tag('doctrine.orm.entity_listener', $getUserListenerTagArgs('preUpdate'))
        ->tag('doctrine.orm.entity_listener', $getUserListenerTagArgs('postUpdate'))
        ->args([
            new Reference(UserPasswordEncoderInterface::class),
            new Reference(UserMailer::class),
            new Reference(TokenGenerator::class),
        ]);

    $services
        ->set(UserLoginType::class)
        ->args([new Reference(RouterInterface::class)])
        ->tag('form.type');

    $services
        ->set(UserMailer::class)
        ->args([
            new Reference(MailerInterface::class),
            new Reference(ContainerInterface::class),
        ])
        ->tag('container.service_subscriber');

    $services
        ->set(UsernameChangedEmailFactory::class)
        ->parent(AbstractUserEmailFactory::class);

    $services
        ->set(UserRegisterListener::class)
        ->args([new Reference(EntityManagerInterface::class)])
        ->tag('kernel.event_listener', ['event' => FormSuccessEvent::class]);

    $services
        ->set(UserRegisterType::class)
        ->tag('form.type');

    $services
        ->set(UserRepository::class)
        ->args([
            new Reference(ManagerRegistry::class),
        ])
        ->tag('doctrine.repository_service');

    $services
        ->set(WelcomeEmailFactory::class)
        ->parent(AbstractUserEmailFactory::class);

    $services->alias(ContextAwareCollectionDataProviderInterface::class, 'api_platform.collection_data_provider');
    $services->alias(Environment::class, 'twig');
    $services->alias(FilterService::class, 'liip_imagine.service.filter');
    $services->alias(OperationPathResolverInterface::class, 'api_platform.operation_path_resolver.router');
    $services->alias(RoleHierarchy::class, 'security.role_hierarchy');
};
