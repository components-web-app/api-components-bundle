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

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Serializer\SerializerContextBuilderInterface;
use ApiPlatform\Symfony\EventListener\EventPriorities;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events as DoctrineEvents;
use Doctrine\Persistence\ManagerRegistry;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Silverback\ApiComponentsBundle\Action\Uploadable\DownloadAction;
use Silverback\ApiComponentsBundle\Action\Uploadable\UploadAction;
use Silverback\ApiComponentsBundle\Action\User\EmailAddressConfirmAction;
use Silverback\ApiComponentsBundle\Action\User\PasswordRequestAction;
use Silverback\ApiComponentsBundle\Action\User\VerifyEmailAddressAction;
use Silverback\ApiComponentsBundle\ApiPlatform\Api\IriConverter;
use Silverback\ApiComponentsBundle\ApiPlatform\Api\MercureIriConverter;
use Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Property\ComponentPropertyMetadataFactory;
use Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Property\ImagineFiltersPropertyMetadataFactory;
use Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource\ComponentResourceMetadataFactory;
use Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource\RoutableResourceMetadataCollectionFactory;
use Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource\RoutingPrefixResourceMetadataCollectionFactory;
use Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource\UploadableResourceMetadataCollectionFactory;
use Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource\UserResourceMetadataCollectionFactory;
use Silverback\ApiComponentsBundle\ApiPlatform\Serializer\VersionedDocumentationNormalizer;
use Silverback\ApiComponentsBundle\AttributeReader\AttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\TimestampedAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Command\FormCachePurgeCommand;
use Silverback\ApiComponentsBundle\Command\RefreshTokensExpireCommand;
use Silverback\ApiComponentsBundle\Command\UserCreateCommand;
use Silverback\ApiComponentsBundle\DataProvider\PageDataProvider;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\PageDataMetadataStateProvider;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\RouteStateProvider;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\UserStateProvider;
use Silverback\ApiComponentsBundle\Doctrine\Extension\ORM\PublishableExtension;
use Silverback\ApiComponentsBundle\Doctrine\Extension\ORM\RoutableExtension;
use Silverback\ApiComponentsBundle\Doctrine\Extension\ORM\RouteExtension;
use Silverback\ApiComponentsBundle\Doctrine\Extension\ORM\TablePrefixExtension;
use Silverback\ApiComponentsBundle\Event\FormSuccessEvent;
use Silverback\ApiComponentsBundle\Event\ImagineRemoveEvent;
use Silverback\ApiComponentsBundle\Event\ImagineStoreEvent;
use Silverback\ApiComponentsBundle\Event\JWTRefreshedEvent;
use Silverback\ApiComponentsBundle\Event\ResourceChangedEvent;
use Silverback\ApiComponentsBundle\EventListener\Api\CollectionApiEventListener;
use Silverback\ApiComponentsBundle\EventListener\Api\ComponentPositionEventListener;
use Silverback\ApiComponentsBundle\EventListener\Api\ComponentUsageEventListener;
use Silverback\ApiComponentsBundle\EventListener\Api\FormApiEventListener;
use Silverback\ApiComponentsBundle\EventListener\Api\OrphanedComponentEventListener;
use Silverback\ApiComponentsBundle\EventListener\Api\PublishableEventListener;
use Silverback\ApiComponentsBundle\EventListener\Api\RouteEventListener;
use Silverback\ApiComponentsBundle\EventListener\Api\UploadableEventListener;
use Silverback\ApiComponentsBundle\EventListener\Api\UserEventListener;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\PropagateUpdatesListener;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\PublishableListener;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\SqlLiteForeignKeyEnabler;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\TimestampedListener;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\UploadableListener;
use Silverback\ApiComponentsBundle\EventListener\Form\EntityPersistFormListener;
use Silverback\ApiComponentsBundle\EventListener\Form\User\ChangePasswordListener;
use Silverback\ApiComponentsBundle\EventListener\Form\User\NewEmailAddressListener;
use Silverback\ApiComponentsBundle\EventListener\Form\User\PasswordUpdateListener;
use Silverback\ApiComponentsBundle\EventListener\Form\User\UserRegisterListener;
use Silverback\ApiComponentsBundle\EventListener\Imagine\ImagineEventListener;
use Silverback\ApiComponentsBundle\EventListener\Jwt\JWTClearTokenListener;
use Silverback\ApiComponentsBundle\EventListener\Jwt\JWTEventListener;
use Silverback\ApiComponentsBundle\EventListener\Mailer\MessageEventListener;
use Silverback\ApiComponentsBundle\EventListener\ResourceChangedEventListener;
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
use Silverback\ApiComponentsBundle\Filter\OrSearchFilter;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemFactory;
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
use Silverback\ApiComponentsBundle\Mercure\MercureAuthorization;
use Silverback\ApiComponentsBundle\Mercure\PublishableAwareHub;
use Silverback\ApiComponentsBundle\Metadata\Factory\CachedPageDataMetadataFactory;
use Silverback\ApiComponentsBundle\Metadata\Factory\ComponentUsageMetadataFactory;
use Silverback\ApiComponentsBundle\Metadata\Factory\PageDataMetadataFactory;
use Silverback\ApiComponentsBundle\Metadata\Factory\PageDataMetadataFactoryInterface;
use Silverback\ApiComponentsBundle\Metadata\Provider\PageDataMetadataProvider;
use Silverback\ApiComponentsBundle\RamseyUuid\UuidUriVariableTransformer\UuidUriVariableTransformer;
use Silverback\ApiComponentsBundle\RefreshToken\Storage\DoctrineRefreshTokenStorage;
use Silverback\ApiComponentsBundle\Repository\Core\AbstractPageDataRepository;
use Silverback\ApiComponentsBundle\Repository\Core\ComponentPositionRepository;
use Silverback\ApiComponentsBundle\Repository\Core\FileInfoRepository;
use Silverback\ApiComponentsBundle\Repository\Core\LayoutRepository;
use Silverback\ApiComponentsBundle\Repository\Core\RouteRepository;
use Silverback\ApiComponentsBundle\Repository\User\UserRepository;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;
use Silverback\ApiComponentsBundle\Security\EventListener\DenyAccessListener;
use Silverback\ApiComponentsBundle\Security\EventListener\LogoutListener;
use Silverback\ApiComponentsBundle\Security\JWTManager;
use Silverback\ApiComponentsBundle\Security\UserChecker;
use Silverback\ApiComponentsBundle\Security\Voter\ComponentVoter;
use Silverback\ApiComponentsBundle\Security\Voter\RoutableVoter;
use Silverback\ApiComponentsBundle\Security\Voter\RouteVoter;
use Silverback\ApiComponentsBundle\Serializer\ContextBuilder\ComponentPositionContextBuilder;
use Silverback\ApiComponentsBundle\Serializer\ContextBuilder\CwaResourceContextBuilder;
use Silverback\ApiComponentsBundle\Serializer\ContextBuilder\PublishableContextBuilder;
use Silverback\ApiComponentsBundle\Serializer\ContextBuilder\TimestampedContextBuilder;
use Silverback\ApiComponentsBundle\Serializer\ContextBuilder\UploadableContextBuilder;
use Silverback\ApiComponentsBundle\Serializer\ContextBuilder\UserContextBuilder;
use Silverback\ApiComponentsBundle\Serializer\MappingLoader\CwaResourceLoader;
use Silverback\ApiComponentsBundle\Serializer\MappingLoader\PublishableLoader;
use Silverback\ApiComponentsBundle\Serializer\MappingLoader\TimestampedLoader;
use Silverback\ApiComponentsBundle\Serializer\MappingLoader\UploadableLoader;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\PublishableNormalizer;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadataProvider;
use Silverback\ApiComponentsBundle\Serializer\SerializeFormatResolver;
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
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service_locator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_locator;

/*
 * @author Daniel West <daniel@silverback.is>
 */
return static function (ContainerConfigurator $configurator) {
    $services = $configurator->services();

    $services
        ->set('silverback.doctrine.repository.page_data')
        ->class(AbstractPageDataRepository::class)
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
                '$container' => service_locator([
                    'twig' => new Reference('twig'),
                    RequestStack::class => new Reference('request_stack'),
                    RefererUrlResolver::class => new Reference(RefererUrlResolver::class),
                ]),
                '$eventDispatcher' => new Reference(EventDispatcherInterface::class),
            ]
        );

    $services
        ->set(AttributeReader::class)
        ->abstract()
        ->args(
            [
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
        ->parent(AbstractUserEmailFactory::class);

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
        ->set(ComponentPositionContextBuilder::class)
        ->decorate('api_platform.serializer.context_builder')
        ->args(
            [
                new Reference(ComponentPositionContextBuilder::class . '.inner'),
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
        ->set(CollectionApiEventListener::class)
        ->autoconfigure(false)
        ->args(
            [
                new Reference(ApiResourceRouteFinder::class),
                new Reference('api_platform.state_provider'),
                new Reference(RequestStack::class),
                new Reference(SerializerContextBuilderInterface::class),
                new Reference(NormalizerInterface::class),
                new Reference(SerializeFormatResolver::class),
                new Reference('api_platform.metadata.resource.metadata_collection_factory'),
            ]
        )
        ->tag('kernel.event_listener', ['event' => ViewEvent::class, 'priority' => EventPriorities::PRE_SERIALIZE, 'method' => 'onPreSerialize']);

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
                new Reference(Security::class),
                new Reference('silverback.doctrine.repository.route'),
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
                new Reference(PasswordHasherFactoryInterface::class),
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
                new Reference(TimestampedAttributeReader::class),
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
        ->set(FilesystemFactory::class)
        ->args([tagged_locator(FilesystemProvider::FILESYSTEM_ADAPTER_TAG, 'alias')]);

    $services
        ->set(FilesystemProvider::class)
        ->args([tagged_locator(FilesystemProvider::FILESYSTEM_TAG, 'alias')]);

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
        ->set(FormApiEventListener::class)
        ->args(
            [
                new Reference(FormSubmitHelper::class),
                new Reference(SerializeFormatResolver::class),
                new Reference(SerializerInterface::class),
                new Reference(FormViewFactory::class),
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
                new Reference(UploadableAttributeReader::class),
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
            new Reference('silverback.doctrine.repository.route'),
            new Reference('api_platform.iri_converter'),
            new Reference('silverback.metadata.api.component_resource_metadata_factory'),
            new Reference('silverback.metadata_provider.page_data'),
            new Reference(ManagerRegistry::class),
        ]);

    $services
        ->set(PasswordChangedEmailFactory::class)
        ->parent(AbstractUserEmailFactory::class);

    $services
        ->set(PasswordResetEmailFactory::class)
        ->parent(AbstractUserEmailFactory::class);

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
        ->set(PublishableAttributeReader::class)
        ->parent(AttributeReader::class);

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
                new Reference(PublishableAttributeReader::class),
                new Reference(AuthorizationCheckerInterface::class),
                '', // injected with dependency injection
            ]
        );

    $services
        ->set(PublishableListener::class)
        ->args([new Reference(PublishableAttributeReader::class)])
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata']);

    $services
        ->set(MercureAuthorization::class)
        ->args(
            [
                new Reference(ResourceNameCollectionFactoryInterface::class),
                new Reference(ResourceMetadataCollectionFactoryInterface::class),
                new Reference(PublishableStatusChecker::class),
                new Reference('router.request_context'),
                new Reference(Authorization::class),
                new Reference('request_stack'),
                '', // injected with dependency injection
            ]
        );

    $services
        ->set(PublishableAwareHub::class)
        ->decorate('mercure.hub.default', null, -1)
        ->args(
            [
                new Reference(PublishableAwareHub::class . '.inner'),
                new Reference(PublishableStatusChecker::class),
                new Reference(IriConverterInterface::class),
            ]
        )
        ->tag('mercure.hub');

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
        ->set(PublishableLoader::class);

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
                new Reference(MercureAuthorization::class),
            ]
        )
        ->tag('kernel.event_listener', ['event' => Events::AUTHENTICATION_SUCCESS, 'method' => 'onJWTAuthenticationSuccess'])
        ->tag('kernel.event_listener', ['event' => Events::JWT_CREATED, 'method' => 'onJWTCreated'])
        ->tag('kernel.event_listener', ['event' => JWTRefreshedEvent::class, 'method' => 'onJWTRefreshed'])
        ->tag('kernel.event_listener', ['event' => KernelEvents::RESPONSE, 'method' => 'onKernelResponse']);
    $services->alias(JWTEventListener::class, 'silverback.security.jwt_event_listener');

    $services
        ->set('silverback.security.jwt_clear_token_listener')
        ->class(JWTClearTokenListener::class)
        ->args([
            '', // injected in dependency injection
            new Reference(MercureAuthorization::class),
        ])
        ->tag('kernel.event_listener', ['event' => Events::JWT_INVALID, 'method' => 'onJwtInvalid'])
        ->tag('kernel.event_listener', ['event' => Events::JWT_EXPIRED, 'method' => 'onJwtExpired']);
    $services->alias(JWTClearTokenListener::class, 'silverback.security.jwt_clear_token_listener');

    $services
        ->set('silverback.security.logout_listener')
        ->class(LogoutListener::class)
        ->args(
            [
                '', // injected in dependency injection
                '', // injected in dependency injection
                new Reference(MercureAuthorization::class),
            ]
        )
        ->tag('kernel.event_listener', ['event' => LogoutEvent::class]);

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
        ->set(ResourceChangedEventListener::class)
        ->tag('kernel.event_listener', ['event' => ResourceChangedEvent::class])
        ->args(
            [
                '$resourceChangedPropagators' => new TaggedIteratorArgument('silverback_api_components.resource_changed_propagator'),
            ]
        );

    $services
        ->set(ResourceMetadataProvider::class);

    $services
        ->set(RouteStateProvider::class)
        ->args(
            [
                new Reference('silverback.doctrine.repository.route'),
                new Reference('api_platform.state_provider'),
            ]
        )
        ->autoconfigure(false)
        ->tag('api_platform.state_provider');

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
            new Reference('silverback.doctrine.repository.route'),
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
    $services->alias('silverback.doctrine.repository.route', RouteRepository::class);

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
            new Reference(Security::class),
            new Reference('silverback.doctrine.repository.page_data'),
            new Reference(DenyAccessListener::class),
        ])
        ->tag('security.voter');

    $services
        ->set(RoutingPrefixResourceMetadataCollectionFactory::class)
        ->decorate('api_platform.metadata.resource.metadata_collection_factory')
        ->args(
            [
                new Reference(RoutingPrefixResourceMetadataCollectionFactory::class . '.inner'),
            ]
        );

    $services
        ->set(RoutableResourceMetadataCollectionFactory::class)
        ->decorate('api_platform.metadata.resource.metadata_collection_factory')
        ->args(
            [
                new Reference(RoutableResourceMetadataCollectionFactory::class . '.inner'),
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
        ->set(TimestampedAttributeReader::class)
        ->parent(AttributeReader::class);

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
                new Reference(TimestampedAttributeReader::class),
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
                new Reference(TimestampedAttributeReader::class),
            ]
        )
        ->tag('doctrine.event_listener', $getTimestampedListenerTagArgs('loadClassMetadata'));

    $services
        ->set(TimestampedLoader::class);

    $services
        ->set(TimestampedValidatorMappingLoader::class)
        ->args(
            [
                new Reference(TimestampedAttributeReader::class),
            ]
        );

    $services
        ->set(TimestampedValidator::class)
        ->decorate('api_platform.validator')
        ->args(
            [
                new Reference(TimestampedValidator::class . '.inner'),
                new Reference(TimestampedAttributeReader::class),
            ]
        );

    $services
        ->set(UploadAction::class)
        ->tag('controller.service_arguments')
        ->args([
            '$publishableNormalizer' => new Reference(PublishableNormalizer::class),
        ]);

    $services
        ->set(UploadableAttributeReader::class)
        ->parent(AttributeReader::class);

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
                new Reference(UploadableAttributeReader::class),
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
                new Reference(UploadableAttributeReader::class),
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
                new Reference(UploadableAttributeReader::class),
            ]
        )
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata']);

    $services
        ->set(UploadableLoader::class)
        ->args(
            [
                new Reference(UploadableAttributeReader::class),
            ]
        );

    $services
        ->set(UploadableResourceMetadataCollectionFactory::class)
        ->decorate('api_platform.metadata.resource.metadata_collection_factory')
        ->args(
            [
                new Reference(UploadableResourceMetadataCollectionFactory::class . '.inner'),
                new Reference(UploadableAttributeReader::class),
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
        ->set(UserStateProvider::class)
        ->args(
            [
                new Reference('silverback.repository.user'),
                new Reference('request_stack'),
            ]
        )
        ->autoconfigure(false)
        ->tag('api_platform.state_provider');

    $services
        ->set(UserEnabledEmailFactory::class)
        ->parent(AbstractUserEmailFactory::class);

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
                new Reference(UserPasswordHasherInterface::class),
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
                service_locator([
                    PasswordResetEmailFactory::class => new Reference(PasswordResetEmailFactory::class),
                    ChangeEmailConfirmationEmailFactory::class => new Reference(ChangeEmailConfirmationEmailFactory::class),
                    WelcomeEmailFactory::class => new Reference(WelcomeEmailFactory::class),
                    UserEnabledEmailFactory::class => new Reference(UserEnabledEmailFactory::class),
                    UsernameChangedEmailFactory::class => new Reference(UsernameChangedEmailFactory::class),
                    PasswordChangedEmailFactory::class => new Reference(PasswordChangedEmailFactory::class),
                    VerifyEmailFactory::class => new Reference(VerifyEmailFactory::class),
                ]),
                '', // injected in dependency injection
            ]
        );

    $services
        ->set(UsernameChangedEmailFactory::class)
        ->parent(AbstractUserEmailFactory::class);

    $services
        ->set(UserDataProcessor::class)
        ->args(
            [
                new Reference(UserPasswordHasherInterface::class),
                new Reference('silverback.repository.user'),
                new Reference(PasswordHasherFactoryInterface::class),
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
            new Reference(PasswordHasherFactoryInterface::class),
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
        ->set(UserResourceMetadataCollectionFactory::class)
        ->decorate('api_platform.metadata.resource.metadata_collection_factory')
        ->args(
            [
                new Reference(UserResourceMetadataCollectionFactory::class . '.inner'),
            ]
        );

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
        ->parent(AbstractUserEmailFactory::class);

    $services
        ->set(WelcomeEmailFactory::class)
        ->parent(AbstractUserEmailFactory::class);

    $services
        ->set('silverback.metadata_factory.page_data')
        ->class(PageDataMetadataFactory::class)
        ->args(
            [
                new Reference('doctrine'),
                new Reference('api_platform.metadata.resource.metadata_collection_factory'),
            ]
        );

    $services
        ->alias(PageDataMetadataFactoryInterface::class, 'silverback.metadata_factory.page_data');

    $services
        ->set('silverback.metadata_factory.page_data.cached')
        ->decorate('silverback.metadata_factory.page_data')
        ->class(CachedPageDataMetadataFactory::class)
        ->args(
            [
                new Reference('api_platform.cache.metadata.resource'),
                new Reference('silverback.metadata_factory.page_data.cached.inner'),
            ]
        );

    $services
        ->set('silverback.metadata.api.component_resource_metadata_factory')
        ->class(ComponentResourceMetadataFactory::class)
        ->decorate('api_platform.metadata.resource.metadata_collection_factory')
        ->args(
            [
                new Reference('silverback.metadata.api.component_resource_metadata_factory.inner'),
                new Reference('api_platform.path_segment_name_generator'),
            ]
        );
    $services->alias(ComponentResourceMetadataFactory::class, 'silverback.metadata.api.component_resource_metadata_factory');

    $services
        ->set('silverback.metadata_factory.component_usage')
        ->class(ComponentUsageMetadataFactory::class)
        ->args(
            [
                new Reference('silverback.doctrine.repository.component_position'),
                new Reference(PageDataProvider::class),
                new Reference(PublishableStatusChecker::class),
                new Reference('doctrine'),
            ]
        );

    $services
        ->set('silverback.event_listener.api.component_usage')
        ->class(ComponentUsageEventListener::class)
        ->args(
            [
                new Reference('silverback.metadata_factory.component_usage'),
            ]
        )
        ->tag('kernel.event_listener', ['event' => RequestEvent::class, 'priority' => EventPriorities::POST_READ, 'method' => 'onPostRead']);

    $services
        ->set('silverback.doctrine.repository.component_position')
        ->class(ComponentPositionRepository::class)
        ->args(
            [
                new Reference(ManagerRegistry::class),
            ]
        )
        ->tag('doctrine.repository_service');

    $services
        ->set('silverback.event_listener.api.orphaned_component')
        ->class(OrphanedComponentEventListener::class)
        ->args(
            [
                new Reference('silverback.metadata_factory.page_data'),
                new Reference('silverback.metadata_factory.component_usage'),
                new Reference(ManagerRegistry::class),
            ]
        )
        ->tag('kernel.event_listener', ['event' => ViewEvent::class, 'priority' => EventPriorities::PRE_WRITE, 'method' => 'onPreWrite']);

    $services
        ->set('silverback.event_listener.api.position_remove')
        ->class(ComponentPositionEventListener::class)
        ->args(
            [
                new Reference(ManagerRegistry::class),
            ]
        )
        ->tag('kernel.event_listener', ['event' => ViewEvent::class, 'priority' => EventPriorities::PRE_WRITE, 'method' => 'onPreWrite'])
        ->tag('kernel.event_listener', ['event' => ResponseEvent::class, 'priority' => EventPriorities::POST_RESPOND, 'method' => 'onPostRespond']);

    $services
        ->set('silverback.metadata_provider.page_data')
        ->class(PageDataMetadataProvider::class)
        ->args([
            new Reference('api_platform.metadata.resource.name_collection_factory'),
            new Reference('silverback.metadata_factory.page_data'),
        ]);

    $services
        ->set(PageDataMetadataStateProvider::class)
        ->args(
            [
                new Reference('silverback.metadata_factory.page_data'),
                new Reference('silverback.metadata_provider.page_data'),
            ]
        )
        ->autoconfigure(false)
        ->tag('api_platform.state_provider');

    $services
        ->set('silverback.security.voter.component_voter')
        ->class(ComponentVoter::class)
        ->args([
            new Reference(PageDataProvider::class),
            new Reference('api_platform.iri_converter'),
            new Reference('http_kernel'),
            new Reference('request_stack'),
            new Reference(PublishableStatusChecker::class),
            new Reference('doctrine'),
        ])
        ->tag('security.voter');

    $services
        ->set('silverback.hydra.normalizer.versioned_documentation')
        ->class(VersionedDocumentationNormalizer::class)
        ->decorate('api_platform.hydra.normalizer.documentation')
        ->args([new Reference('silverback.hydra.normalizer.versioned_documentation.inner')]);

    $services
        ->set(UuidUriVariableTransformer::class)
        ->decorate('api_platform.ramsey_uuid.uri_variables.transformer.uuid')
        ->args(
            [
                new Reference(UuidUriVariableTransformer::class . '.inner'),
            ]
        );

    $services
        ->set(IriConverter::class)
        ->decorate('api_platform.iri_converter')
        ->args([
            new Reference(IriConverter::class . '.inner'),
        ]);
    $services->alias('silverback.iri_converter', IriConverter::class);

    $services
        ->set(MercureIriConverter::class)
        ->args([
            new Reference('api_platform.iri_converter'),
            new Reference(PublishableStatusChecker::class),
        ]);

    $services
        ->set('silverback.doctrine.orm.or_search_filter')
        ->class(OrSearchFilter::class)
        ->private()
        ->abstract()
        ->args([
            new Reference('doctrine'),
            new Reference('api_platform.iri_converter'),
            new Reference('api_platform.property_accessor'),
            new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
        ])
        ->arg('$nameConverter', new Reference('api_platform.name_converter', ContainerInterface::IGNORE_ON_INVALID_REFERENCE));
    $services->alias(OrSearchFilter::class, 'silverback.doctrine.orm.or_search_filter');

    $services
        ->set(SqlLiteForeignKeyEnabler::class)
        ->tag('doctrine.event_listener', ['event' => DoctrineEvents::preFlush]);

    $services
        ->set('silverback.api_components.event_listener.doctrine.propagate_updates_listener')
        ->class(PropagateUpdatesListener::class)
        ->args([
            new Reference('api_platform.iri_converter'),
            new Reference(ManagerRegistry::class),
            new TaggedIteratorArgument('silverback_api_components.resource_changed_propagator'),
            new Reference('api_platform.resource_class_resolver'),
            new Reference(PageDataProvider::class),
            new Reference('silverback.doctrine.repository.component_position'),
        ])
        ->tag('doctrine.event_listener', ['event' => DoctrineEvents::onFlush])
        ->tag('doctrine.event_listener', ['event' => DoctrineEvents::postFlush]);
    $services->alias(PropagateUpdatesListener::class, 'silverback.api_components.event_listener.doctrine.propagate_updates_listener');
};
