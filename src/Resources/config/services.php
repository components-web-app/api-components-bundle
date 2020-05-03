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

namespace Silverback\ApiComponentsBundle\Resources\config;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Api\ResourceClassResolverInterface;
use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\EventListener\EventPriorities;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\PathResolver\OperationPathResolverInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Psr\Container\ContainerInterface;
use Silverback\ApiComponentsBundle\Action\Form\FormPostPatchAction;
use Silverback\ApiComponentsBundle\Action\Uploadable\UploadableAction;
use Silverback\ApiComponentsBundle\Action\User\EmailAddressVerifyAction;
use Silverback\ApiComponentsBundle\Action\User\PasswordRequestAction;
use Silverback\ApiComponentsBundle\Action\User\PasswordUpdateAction;
use Silverback\ApiComponentsBundle\AnnotationReader\AnnotationReader;
use Silverback\ApiComponentsBundle\AnnotationReader\PublishableAnnotationReader;
use Silverback\ApiComponentsBundle\AnnotationReader\TimestampedAnnotationReader;
use Silverback\ApiComponentsBundle\AnnotationReader\UploadableAnnotationReader;
use Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource\RoutingPrefixResourceMetadataFactory;
use Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource\UploadableResourceMetadataFactory;
use Silverback\ApiComponentsBundle\Command\FormCachePurgeCommand;
use Silverback\ApiComponentsBundle\Command\UserCreateCommand;
use Silverback\ApiComponentsBundle\DataTransformer\CollectionOutputDataTransformer;
use Silverback\ApiComponentsBundle\DataTransformer\FormOutputDataTransformer;
use Silverback\ApiComponentsBundle\DataTransformer\PageTemplateOutputDataTransformer;
use Silverback\ApiComponentsBundle\Doctrine\Extension\ORM\PublishableExtension;
use Silverback\ApiComponentsBundle\Doctrine\Extension\ORM\TablePrefixExtension;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Event\FormSuccessEvent;
use Silverback\ApiComponentsBundle\Event\ImagineRemoveEvent;
use Silverback\ApiComponentsBundle\Event\ImagineStoreEvent;
use Silverback\ApiComponentsBundle\EventListener\Api\PublishableEventListener;
use Silverback\ApiComponentsBundle\EventListener\Api\UploadableEventListener;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\PublishableListener;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\TimestampedListener;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\UploadableListener;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\UserListener;
use Silverback\ApiComponentsBundle\EventListener\Form\User\NewEmailAddressListener;
use Silverback\ApiComponentsBundle\EventListener\Form\User\UserRegisterListener;
use Silverback\ApiComponentsBundle\EventListener\Imagine\ImagineEventListener;
use Silverback\ApiComponentsBundle\EventListener\Jwt\JwtCreatedEventListener;
use Silverback\ApiComponentsBundle\EventListener\Mailer\MessageEventListener;
use Silverback\ApiComponentsBundle\Factory\Form\FormFactory;
use Silverback\ApiComponentsBundle\Factory\Form\FormViewFactory;
use Silverback\ApiComponentsBundle\Factory\Mailer\User\AbstractUserEmailFactory;
use Silverback\ApiComponentsBundle\Factory\Mailer\User\ChangeEmailVerificationEmailFactory;
use Silverback\ApiComponentsBundle\Factory\Mailer\User\PasswordChangedEmailFactory;
use Silverback\ApiComponentsBundle\Factory\Mailer\User\PasswordResetEmailFactory;
use Silverback\ApiComponentsBundle\Factory\Mailer\User\UserEnabledEmailFactory;
use Silverback\ApiComponentsBundle\Factory\Mailer\User\UsernameChangedEmailFactory;
use Silverback\ApiComponentsBundle\Factory\Mailer\User\WelcomeEmailFactory;
use Silverback\ApiComponentsBundle\Factory\Response\ResponseFactory;
use Silverback\ApiComponentsBundle\Factory\Uploadable\MediaObjectFactory;
use Silverback\ApiComponentsBundle\Factory\User\UserFactory;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Form\Cache\FormCachePurger;
use Silverback\ApiComponentsBundle\Form\Handler\FormSubmitHandler;
use Silverback\ApiComponentsBundle\Form\Type\User\ChangePasswordType;
use Silverback\ApiComponentsBundle\Form\Type\User\NewEmailAddressType;
use Silverback\ApiComponentsBundle\Form\Type\User\UserLoginType;
use Silverback\ApiComponentsBundle\Form\Type\User\UserRegisterType;
use Silverback\ApiComponentsBundle\Imagine\FlysystemDataLoader;
use Silverback\ApiComponentsBundle\Mailer\UserMailer;
use Silverback\ApiComponentsBundle\Manager\User\EmailAddressManager;
use Silverback\ApiComponentsBundle\Manager\User\PasswordManager;
use Silverback\ApiComponentsBundle\Publishable\PublishableHelper;
use Silverback\ApiComponentsBundle\Repository\Core\LayoutRepository;
use Silverback\ApiComponentsBundle\Repository\Core\RouteRepository;
use Silverback\ApiComponentsBundle\Repository\User\UserRepository;
use Silverback\ApiComponentsBundle\Security\TokenAuthenticator;
use Silverback\ApiComponentsBundle\Security\UserChecker;
use Silverback\ApiComponentsBundle\Serializer\ContextBuilder\PublishableContextBuilder;
use Silverback\ApiComponentsBundle\Serializer\ContextBuilder\TimestampedContextBuilder;
use Silverback\ApiComponentsBundle\Serializer\ContextBuilder\UserContextBuilder;
use Silverback\ApiComponentsBundle\Serializer\MappingLoader\PublishableLoader;
use Silverback\ApiComponentsBundle\Serializer\MappingLoader\TimestampedLoader;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\MetadataNormalizer;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\PersistedNormalizer;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\PublishableNormalizer;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\UploadableNormalizer;
use Silverback\ApiComponentsBundle\Serializer\SerializeFormatResolver;
use Silverback\ApiComponentsBundle\Uploadable\FileInfoCacheHelper;
use Silverback\ApiComponentsBundle\Uploadable\UploadableHelper;
use Silverback\ApiComponentsBundle\Utility\RefererUrlHelper;
use Silverback\ApiComponentsBundle\Validator\Constraints\FormTypeClassValidator;
use Silverback\ApiComponentsBundle\Validator\Constraints\NewEmailAddressValidator;
use Silverback\ApiComponentsBundle\Validator\PublishableValidator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Twig\Environment;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_locator;

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
        ]);

    $services
        ->set(AnnotationReader::class)
        ->abstract()
        ->args([
            new Reference('annotations.reader'),
            new Reference('doctrine'),
        ]);

    $services
        ->set(ChangeEmailVerificationEmailFactory::class)
        ->parent(AbstractUserEmailFactory::class)
        ->tag('container.service_subscriber');

    $services
        ->set(ChangePasswordType::class)
        ->args([
            new Reference(Security::class),
            '', // injected in dependency injection
        ])
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
        ->set(UploadableAction::class)
        ->tag('controller.service_arguments');

    $services
        ->set(FilesystemProvider::class)
        ->args([tagged_locator(FilesystemProvider::FILESYSTEM_ADAPTER_TAG, 'alias')]);

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
        ])
        ->tag('controller.service_arguments');

    $services
        ->set(FileInfoCacheHelper::class)
        ->args([
            new Reference(EntityManagerInterface::class),
        ]);

    $services
        ->set(FlysystemDataLoader::class)
        ->args([
            new Reference(FilesystemProvider::class),
        ])
        ->tag('liip_imagine.binary.loader', ['loader' => 'silverback.api_component.liip_imagine.binary.loader']);

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
                '$formTypes' => new TaggedIteratorArgument('silverback_api_components.form_type'),
            ]
        );

    $services
        ->set(FormViewFactory::class)
        ->args([new Reference(FormFactory::class)]);

    $services
        ->set(ImagineEventListener::class)
        ->args([
            new Reference(FileInfoCacheHelper::class),
        ])
        ->tag('kernel.event_listener', ['event' => ImagineStoreEvent::class, 'method' => 'onStore'])
        ->tag('kernel.event_listener', ['event' => ImagineRemoveEvent::class, 'method' => 'onRemove']);

    $services
        ->set(JwtCreatedEventListener::class)
        ->args([
            new Reference(RoleHierarchy::class),
        ])
        ->tag('kernel.event_listener', ['event' => Events::JWT_CREATED, 'method' => 'updateTokenRoles']);

    $services
        ->set(LayoutRepository::class)
        ->args([
            new Reference(ManagerRegistry::class),
        ])
        ->tag('doctrine.repository_service');

    $services
        ->set(MediaObjectFactory::class)
        ->args([
            new Reference(ManagerRegistry::class),
            new Reference(FileInfoCacheHelper::class),
            new Reference(UploadableAnnotationReader::class),
            new Reference(FilesystemProvider::class),
            new Reference(FlysystemDataLoader::class),
            new Reference(RequestStack::class),
            null, // populated in dependency injection
        ]);

    $services
        ->set(MessageEventListener::class)
        ->tag('kernel.event_listener', ['event' => MessageEvent::class])
        ->args([
            '%env(MAILER_EMAIL)%',
        ]);

    $services
        ->set(MetadataNormalizer::class)
        ->autoconfigure(false)
        ->args([
            '', // set in dependency injection
        ])
        ->tag('serializer.normalizer', ['priority' => -500]);

    $services
        ->set(NewEmailAddressListener::class)
        ->args([new Reference(EntityManagerInterface::class)])
        ->tag('kernel.event_listener', ['event' => FormSuccessEvent::class]);

    $services
        ->set(NewEmailAddressType::class)
        ->args([
            new Reference(Security::class),
            '', // injected in dependency injection
        ])
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
        ->parent(AbstractUserEmailFactory::class)
        ->tag('container.service_subscriber');

    $services
        ->set(PasswordManager::class)
        ->args([
            new Reference(UserMailer::class),
            new Reference(EntityManagerInterface::class),
            new Reference(ValidatorInterface::class),
            new Reference(UserRepository::class),
            '', // injected in dependency injection
        ]);

    $services
        ->set(PasswordResetEmailFactory::class)
        ->parent(AbstractUserEmailFactory::class)
        ->tag('container.service_subscriber');

    $services
        ->set(PasswordRequestAction::class)
        ->args($passwordActionArgs = [
            new Reference(SerializerInterface::class),
            new Reference(SerializeFormatResolver::class),
            new Reference(ResponseFactory::class),
            new Reference(PasswordManager::class),
        ])
        ->tag('controller.service_arguments');

    $services
        ->set(PasswordUpdateAction::class)
        ->args($passwordActionArgs)
        ->tag('controller.service_arguments');

    $services
        ->set(PersistedNormalizer::class)
        ->autoconfigure(false)
        ->args([
            new Reference(EntityManagerInterface::class),
            new Reference(ResourceClassResolverInterface::class),
        ])
        ->tag('serializer.normalizer', ['priority' => -499]);

    $services
        ->set(PublishableAnnotationReader::class)
        ->parent(AnnotationReader::class);

    $services
        ->set(PublishableContextBuilder::class)
        ->decorate('api_platform.serializer.context_builder')
        ->args([
            new Reference(PublishableContextBuilder::class . '.inner'),
            new Reference(PublishableHelper::class),
        ])
        ->autoconfigure(false);

    $services
        ->set(PublishableEventListener::class)
        ->args([
            new Reference(PublishableHelper::class),
            new Reference('doctrine'),
            new Reference('api_platform.validator'),
        ])
        ->tag('kernel.event_listener', ['event' => RequestEvent::class, 'priority' => EventPriorities::POST_READ, 'method' => 'onPostRead'])
        ->tag('kernel.event_listener', ['event' => ViewEvent::class, 'priority' => EventPriorities::PRE_WRITE, 'method' => 'onPreWrite'])
        ->tag('kernel.event_listener', ['event' => RequestEvent::class, 'priority' => EventPriorities::POST_DESERIALIZE, 'method' => 'onPostDeserialize'])
        ->tag('kernel.event_listener', ['event' => ResponseEvent::class, 'priority' => EventPriorities::POST_RESPOND, 'method' => 'onPostRespond']);

    $services
        ->set(PublishableHelper::class)
        ->args([
            new Reference(ManagerRegistry::class),
            new Reference(PublishableAnnotationReader::class),
            new Reference(AuthorizationCheckerInterface::class),
            '', // injected with dependency injection
        ]);

    $services
        ->set(PublishableListener::class)
        ->args([new Reference(PublishableAnnotationReader::class)])
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata']);

    // High priority for item because of queryBuilder reset
    $services
        ->set(PublishableExtension::class)
        ->args([
            new Reference(PublishableHelper::class),
            new Reference('request_stack'),
            new Reference('doctrine'),
        ])
        ->tag('api_platform.doctrine.orm.query_extension.item', ['priority' => 100])
        ->tag('api_platform.doctrine.orm.query_extension.collection');

    $services
        ->set(PublishableNormalizer::class)
        ->autoconfigure(false)
        ->args([
            new Reference(PublishableHelper::class),
            new Reference('doctrine'),
            new Reference('request_stack'),
            new Reference('api_platform.validator'),
        ])->tag('serializer.normalizer', ['priority' => -400]);

    $services
        ->set(PublishableValidator::class)
        ->decorate('api_platform.validator')
        ->args([
            new Reference(PublishableValidator::class . '.inner'),
            new Reference(PublishableHelper::class),
        ]);

    $services
        ->set(PublishableLoader::class)
        ->args([
            new Reference('annotations.reader'),
        ]);

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
        ->set(RoutingPrefixResourceMetadataFactory::class)
        ->decorate('api_platform.metadata.resource.metadata_factory')
        ->args([
            new Reference(RoutingPrefixResourceMetadataFactory::class . '.inner'),
        ]);

    $services
        ->set(SerializeFormatResolver::class)
        ->args([
            new Reference(RequestStack::class),
            'jsonld',
        ]);

    $services
        ->set(TimestampedAnnotationReader::class)
        ->parent(AnnotationReader::class);

    $services
        ->set(TimestampedContextBuilder::class)
        ->decorate('api_platform.serializer.context_builder')
        ->args([
            new Reference(TimestampedContextBuilder::class . '.inner'),
        ])
        ->autoconfigure(false);

    $services
        ->set(TimestampedLoader::class)
        ->args([
            new Reference('annotations.reader'),
        ]);

    $services
        ->set(TablePrefixExtension::class)
        ->args([
            '', // injected in dependency injection
        ])
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata']);

    $getTimestampedListenerTagArgs = static function ($event) {
        return [
            'event' => $event,
            'method' => $event,
        ];
    };
    $services
        ->set(TimestampedListener::class)
        ->args([
            new Reference(TimestampedAnnotationReader::class),
            new Reference(ManagerRegistry::class),
        ])
        ->tag('doctrine.event_listener', $getTimestampedListenerTagArgs('loadClassMetadata'))
        ->tag('doctrine.event_listener', $getTimestampedListenerTagArgs('prePersist'))
        ->tag('doctrine.event_listener', $getTimestampedListenerTagArgs('preUpdate'));

    $services
        ->set(TokenAuthenticator::class)
        ->args([
            new Reference(Security::class),
            new Reference(ResponseFactory::class),
            '', // injected in dependency injection
        ]);

    $services
        ->set(UploadableAnnotationReader::class)
        ->parent(AnnotationReader::class);

    $services
        ->set(UploadableEventListener::class)
        ->args([
            new Reference(UploadableAnnotationReader::class),
            new Reference(UploadableHelper::class),
        ])
        ->tag('kernel.event_listener', ['event' => ViewEvent::class, 'priority' => EventPriorities::PRE_WRITE, 'method' => 'onPreWrite'])
        ->tag('kernel.event_listener', ['event' => ViewEvent::class, 'priority' => EventPriorities::POST_WRITE, 'method' => 'onPostWrite']);

    $services
        ->set(UploadableHelper::class)
        ->args([
            new Reference(ManagerRegistry::class),
            new Reference(UploadableAnnotationReader::class),
            new Reference(FilesystemProvider::class),
            new Reference(FlysystemDataLoader::class),
            null, // Set in dependency injection if imagine cache manager exists
        ]);

    $services
        ->set(UploadableListener::class)
        ->args([
            new Reference(UploadableAnnotationReader::class),
        ])
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata']);

    $services
        ->set(UploadableNormalizer::class)
        ->autoconfigure(false)
        ->args([
            new Reference(MediaObjectFactory::class),
            new Reference(UploadableAnnotationReader::class),
            new Reference(ManagerRegistry::class),
        ])
        ->tag('serializer.normalizer', ['priority' => -499]);

    $services
        ->set(UploadableResourceMetadataFactory::class)
        ->decorate('api_platform.metadata.resource.metadata_factory')
        ->args([
            new Reference(UploadableResourceMetadataFactory::class . '.inner'),
            new Reference(UploadableAnnotationReader::class),
            new Reference('api_platform.path_segment_name_generator'),
        ])
        ->autoconfigure(false);

//    COMPILER PASS REQUIRED AS WELL
//    $services
//        ->set(UploadableLoader::class)
//        ->args([
//            new Reference(UploadableAnnotationReader::class),
//        ]);

    $services
        ->set(UserChecker::class)
        ->args([
            '', // injected in dependency injection
        ]);

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
        ->parent(AbstractUserEmailFactory::class)
        ->tag('container.service_subscriber');

    $services
        ->set(UserFactory::class)
        ->args([
            new Reference(EntityManagerInterface::class),
            new Reference(ValidatorInterface::class),
            new Reference(UserRepository::class),
            '', // injected in dependency injection
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
            '', // injected in dependency injection
            '', // injected in dependency injection
            '', // injected in dependency injection
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
            '', // injected in dependency injection
        ])
        ->tag('container.service_subscriber');

    $services
        ->set(UsernameChangedEmailFactory::class)
        ->parent(AbstractUserEmailFactory::class)
        ->tag('container.service_subscriber');

    $services
        ->set(UserRegisterListener::class)
        ->args([new Reference(EntityManagerInterface::class)])
        ->tag('kernel.event_listener', ['event' => FormSuccessEvent::class]);

    $services
        ->set(UserRegisterType::class)
        ->args([
            '', // injected in dependency injection
        ])
        ->tag('form.type');

    $services
        ->set(UserRepository::class)
        ->args([
            new Reference(ManagerRegistry::class),
            '', // injected in dependency injection
            '', // injected in dependency injection
        ])
        ->tag('doctrine.repository_service');

    $services
        ->set(WelcomeEmailFactory::class)
        ->parent(AbstractUserEmailFactory::class)
        ->tag('container.service_subscriber');

    $services->alias(ContextAwareCollectionDataProviderInterface::class, 'api_platform.collection_data_provider');
    $services->alias(Environment::class, 'twig');
    $services->alias(OperationPathResolverInterface::class, 'api_platform.operation_path_resolver.router');
    $services->alias(RoleHierarchy::class, 'security.role_hierarchy');
};
