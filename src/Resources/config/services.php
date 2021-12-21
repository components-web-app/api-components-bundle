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
use ApiPlatform\Core\EventListener\EventPriorities;
use ApiPlatform\Core\Serializer\SerializerContextBuilderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Psr\Container\ContainerInterface;
use Silverback\ApiComponentsBundle\Action\Uploadable\DownloadAction;
use Silverback\ApiComponentsBundle\Action\Uploadable\UploadAction;
use Silverback\ApiComponentsBundle\Action\User\EmailAddressConfirmAction;
use Silverback\ApiComponentsBundle\Action\User\PasswordRequestAction;
use Silverback\ApiComponentsBundle\Action\User\VerifyEmailAddressAction;
use Silverback\ApiComponentsBundle\AnnotationReader\AnnotationReader;
use Silverback\ApiComponentsBundle\AnnotationReader\PublishableAnnotationReader;
use Silverback\ApiComponentsBundle\AnnotationReader\TimestampedAnnotationReader;
use Silverback\ApiComponentsBundle\AnnotationReader\UploadableAnnotationReader;
use Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Property\ComponentPropertyMetadataFactory;
use Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Property\ImagineFiltersPropertyMetadataFactory;
use Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource\RoutableResourceMetadataFactory;
use Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource\RoutingPrefixResourceMetadataFactory;
use Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource\UploadableResourceMetadataFactory;
use Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource\UserResourceMetadataFactory;
use Silverback\ApiComponentsBundle\Command\FormCachePurgeCommand;
use Silverback\ApiComponentsBundle\Command\RefreshTokensExpireCommand;
use Silverback\ApiComponentsBundle\Command\UserCreateCommand;
use Silverback\ApiComponentsBundle\DataProvider\Item\RouteDataProvider;
use Silverback\ApiComponentsBundle\DataProvider\Item\UserDataProvider;
use Silverback\ApiComponentsBundle\DataProvider\PageDataProvider;
use Silverback\ApiComponentsBundle\DataTransformer\CollectionOutputDataTransformer;
use Silverback\ApiComponentsBundle\DataTransformer\FormOutputDataTransformer;
use Silverback\ApiComponentsBundle\Doctrine\Extension\ORM\PublishableExtension;
use Silverback\ApiComponentsBundle\Doctrine\Extension\ORM\RoutableExtension;
use Silverback\ApiComponentsBundle\Doctrine\Extension\ORM\RouteExtension;
use Silverback\ApiComponentsBundle\Doctrine\Extension\ORM\TablePrefixExtension;
use Silverback\ApiComponentsBundle\Event\FormSuccessEvent;
use Silverback\ApiComponentsBundle\Event\ImagineRemoveEvent;
use Silverback\ApiComponentsBundle\Event\ImagineStoreEvent;
use Silverback\ApiComponentsBundle\Event\JWTRefreshedEvent;
use Silverback\ApiComponentsBundle\EventListener\Api\FormSubmitEventListener;
use Silverback\ApiComponentsBundle\EventListener\Api\PublishableEventListener;
use Silverback\ApiComponentsBundle\EventListener\Api\RouteEventListener;
use Silverback\ApiComponentsBundle\EventListener\Api\UploadableEventListener;
use Silverback\ApiComponentsBundle\EventListener\Api\UserEventListener;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\PublishableListener;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\TimestampedListener;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\UploadableListener;
use Silverback\ApiComponentsBundle\EventListener\Form\EntityPersistFormListener;
use Silverback\ApiComponentsBundle\EventListener\Form\User\ChangePasswordListener;
use Silverback\ApiComponentsBundle\EventListener\Form\User\NewEmailAddressListener;
use Silverback\ApiComponentsBundle\EventListener\Form\User\PasswordUpdateListener;
use Silverback\ApiComponentsBundle\EventListener\Form\User\UserRegisterListener;
use Silverback\ApiComponentsBundle\EventListener\Imagine\ImagineEventListener;
use Silverback\ApiComponentsBundle\EventListener\Jwt\JWTEventListener;
use Silverback\ApiComponentsBundle\EventListener\Jwt\JWTInvalidEventListener;
use Silverback\ApiComponentsBundle\EventListener\Mailer\MessageEventListener;
use Silverback\ApiComponentsBundle\Factory\Form\FormViewFactory;
use Silverback\ApiComponentsBundle\Factory\Uploadable\MediaObjectFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\AbstractUserEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\ChangeEmailConfirmationEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\PasswordChangedEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\PasswordResetEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\UserEnabledEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\UsernameChangedEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\VerifyEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\WelcomeEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\UserFactory;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Form\Type\User\ChangePasswordType;
use Silverback\ApiComponentsBundle\Form\Type\User\NewEmailAddressType;
use Silverback\ApiComponentsBundle\Form\Type\User\PasswordUpdateType;
use Silverback\ApiComponentsBundle\Form\Type\User\UserLoginType;
use Silverback\ApiComponentsBundle\Form\Type\User\UserRegisterType;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentPositionSortValueHelper;
use Silverback\ApiComponentsBundle\Helper\Form\FormCachePurger;
use Silverback\ApiComponentsBundle\Helper\Form\FormSubmitHelper;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Helper\RefererUrlResolver;
use Silverback\ApiComponentsBundle\Helper\Route\RouteGenerator;
use Silverback\ApiComponentsBundle\Helper\Route\RouteGeneratorInterface;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Helper\Uploadable\FileInfoCacheManager;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Silverback\ApiComponentsBundle\Helper\User\EmailAddressManager;
use Silverback\ApiComponentsBundle\Helper\User\UserDataProcessor;
use Silverback\ApiComponentsBundle\Helper\User\UserMailer;
use Silverback\ApiComponentsBundle\Imagine\FlysystemDataLoader;
use Silverback\ApiComponentsBundle\Metadata\Factory\CachedPageDataMetadataFactory;
use Silverback\ApiComponentsBundle\Metadata\Factory\PageDataMetadataFactory;
use Silverback\ApiComponentsBundle\Metadata\Factory\PageDataMetadataFactoryInterface;
use Silverback\ApiComponentsBundle\RefreshToken\Storage\DoctrineRefreshTokenStorage;
use Silverback\ApiComponentsBundle\Repository\Core\AbstractPageDataRepository;
use Silverback\ApiComponentsBundle\Repository\Core\FileInfoRepository;
use Silverback\ApiComponentsBundle\Repository\Core\LayoutRepository;
use Silverback\ApiComponentsBundle\Repository\Core\RouteRepository;
use Silverback\ApiComponentsBundle\Repository\User\UserRepository;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;
use Silverback\ApiComponentsBundle\Security\EventListener\DenyAccessListener;
use Silverback\ApiComponentsBundle\Security\EventListener\LogoutListener;
use Silverback\ApiComponentsBundle\Security\Http\Logout\LogoutHandler;
use Silverback\ApiComponentsBundle\Security\JWTManager;
use Silverback\ApiComponentsBundle\Security\UserChecker;
use Silverback\ApiComponentsBundle\Security\Voter\RoutableVoter;
use Silverback\ApiComponentsBundle\Security\Voter\RouteVoter;
use Silverback\ApiComponentsBundle\Serializer\ContextBuilder\CwaResourceContextBuilder;
use Silverback\ApiComponentsBundle\Serializer\ContextBuilder\PublishableContextBuilder;
use Silverback\ApiComponentsBundle\Serializer\ContextBuilder\TimestampedContextBuilder;
use Silverback\ApiComponentsBundle\Serializer\ContextBuilder\UploadableContextBuilder;
use Silverback\ApiComponentsBundle\Serializer\ContextBuilder\UserContextBuilder;
use Silverback\ApiComponentsBundle\Serializer\MappingLoader\CwaResourceLoader;
use Silverback\ApiComponentsBundle\Serializer\MappingLoader\PublishableLoader;
use Silverback\ApiComponentsBundle\Serializer\MappingLoader\TimestampedLoader;
use Silverback\ApiComponentsBundle\Serializer\MappingLoader\UploadableLoader;
use Silverback\ApiComponentsBundle\Serializer\SerializeFormatResolver;
use Silverback\ApiComponentsBundle\Serializer\UuidNormalizer;
use Silverback\ApiComponentsBundle\Utility\ApiResourceRouteFinder;
use Silverback\ApiComponentsBundle\Validator\Constraints\ComponentPositionValidator;
use Silverback\ApiComponentsBundle\Validator\Constraints\FormTypeClassValidator;
use Silverback\ApiComponentsBundle\Validator\Constraints\NewEmailAddressValidator;
use Silverback\ApiComponentsBundle\Validator\Constraints\ResourceIriValidator;
use Silverback\ApiComponentsBundle\Validator\Constraints\UserPasswordValidator;
use Silverback\ApiComponentsBundle\Validator\MappingLoader\TimestampedLoader as TimestampedValidatorMappingLoader;
use Silverback\ApiComponentsBundle\Validator\PublishableValidator;
use Silverback\ApiComponentsBundle\Validator\TimestampedValidator;
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
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Event\LogoutEvent;
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
        ->set(AbstractPageDataRepository::class)
        ->args(
            [
                new Reference(ManagerRegistry::class),
            ]
        )
        ->tag('doctrine.repository_service');

    $services
        ->set(AbstractUserEmailFactory::class)
        ->abstract()
        ->args(
            [
                '$container' => new Reference(ContainerInterface::class),
                '$eventDispatcher' => new Reference(EventDispatcherInterface::class),
            ]
        );

    $services
        ->set(AnnotationReader::class)
        ->abstract()
        ->args(
            [
                new Reference('annotations.reader'),
                new Reference('doctrine'),
            ]
        );

    $services
        ->set(ApiResourceRouteFinder::class)
        ->args(
            [
                new Reference('api_platform.router'),
            ]
        );

    $services
        ->set(ChangeEmailConfirmationEmailFactory::class)
        ->parent(AbstractUserEmailFactory::class)
        ->tag('container.service_subscriber');

    $services
        ->set(ChangePasswordType::class)
        ->args(
            [
                new Reference(Security::class),
                new Reference('silverback.repository.user'),
                '', // injected in dependency injection
            ]
        )
        ->tag('form.type');

    $services
        ->set(CwaResourceContextBuilder::class)
        ->decorate('api_platform.serializer.context_builder')
        ->args(
            [
                new Reference(CwaResourceContextBuilder::class . '.inner'),
                new Reference(RoleHierarchyInterface::class),
                new Reference(Security::class),
            ]
        )
        ->autoconfigure(false);

    $services
        ->set(CwaResourceLoader::class);

    $services
        ->set(ChangePasswordListener::class)
        ->parent(EntityPersistFormListener::class)
        ->tag('kernel.event_listener', ['event' => FormSuccessEvent::class]);

    $services
        ->set(CollectionOutputDataTransformer::class)
        ->autoconfigure(false)
        ->args(
            [
                new Reference(ApiResourceRouteFinder::class),
                new Reference('api_platform.collection_data_provider'),
                new Reference(RequestStack::class),
                new Reference(SerializerContextBuilderInterface::class),
                new Reference(NormalizerInterface::class),
                new Reference(SerializeFormatResolver::class),
            ]
        )
        ->tag('api_platform.data_transformer');

    $services
        ->set('silverback.helper.component_position_sort_value')
        ->class(ComponentPositionSortValueHelper::class);

    $services
        ->set(ComponentPositionValidator::class)
        ->args([
            new Reference(IriConverterInterface::class),
        ])
        ->tag('validator.constraint_validator');

    $services
        ->set(ComponentPropertyMetadataFactory::class)
        ->decorate('api_platform.metadata.property.metadata_factory')
        ->args(
            [
                new Reference(ComponentPropertyMetadataFactory::class . '.inner'),
            ]
        );

    $services
        ->set(DenyAccessListener::class)
        ->args(
            [
                new Reference('silverback.repository.route'),
                new Reference(AbstractPageDataRepository::class),
                new Reference(Security::class),
                new Reference(IriConverterInterface::class),
                new Reference(HttpKernelInterface::class),
            ]
        )
        ->tag('kernel.event_listener', ['event' => RequestEvent::class, 'priority' => EventPriorities::PRE_DESERIALIZE, 'method' => 'onPreDeserialize']);

    $services
        ->set(DownloadAction::class)
        ->tag('controller.service_arguments');

    $services
        ->set(EmailAddressManager::class)
        ->args(
            [
                new Reference(EntityManagerInterface::class),
                new Reference('silverback.repository.user'),
                new Reference(EncoderFactoryInterface::class),
                new Reference(UserDataProcessor::class),
                new Reference(UserEventListener::class),
            ]
        );

    $services
        ->set(EmailAddressConfirmAction::class)
        ->args(
            [
                new Reference(EmailAddressManager::class),
            ]
        )
        ->tag('controller.service_arguments');

    $services
        ->set(EntityPersistFormListener::class)
        ->abstract()
        ->call(
            'init',
            [
                new Reference(ManagerRegistry::class),
                new Reference(TimestampedAnnotationReader::class),
                new Reference('silverback.helper.timestamped_data_persister'),
                new Reference(UserEventListener::class),
                new Reference(NormalizerInterface::class),
                new Reference(UserDataProcessor::class),
            ]
        );

    $services
        ->set(FileInfoCacheManager::class)
        ->args(
            [
                new Reference(EntityManagerInterface::class),
                new Reference(FileInfoRepository::class),
            ]
        );

    $services
        ->set(FileInfoRepository::class)
        ->args(
            [
                new Reference(ManagerRegistry::class),
            ]
        )
        ->tag('doctrine.repository_service');

    $services
        ->set(FilesystemProvider::class)
        ->args([tagged_locator(FilesystemProvider::FILESYSTEM_ADAPTER_TAG, 'alias')]);

    $services
        ->set(FlysystemDataLoader::class)
        ->args(
            [
                new Reference(FilesystemProvider::class),
            ]
        )
        ->tag('liip_imagine.binary.loader', ['loader' => 'silverback.api_components.liip_imagine.binary.loader']);

    $services
        ->set(FormCachePurgeCommand::class)
        ->tag('console.command')
        ->args(
            [
                new Reference(FormCachePurger::class),
                new Reference(EventDispatcherInterface::class),
            ]
        );

    $services
        ->set(FormCachePurger::class)
        ->args(
            [
                new Reference(EntityManagerInterface::class),
                new Reference(EventDispatcherInterface::class),
            ]
        );

    $services
        ->set(FormSubmitEventListener::class)
        ->args(
            [
                new Reference(FormSubmitHelper::class),
                new Reference(SerializeFormatResolver::class),
                new Reference(SerializerInterface::class),
            ]
        )
        ->tag('kernel.event_listener', ['event' => ViewEvent::class, 'priority' => EventPriorities::PRE_SERIALIZE, 'method' => 'onPreSerialize'])
        ->tag('kernel.event_listener', ['event' => ResponseEvent::class, 'priority' => EventPriorities::POST_RESPOND, 'method' => 'onPostRespond']);

    $services
        ->set(FormSubmitHelper::class)
        ->args(
            [
                new Reference(FormFactoryInterface::class),
                new Reference(EventDispatcherInterface::class),
            ]
        );

    $services
        ->set(FormOutputDataTransformer::class)
        ->autoconfigure(false)
        ->args(
            [
                new Reference(FormViewFactory::class),
            ]
        )
        ->tag('api_platform.data_transformer');

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
        ->args(
            [
                new Reference(FormFactoryInterface::class),
                new Reference(IriConverterInterface::class),
                new Reference(UrlHelper::class),
            ]
        );

    $services
        ->set(ImagineEventListener::class)
        ->args(
            [
                new Reference(FileInfoCacheManager::class),
            ]
        )
        ->tag('kernel.event_listener', ['event' => ImagineStoreEvent::class, 'method' => 'onStore'])
        ->tag('kernel.event_listener', ['event' => ImagineRemoveEvent::class, 'method' => 'onRemove']);

    $services
        ->set(ImagineFiltersPropertyMetadataFactory::class)
        ->decorate('api_platform.metadata.property.metadata_factory')
        ->args(
            [
                new Reference(ImagineFiltersPropertyMetadataFactory::class . '.inner'),
            ]
        );

    $services
        ->set(LayoutRepository::class)
        ->args(
            [
                new Reference(ManagerRegistry::class),
            ]
        )
        ->tag('doctrine.repository_service');

    $services
        ->set(MediaObjectFactory::class)
        ->args(
            [
                new Reference(ManagerRegistry::class),
                new Reference(FileInfoCacheManager::class),
                new Reference(UploadableAnnotationReader::class),
                new Reference(FilesystemProvider::class),
                new Reference(FlysystemDataLoader::class),
                new Reference(RequestStack::class),
                new Reference(IriConverterInterface::class),
                new Reference(UrlHelper::class),
                null, // populated in dependency injection
            ]
        );

    $services
        ->set(MessageEventListener::class)
        ->tag('kernel.event_listener', ['event' => MessageEvent::class])
        ->args(
            [
                '%env(MAILER_EMAIL)%',
            ]
        );

    $services
        ->set(NewEmailAddressListener::class)
        ->parent(EntityPersistFormListener::class)
        ->tag('kernel.event_listener', ['event' => FormSuccessEvent::class]);

    $services
        ->set(NewEmailAddressType::class)
        ->args(
            [
                new Reference(Security::class),
                new Reference('silverback.repository.user'),
                '', // injected in dependency injection
            ]
        )
        ->tag('form.type');

    $services
        ->set(NewEmailAddressValidator::class)
        ->args(
            [
                new Reference('silverback.repository.user'),
            ]
        )
        ->tag('validator.constraint_validator');

    $services
        ->set(PageDataProvider::class)
        ->args([
            new Reference(RequestStack::class),
            new Reference('silverback.repository.route'),
            new Reference('api_platform.iri_converter'),
        ]);

    $services
        ->set(PasswordChangedEmailFactory::class)
        ->parent(AbstractUserEmailFactory::class)
        ->tag('container.service_subscriber');

    $services
        ->set(PasswordResetEmailFactory::class)
        ->parent(AbstractUserEmailFactory::class)
        ->tag('container.service_subscriber');

    $services
        ->set(PasswordRequestAction::class)
        ->args(
            [
                new Reference(UserDataProcessor::class),
                new Reference(EntityManagerInterface::class),
                new Reference(UserMailer::class),
            ]
        )
        ->tag('controller.service_arguments');

    $services
        ->set(PasswordUpdateType::class)
        ->args(
            [
                new Reference(RequestStack::class),
                '', // injected in dependency injection
            ]
        )
        ->tag('form.type');

    $services
        ->set(PasswordUpdateListener::class)
        ->args(
            [
                new Reference(UserDataProcessor::class),
                new Reference(UserMailer::class),
            ]
        )
        ->tag('kernel.event_listener', ['event' => FormSuccessEvent::class]);

    $services
        ->set(PublishableAnnotationReader::class)
        ->parent(AnnotationReader::class);

    $services
        ->set(PublishableContextBuilder::class)
        ->decorate('api_platform.serializer.context_builder')
        ->args(
            [
                new Reference(PublishableContextBuilder::class . '.inner'),
                new Reference(PublishableStatusChecker::class),
            ]
        )
        ->autoconfigure(false);

    $services
        ->set(PublishableEventListener::class)
        ->args(
            [
                new Reference(PublishableStatusChecker::class),
                new Reference('doctrine'),
                new Reference('api_platform.validator'),
            ]
        )
        ->tag('kernel.event_listener', ['event' => RequestEvent::class, 'priority' => EventPriorities::POST_READ, 'method' => 'onPostRead'])
        ->tag('kernel.event_listener', ['event' => ViewEvent::class, 'priority' => EventPriorities::PRE_WRITE, 'method' => 'onPreWrite'])
        ->tag('kernel.event_listener', ['event' => RequestEvent::class, 'priority' => EventPriorities::POST_DESERIALIZE, 'method' => 'onPostDeserialize'])
        ->tag('kernel.event_listener', ['event' => ResponseEvent::class, 'priority' => EventPriorities::POST_RESPOND, 'method' => 'onPostRespond']);

    $services
        ->set(PublishableStatusChecker::class)
        ->args(
            [
                new Reference(ManagerRegistry::class),
                new Reference(PublishableAnnotationReader::class),
                new Reference(AuthorizationCheckerInterface::class),
                '', // injected with dependency injection
            ]
        );

    $services
        ->set(PublishableListener::class)
        ->args([new Reference(PublishableAnnotationReader::class)])
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata']);

    // High priority for item because of queryBuilder reset
    $services
        ->set(PublishableExtension::class)
        ->args(
            [
                new Reference(PublishableStatusChecker::class),
                new Reference('request_stack'),
                new Reference('doctrine'),
            ]
        )
        ->tag('api_platform.doctrine.orm.query_extension.item', ['priority' => 100])
        ->tag('api_platform.doctrine.orm.query_extension.collection');

    $services
        ->set(PublishableValidator::class)
        ->decorate('api_platform.validator')
        ->args(
            [
                new Reference(PublishableValidator::class . '.inner'),
                new Reference(PublishableStatusChecker::class),
            ]
        );

    $services
        ->set(PublishableLoader::class)
        ->args(
            [
                new Reference('annotations.reader'),
            ]
        );

    $services
        ->set('silverback.security.jwt_manager')
        ->class(JWTManager::class)
        ->decorate('lexik_jwt_authentication.jwt_manager')
        ->args(
            [
                new Reference('silverback.security.jwt_manager.inner'),
                new Reference('event_dispatcher'),
                '', // injected in dependency injection
                '', // injected in dependency injection
            ]
        )
        ->autoconfigure(false);
    $services->alias(JWTManager::class, 'silverback.security.jwt_manager');

    $services
        ->set('silverback.security.jwt_event_listener')
        ->class(JWTEventListener::class)
        ->args(
            [
                new Reference('security.role_hierarchy'),
                '', // injected in dependency injection
            ]
        )
        ->tag('kernel.event_listener', ['event' => Events::JWT_CREATED, 'method' => 'onJWTCreated'])
        ->tag('kernel.event_listener', ['event' => JWTRefreshedEvent::class, 'method' => 'onJWTRefreshed'])
        ->tag('kernel.event_listener', ['event' => KernelEvents::RESPONSE, 'method' => 'onKernelResponse']);
    $services->alias(JWTEventListener::class, 'silverback.security.jwt_event_listener');

    $services
        ->set('silverback.security.jwt_invalid_event_listener')
        ->class(JWTInvalidEventListener::class)
        ->args([
            '', // injected in dependency injection
        ])
        ->tag('kernel.event_listener', ['event' => Events::JWT_INVALID, 'method' => 'onJwtInvalid']);
    $services->alias(JWTInvalidEventListener::class, 'silverback.security.jwt_invalid_event_listener');

    if (class_exists(LogoutEvent::class)) {
        $services
            ->set('silverback.security.logout_listener')
            ->class(LogoutListener::class)
            ->args(
                [
                    '', // injected in dependency injection
                    '', // injected in dependency injection
                ]
            )
            ->tag('kernel.event_listener', ['event' => LogoutEvent::class]);
    } else {
        // Deprecated from Symfony 5.1
        $services
            ->set('silverback.security.logout_handler')
            ->class(LogoutHandler::class)
            ->args(
                [
                    '', // injected in dependency injection
                    '', // injected in dependency injection
                ]
            );
    }

    $services
        ->set('silverback.api_components.refresh_token.storage.doctrine', DoctrineRefreshTokenStorage::class)
        ->args(
            [
                new Reference('doctrine'),
                '%silverback.api_components.refresh_token.ttl%',
            ]
        );
    $services->alias(DoctrineRefreshTokenStorage::class, 'silverback.api_components.refresh_token.storage.doctrine');

    $services
        ->set(ResourceIriValidator::class)
        ->args(
            [
                new Reference(ApiResourceRouteFinder::class),
            ]
        )
        ->tag('validator.constraint_validator');

    $services
        ->set(RefererUrlResolver::class)
        ->args(
            [
                new Reference(RequestStack::class),
            ]
        );

    $services
        ->set('silverback.command.refresh_tokens_expire')
        ->class(RefreshTokensExpireCommand::class)
        ->tag('console.command')
        ->args(
            [
                '', // injected in dependency injection
                new Reference('silverback.repository.user'),
            ]
        );

    $services
        ->set(RouteDataProvider::class)
        ->args(
            [
                new Reference('silverback.repository.route'),
                new Reference('api_platform.item_data_provider'),
            ]
        )
        ->autoconfigure(false)
        ->tag('api_platform.item_data_provider', ['priority' => 1]);

    $services
        ->set('silverback.event_listener.api.route_event_listener')
        ->class(RouteEventListener::class)
        ->args([
            new Reference('silverback.helper.route_generator'),
            new Reference(ManagerRegistry::class),
        ])
        ->tag('kernel.event_listener', ['event' => ViewEvent::class, 'priority' => EventPriorities::POST_VALIDATE, 'method' => 'onPostValidate'])
        ->tag('kernel.event_listener', ['event' => ViewEvent::class, 'priority' => EventPriorities::POST_WRITE, 'method' => 'onPostWrite']);

    $services
        ->set(RouteExtension::class)
        ->args(
            [
                '', // added in dependency injection
                new Reference('api_platform.security.resource_access_checker'),
            ]
        )
        ->tag('api_platform.doctrine.orm.query_extension.collection');

    $services
        ->set('silverback.helper.route_generator')
        ->class(RouteGenerator::class)
        ->args([
            new Reference('cocur_slugify'),
            new Reference(ManagerRegistry::class),
            new Reference('silverback.helper.timestamped_data_persister'),
            new Reference('silverback.repository.route'),
        ]);
    $services->alias(RouteGeneratorInterface::class, 'silverback.helper.route_generator');

    // Having trouble setting the repository with a service ID.
    // Doctrine reporting that the repository class is not tagged
    // and if I use service ID that it does not exist...
    $services
        ->set(RouteRepository::class)
        ->args(
            [
                new Reference(ManagerRegistry::class),
            ]
        )
        ->tag('doctrine.repository_service');
    $services->alias('silverback.repository.route', RouteRepository::class);

    $services
        ->set(RouteVoter::class)
        ->args([
            '', // added in dependency injection
            new Reference('api_platform.security.resource_access_checker'),
        ])
        ->tag('security.voter');

    $services
        ->set(RoutableExtension::class)
        ->args(
            [
                '', // added in dependency injection
                new Reference('api_platform.security.resource_access_checker'),
            ]
        )
        ->tag('api_platform.doctrine.orm.query_extension.collection');

    $services
        ->set(RoutableVoter::class)
        ->args([
            '', // added in dependency injection
            new Reference('api_platform.security.resource_access_checker'),
        ])
        ->tag('security.voter');

    $services
        ->set(RoutingPrefixResourceMetadataFactory::class)
        ->decorate('api_platform.metadata.resource.metadata_factory')
        ->args(
            [
                new Reference(RoutingPrefixResourceMetadataFactory::class . '.inner'),
            ]
        );

    $services
        ->set(RoutableResourceMetadataFactory::class)
        ->decorate('api_platform.metadata.resource.metadata_factory')
        ->args(
            [
                new Reference(RoutableResourceMetadataFactory::class . '.inner'),
            ]
        );

    $services
        ->set(SerializeFormatResolver::class)
        ->args(
            [
                new Reference(RequestStack::class),
                'jsonld',
            ]
        );

    $services
        ->set(TablePrefixExtension::class)
        ->args(
            [
                '', // injected in dependency injection
            ]
        )
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata']);

    $services
        ->set(TimestampedAnnotationReader::class)
        ->parent(AnnotationReader::class);

    $services
        ->set(TimestampedContextBuilder::class)
        ->decorate('api_platform.serializer.context_builder', null, -1)
        ->args(
            [
                new Reference(TimestampedContextBuilder::class . '.inner'),
            ]
        )
        ->autoconfigure(false);

    $services
        ->set('silverback.helper.timestamped_data_persister')
        ->class(TimestampedDataPersister::class)
        ->args(
            [
                new Reference(ManagerRegistry::class),
                new Reference(TimestampedAnnotationReader::class),
            ]
        );
    $services->alias(TimestampedDataPersister::class, 'silverback.helper.timestamped_data_persister');

    $getTimestampedListenerTagArgs = static function ($event) {
        return [
            'event' => $event,
            'method' => $event,
        ];
    };
    $services
        ->set(TimestampedListener::class)
        ->args(
            [
                new Reference(TimestampedAnnotationReader::class),
            ]
        )
        ->tag('doctrine.event_listener', $getTimestampedListenerTagArgs('loadClassMetadata'));

    $services
        ->set(TimestampedLoader::class)
        ->args(
            [
                new Reference('annotations.reader'),
            ]
        );

    $services
        ->set(TimestampedValidatorMappingLoader::class)
        ->args(
            [
                new Reference(TimestampedAnnotationReader::class),
            ]
        );

    $services
        ->set(TimestampedValidator::class)
        ->decorate('api_platform.validator')
        ->args(
            [
                new Reference(TimestampedValidator::class . '.inner'),
                new Reference(TimestampedAnnotationReader::class),
            ]
        );

    $services
        ->set(UploadAction::class)
        ->tag('controller.service_arguments');

    $services
        ->set(UploadableAnnotationReader::class)
        ->parent(AnnotationReader::class);

    $services
        ->set(UploadableContextBuilder::class)
        ->decorate('api_platform.serializer.context_builder', null, -1)
        ->args(
            [
                new Reference(UploadableContextBuilder::class . '.inner'),
            ]
        )
        ->autoconfigure(false);

    $services
        ->set(UploadableEventListener::class)
        ->args(
            [
                new Reference(UploadableAnnotationReader::class),
                new Reference(UploadableFileManager::class),
            ]
        )
        ->tag('kernel.event_listener', ['event' => ViewEvent::class, 'priority' => EventPriorities::PRE_WRITE, 'method' => 'onPreWrite'])
        ->tag('kernel.event_listener', ['event' => ViewEvent::class, 'priority' => EventPriorities::POST_WRITE, 'method' => 'onPostWrite']);

    $services
        ->set(UploadableFileManager::class)
        ->args(
            [
                new Reference(ManagerRegistry::class),
                new Reference(UploadableAnnotationReader::class),
                new Reference(FilesystemProvider::class),
                new Reference(FlysystemDataLoader::class),
                new Reference(FileInfoCacheManager::class),
                null, // set in dependency injection if imagine exists
                null, // Set in dependency injection if imagine cache manager exists
            ]
        );

    $services
        ->set(UploadableListener::class)
        ->args(
            [
                new Reference(UploadableAnnotationReader::class),
            ]
        )
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata']);

    $services
        ->set(UploadableLoader::class)
        ->args(
            [
                new Reference('annotations.reader'),
                new Reference(UploadableAnnotationReader::class),
            ]
        );

    $services
        ->set(UploadableResourceMetadataFactory::class)
        ->decorate('api_platform.metadata.resource.metadata_factory')
        ->args(
            [
                new Reference(UploadableResourceMetadataFactory::class . '.inner'),
                new Reference(UploadableAnnotationReader::class),
                new Reference('api_platform.path_segment_name_generator'),
            ]
        )
        ->autoconfigure(false);

//    COMPILER PASS REQUIRED AS WELL
//    $services
//        ->set(UploadableLoader::class)
//        ->args([
//            new Reference(UploadableAnnotationReader::class),
//        ]);

    $services
        ->set(UserChecker::class)
        ->args(
            [
                '', // injected in dependency injection
            ]
        );

    $services
        ->set(UserContextBuilder::class)
        ->decorate('api_platform.serializer.context_builder')
        ->args(
            [
                new Reference(UserContextBuilder::class . '.inner'),
                new Reference(AuthorizationCheckerInterface::class),
            ]
        )
        ->autoconfigure(false);

    $services
        ->set(UserCreateCommand::class)
        ->tag('console.command')
        ->args(
            [
                new Reference(UserFactory::class),
            ]
        );

    $services
        ->set(UserDataProvider::class)
        ->args(
            [
                new Reference('silverback.repository.user'),
            ]
        )
        ->autoconfigure(false)
        ->tag('api_platform.item_data_provider', ['priority' => 1]);

    $services
        ->set(UserEnabledEmailFactory::class)
        ->parent(AbstractUserEmailFactory::class)
        ->tag('container.service_subscriber');

    $services
        ->set(UserEventListener::class)
        ->args(
            [
                new Reference(UserMailer::class),
                new Reference(Security::class),
            ]
        )
        ->tag('kernel.event_listener', ['event' => ViewEvent::class, 'priority' => EventPriorities::POST_WRITE, 'method' => 'onPostWrite'])
        ->tag('kernel.event_listener', ['event' => RequestEvent::class, 'priority' => EventPriorities::PRE_READ, 'method' => 'onPreRead']);

    $services
        ->set(UserFactory::class)
        ->args(
            [
                new Reference(EntityManagerInterface::class),
                new Reference(ValidatorInterface::class),
                new Reference('silverback.repository.user'),
                new Reference('silverback.helper.timestamped_data_persister'),
                new Reference(UserPasswordEncoderInterface::class),
                '', // injected in dependency injection
            ]
        );

    $services
        ->set(UserLoginType::class)
        ->args([new Reference(RouterInterface::class)])
        ->tag('form.type');

    $services
        ->set(UserMailer::class)
        ->args(
            [
                new Reference(MailerInterface::class),
                new Reference(ContainerInterface::class),
                '', // injected in dependency injection
            ]
        )
        ->tag('container.service_subscriber');

    $services
        ->set(UsernameChangedEmailFactory::class)
        ->parent(AbstractUserEmailFactory::class)
        ->tag('container.service_subscriber');

    $services
        ->set(UserDataProcessor::class)
        ->args(
            [
                new Reference(UserPasswordEncoderInterface::class),
                new Reference('silverback.repository.user'),
                new Reference(EncoderFactoryInterface::class),
                '', // injected in dependency injection
                '', // injected in dependency injection
                '', // injected in dependency injection
                '', // injected in dependency injection
            ]
        );

    $services
        ->set(UserPasswordValidator::class)
        ->args([
            new Reference(TokenStorageInterface::class),
            new Reference(EncoderFactoryInterface::class),
            new Reference('silverback.repository.user'),
        ])
        ->decorate('security.validator.user_password');

    $services
        ->set(UserRegisterListener::class)
        ->parent(EntityPersistFormListener::class)
        ->tag('kernel.event_listener', ['event' => FormSuccessEvent::class]);

    $services
        ->set(UserRegisterType::class)
        ->args(
            [
                '', // injected in dependency injection
            ]
        )
        ->tag('form.type');

    $services
        ->set(UserRepositoryInterface::class)
        ->class(UserRepository::class)
        ->args(
            [
                new Reference(ManagerRegistry::class),
                '', // injected in dependency injection
                '', // injected in dependency injection
                '', // injected in dependency injection
            ]
        )
        ->tag('doctrine.repository_service');
    $services->alias('silverback.repository.user', UserRepositoryInterface::class);

    $services
        ->set(UserResourceMetadataFactory::class)
        ->decorate('api_platform.metadata.resource.metadata_factory')
        ->args(
            [
                new Reference(UserResourceMetadataFactory::class . '.inner'),
            ]
        );

    $services
        ->set(UuidNormalizer::class)
        ->decorate('api_platform.identifier.uuid_normalizer')
        ->args([
            new Reference(UuidNormalizer::class . '.inner'),
        ]);

    $services
        ->set(VerifyEmailAddressAction::class)
        ->args(
            [
                new Reference(EmailAddressManager::class),
            ]
        )
        ->tag('controller.service_arguments');

    $services
        ->set(VerifyEmailFactory::class)
        ->parent(AbstractUserEmailFactory::class)
        ->tag('container.service_subscriber');

    $services
        ->set(WelcomeEmailFactory::class)
        ->parent(AbstractUserEmailFactory::class)
        ->tag('container.service_subscriber');

    $services->alias(Environment::class, 'twig');

    $services
        ->set('silverback.metadata.resource.metadata_factory')
        ->class(PageDataMetadataFactory::class)
        ->args(
            [
                new Reference('doctrine'),
                new Reference('api_platform.metadata.resource.metadata_factory'),
            ]
        );

    $services
        ->alias(PageDataMetadataFactoryInterface::class, 'silverback.metadata.resource.metadata_factory');

    $services
        ->set('silverback.metadata.resource.metadata_factory.cached')
        ->decorate('silverback.metadata.resource.metadata_factory')
        ->class(CachedPageDataMetadataFactory::class)
        ->args(
            [
                new Reference('api_platform.cache.metadata.resource'),
                new Reference('silverback.metadata.resource.metadata_factory.cached.inner'),
            ]
        );
};
