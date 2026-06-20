<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Resources\config;

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\State\SerializerContextBuilderInterface;
use ApiPlatform\Symfony\EventListener\EventPriorities;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events as DoctrineEvents;
use Doctrine\Persistence\ManagerRegistry;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Silverback\ApiComponentsBundle\Action\Uploadable\DownloadAction;
use Silverback\ApiComponentsBundle\Action\Uploadable\UploadAction;
use Silverback\ApiComponentsBundle\Action\User\EmailAddressConfirmAction;
use Silverback\ApiComponentsBundle\Action\User\PasswordRequestAction;
use Silverback\ApiComponentsBundle\Action\User\ResendVerifyEmailAddressAction;
use Silverback\ApiComponentsBundle\Action\User\ResendVerifyNewEmailAddressAction;
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
use Silverback\ApiComponentsBundle\Command\CleanOrphanedCommand;
use Silverback\ApiComponentsBundle\Command\FormCachePurgeCommand;
use Silverback\ApiComponentsBundle\Command\RefreshTokensExpireCommand;
use Silverback\ApiComponentsBundle\Command\UserCreateCommand;
use Silverback\ApiComponentsBundle\DataCollector\CwaCollectorData;
use Silverback\ApiComponentsBundle\DataCollector\CwaDataCollector;
use Silverback\ApiComponentsBundle\DataProvider\PageDataProvider;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\ComponentGroupStateProvider;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\FormStateProvider;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\PageDataMetadataStateProvider;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\ResourceManifestStateProvider;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\RouteChildrenStateProvider;
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
use Silverback\ApiComponentsBundle\EventListener\Api\DeletedResourceEventListener;
use Silverback\ApiComponentsBundle\EventListener\Api\FormApiEventListener;
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
use Silverback\ApiComponentsBundle\Factory\Uploadable\ApiUrlGenerator;
use Silverback\ApiComponentsBundle\Factory\Uploadable\MediaObjectFactory;
use Silverback\ApiComponentsBundle\Factory\Uploadable\PublicUrlGenerator;
use Silverback\ApiComponentsBundle\Factory\Uploadable\TemporaryUrlGenerator;
use Silverback\ApiComponentsBundle\Factory\Uploadable\UploadableUrlGeneratorInterface;
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
use Silverback\ApiComponentsBundle\Fixture\CwaFixtureBuilder;
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
use Silverback\ApiComponentsBundle\Helper\OrphanedResourceHelper;
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
use Silverback\ApiComponentsBundle\Repository\Core\ComponentGroupRepository;
use Silverback\ApiComponentsBundle\Repository\Core\ComponentPositionRepository;
use Silverback\ApiComponentsBundle\Repository\Core\FileInfoRepository;
use Silverback\ApiComponentsBundle\Repository\Core\LayoutRepository;
use Silverback\ApiComponentsBundle\Repository\Core\RouteRepository;
use Silverback\ApiComponentsBundle\Repository\Core\SiteConfigParameterRepository;
use Silverback\ApiComponentsBundle\Repository\User\UserRepository;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;
use Silverback\ApiComponentsBundle\Security\EventListener\AccessDeniedListener;
use Silverback\ApiComponentsBundle\Security\EventListener\DenyAccessListener;
use Silverback\ApiComponentsBundle\Security\EventListener\LogoutListener;
use Silverback\ApiComponentsBundle\Security\JWTManager;
use Silverback\ApiComponentsBundle\Security\UserChecker;
use Silverback\ApiComponentsBundle\Security\Voter\ComponentVoter;
use Silverback\ApiComponentsBundle\Security\Voter\ResourceManifestVoter;
use Silverback\ApiComponentsBundle\Security\Voter\RoutableVoter;
use Silverback\ApiComponentsBundle\Security\Voter\RouteVoter;
use Silverback\ApiComponentsBundle\Security\Voter\SiteConfigParameterVoter;
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
use Symfony\Bundle\SecurityBundle\Security;
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
        ->set('silverback.api_components.utility.api_resource_route_finder')
        ->class(ApiResourceRouteFinder::class)
        ->args(
            [
                new Reference('api_platform.router'),
            ]
        );
    $services->alias(ApiResourceRouteFinder::class, 'silverback.api_components.utility.api_resource_route_finder');

    $services
        ->set('silverback.api_components.factory.user.mailer.change_email_confirmation_email')
        ->class(ChangeEmailConfirmationEmailFactory::class)
        ->parent(AbstractUserEmailFactory::class);
    $services->alias(ChangeEmailConfirmationEmailFactory::class, 'silverback.api_components.factory.user.mailer.change_email_confirmation_email');

    $services
        ->set('silverback.api_components.form.change_password_type')
        ->class(ChangePasswordType::class)
        ->args(
            [
                new Reference(Security::class),
                new Reference('silverback.repository.user'),
                '', // injected in dependency injection
            ]
        )
        ->tag('form.type');
    $services->alias(ChangePasswordType::class, 'silverback.api_components.form.change_password_type');

    $services
        ->set('silverback.api_components.serializer.context_builder.cwa_resource')
        ->class(CwaResourceContextBuilder::class)
        ->decorate('api_platform.serializer.context_builder')
        ->args(
            [
                new Reference('silverback.api_components.serializer.context_builder.cwa_resource.inner'),
                new Reference(RoleHierarchyInterface::class),
                new Reference(Security::class),
            ]
        )
        ->autoconfigure(false);
    $services->alias(CwaResourceContextBuilder::class, 'silverback.api_components.serializer.context_builder.cwa_resource');

    $services
        ->set('silverback.api_components.serializer.context_builder.component_position')
        ->class(ComponentPositionContextBuilder::class)
        ->decorate('api_platform.serializer.context_builder')
        ->args(
            [
                new Reference('silverback.api_components.serializer.context_builder.component_position.inner'),
                new Reference(RoleHierarchyInterface::class),
                new Reference(Security::class),
            ]
        )
        ->autoconfigure(false);
    $services->alias(ComponentPositionContextBuilder::class, 'silverback.api_components.serializer.context_builder.component_position');

    $services
        ->set('silverback.api_components.serializer.mapping_loader.cwa_resource')
        ->class(CwaResourceLoader::class);
    $services->alias(CwaResourceLoader::class, 'silverback.api_components.serializer.mapping_loader.cwa_resource');

    $services
        ->set('silverback.api_components.event_listener.form.change_password')
        ->class(ChangePasswordListener::class)
        ->parent(EntityPersistFormListener::class)
        ->tag('kernel.event_listener', ['event' => FormSuccessEvent::class]);
    $services->alias(ChangePasswordListener::class, 'silverback.api_components.event_listener.form.change_password');

    $services
        ->set('silverback.api_components.event_listener.api.collection')
        ->class(CollectionApiEventListener::class)
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
                new Reference('api_platform.state_provider.parameter'),
            ]
        )
        ->tag('kernel.event_listener', ['event' => ViewEvent::class, 'priority' => EventPriorities::PRE_SERIALIZE, 'method' => 'onPreSerialize']);
    $services->alias(CollectionApiEventListener::class, 'silverback.api_components.event_listener.api.collection');

    $services
        ->set('silverback.helper.component_position_sort_value')
        ->class(ComponentPositionSortValueHelper::class);
    $services->alias(ComponentPositionSortValueHelper::class, 'silverback.helper.component_position_sort_value');

    $services
        ->set('silverback.api_components.validator.component_position')
        ->class(ComponentPositionValidator::class)
        ->args([
            new Reference(IriConverterInterface::class),
            new Reference('silverback.metadata_provider.page_data'),
        ])
        ->tag('validator.constraint_validator');
    $services->alias(ComponentPositionValidator::class, 'silverback.api_components.validator.component_position');

    $services
        ->set('silverback.api_components.api_platform.property_metadata_factory.component')
        ->class(ComponentPropertyMetadataFactory::class)
        ->decorate('api_platform.metadata.property.metadata_factory')
        ->args(
            [
                new Reference('silverback.api_components.api_platform.property_metadata_factory.component.inner'),
            ]
        );
    $services->alias(ComponentPropertyMetadataFactory::class, 'silverback.api_components.api_platform.property_metadata_factory.component');

    $services
        ->set('silverback.api_components.event_listener.security.deny_access')
        ->class(DenyAccessListener::class)
        ->args(
            [
                new Reference(Security::class),
                new Reference('silverback.doctrine.repository.route'),
            ]
        )
        ->tag('kernel.event_listener', ['event' => RequestEvent::class, 'priority' => EventPriorities::PRE_DESERIALIZE, 'method' => 'onPreDeserialize']);
    $services->alias(DenyAccessListener::class, 'silverback.api_components.event_listener.security.deny_access');

    $services
        ->set(DownloadAction::class)
        ->public()
        ->tag('controller.service_arguments');
    $services->alias('silverback.api_components.action.uploadable.download', DownloadAction::class)->public();

    $services
        ->set('silverback.api_components.helper.user.email_address_manager')
        ->class(EmailAddressManager::class)
        ->args(
            [
                new Reference(EntityManagerInterface::class),
                new Reference('silverback.repository.user'),
                new Reference(PasswordHasherFactoryInterface::class),
                new Reference(UserDataProcessor::class),
                new Reference(UserEventListener::class),
            ]
        );
    $services->alias(EmailAddressManager::class, 'silverback.api_components.helper.user.email_address_manager');

    $services
        ->set(EmailAddressConfirmAction::class)
        ->public()
        ->args(
            [
                new Reference(EmailAddressManager::class),
            ]
        )
        ->tag('controller.service_arguments');
    $services->alias('silverback.api_components.action.user.email_address_confirm', EmailAddressConfirmAction::class)->public();

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
        ->set('silverback.api_components.helper.uploadable.file_info_cache_manager')
        ->class(FileInfoCacheManager::class)
        ->args(
            [
                new Reference(EntityManagerInterface::class),
                new Reference(FileInfoRepository::class),
            ]
        );
    $services->alias(FileInfoCacheManager::class, 'silverback.api_components.helper.uploadable.file_info_cache_manager');

    $services
        ->set('silverback.api_components.repository.file_info')
        ->class(FileInfoRepository::class)
        ->args(
            [
                new Reference(ManagerRegistry::class),
            ]
        )
        ->tag('doctrine.repository_service');
    $services->alias(FileInfoRepository::class, 'silverback.api_components.repository.file_info');

    $services
        ->set('silverback.api_components.flysystem.filesystem_factory')
        ->class(FilesystemFactory::class)
        ->args([tagged_locator(FilesystemProvider::FILESYSTEM_ADAPTER_TAG, 'alias')]);
    $services->alias(FilesystemFactory::class, 'silverback.api_components.flysystem.filesystem_factory');

    $services
        ->set('silverback.api_components.flysystem.filesystem_provider')
        ->class(FilesystemProvider::class)
        ->args([tagged_locator(FilesystemProvider::FILESYSTEM_TAG, 'alias')]);
    $services->alias(FilesystemProvider::class, 'silverback.api_components.flysystem.filesystem_provider');

    $services
        ->set('silverback.api_components.imagine.flysystem_data_loader')
        ->class(FlysystemDataLoader::class)
        ->args(
            [
                new Reference(FilesystemProvider::class),
            ]
        )
        ->tag('liip_imagine.binary.loader', ['loader' => 'silverback.api_components.liip_imagine.binary.loader']);
    $services->alias(FlysystemDataLoader::class, 'silverback.api_components.imagine.flysystem_data_loader');

    $services
        ->set('silverback.api_components.command.form_cache_purge')
        ->class(FormCachePurgeCommand::class)
        ->tag('console.command')
        ->args(
            [
                new Reference(FormCachePurger::class),
                new Reference(EventDispatcherInterface::class),
            ]
        );
    $services->alias(FormCachePurgeCommand::class, 'silverback.api_components.command.form_cache_purge');

    $services
        ->set('silverback.api_components.helper.form.form_cache_purger')
        ->class(FormCachePurger::class)
        ->args(
            [
                new Reference(EntityManagerInterface::class),
                new Reference(EventDispatcherInterface::class),
            ]
        );
    $services->alias(FormCachePurger::class, 'silverback.api_components.helper.form.form_cache_purger');

    $services
        ->set('silverback.api_components.event_listener.api.form')
        ->class(FormApiEventListener::class)
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
    $services->alias(FormApiEventListener::class, 'silverback.api_components.event_listener.api.form');

    $services
        ->set('silverback.api_components.helper.form.form_submit')
        ->class(FormSubmitHelper::class)
        ->args(
            [
                new Reference(FormFactoryInterface::class),
                new Reference(EventDispatcherInterface::class),
            ]
        );
    $services->alias(FormSubmitHelper::class, 'silverback.api_components.helper.form.form_submit');

    $services
        ->set('silverback.api_components.validator.form_type_class')
        ->class(FormTypeClassValidator::class)
        ->tag('validator.constraint_validator')
        ->args(
            [
                '$formTypes' => new TaggedIteratorArgument('silverback_api_components.form_type'),
            ]
        );
    $services->alias(FormTypeClassValidator::class, 'silverback.api_components.validator.form_type_class');

    $services
        ->set('silverback.api_components.factory.form.form_view')
        ->class(FormViewFactory::class)
        ->args(
            [
                new Reference(FormFactoryInterface::class),
                new Reference(IriConverterInterface::class),
                new Reference(UrlHelper::class),
            ]
        );
    $services->alias(FormViewFactory::class, 'silverback.api_components.factory.form.form_view');

    $services
        ->set('silverback.api_components.event_listener.imagine')
        ->class(ImagineEventListener::class)
        ->args(
            [
                new Reference(FileInfoCacheManager::class),
            ]
        )
        ->tag('kernel.event_listener', ['event' => ImagineStoreEvent::class, 'method' => 'onStore'])
        ->tag('kernel.event_listener', ['event' => ImagineRemoveEvent::class, 'method' => 'onRemove']);
    $services->alias(ImagineEventListener::class, 'silverback.api_components.event_listener.imagine');

    $services
        ->set('silverback.api_components.api_platform.property_metadata_factory.imagine_filters')
        ->class(ImagineFiltersPropertyMetadataFactory::class)
        ->decorate('api_platform.metadata.property.metadata_factory')
        ->args(
            [
                new Reference('silverback.api_components.api_platform.property_metadata_factory.imagine_filters.inner'),
            ]
        );
    $services->alias(ImagineFiltersPropertyMetadataFactory::class, 'silverback.api_components.api_platform.property_metadata_factory.imagine_filters');

    $services
        ->set('silverback.api_components.repository.layout')
        ->class(LayoutRepository::class)
        ->args(
            [
                new Reference(ManagerRegistry::class),
            ]
        )
        ->tag('doctrine.repository_service');
    $services->alias(LayoutRepository::class, 'silverback.api_components.repository.layout');

    $services
        ->set('silverback.api_components.factory.uploadable.media_object')
        ->class(MediaObjectFactory::class)
        ->args(
            [
                new Reference(ManagerRegistry::class),
                new Reference(FileInfoCacheManager::class),
                new Reference(UploadableAttributeReader::class),
                new Reference(FilesystemProvider::class),
                new Reference(FlysystemDataLoader::class),
                new Reference(RequestStack::class),
                new Reference(FilesystemFactory::class),
                new Reference(UrlHelper::class),
                tagged_locator(UploadableUrlGeneratorInterface::TAG, 'alias'),
                null, // populated in dependency injection
            ]
        );
    $services->alias(MediaObjectFactory::class, 'silverback.api_components.factory.uploadable.media_object');

    $services
        ->set('silverback.api_components.event_listener.mailer.message')
        ->class(MessageEventListener::class)
        ->tag('kernel.event_listener', ['event' => MessageEvent::class])
        ->args(
            [
                '%env(MAILER_EMAIL)%',
            ]
        );
    $services->alias(MessageEventListener::class, 'silverback.api_components.event_listener.mailer.message');

    $services
        ->set('silverback.api_components.event_listener.form.new_email_address')
        ->class(NewEmailAddressListener::class)
        ->parent(EntityPersistFormListener::class)
        ->tag('kernel.event_listener', ['event' => FormSuccessEvent::class]);
    $services->alias(NewEmailAddressListener::class, 'silverback.api_components.event_listener.form.new_email_address');

    $services
        ->set('silverback.api_components.form.new_email_address_type')
        ->class(NewEmailAddressType::class)
        ->args(
            [
                new Reference(Security::class),
                new Reference('silverback.repository.user'),
                '', // injected in dependency injection
            ]
        )
        ->tag('form.type');
    $services->alias(NewEmailAddressType::class, 'silverback.api_components.form.new_email_address_type');

    $services
        ->set('silverback.api_components.validator.new_email_address')
        ->class(NewEmailAddressValidator::class)
        ->args(
            [
                new Reference('silverback.repository.user'),
            ]
        )
        ->tag('validator.constraint_validator');
    $services->alias(NewEmailAddressValidator::class, 'silverback.api_components.validator.new_email_address');

    $services
        ->set('silverback.api_components.data_provider.page_data')
        ->class(PageDataProvider::class)
        ->args([
            new Reference(RequestStack::class),
            new Reference('silverback.doctrine.repository.route'),
            new Reference('api_platform.iri_converter'),
            new Reference('silverback.metadata.api.component_resource_metadata_factory'),
            new Reference('silverback.metadata_provider.page_data'),
            new Reference(ManagerRegistry::class),
        ]);
    $services->alias(PageDataProvider::class, 'silverback.api_components.data_provider.page_data');

    $services
        ->set('silverback.api_components.factory.user.mailer.password_changed_email')
        ->class(PasswordChangedEmailFactory::class)
        ->parent(AbstractUserEmailFactory::class);
    $services->alias(PasswordChangedEmailFactory::class, 'silverback.api_components.factory.user.mailer.password_changed_email');

    $services
        ->set('silverback.api_components.factory.user.mailer.password_reset_email')
        ->class(PasswordResetEmailFactory::class)
        ->parent(AbstractUserEmailFactory::class);
    $services->alias(PasswordResetEmailFactory::class, 'silverback.api_components.factory.user.mailer.password_reset_email');

    $services
        ->set(PasswordRequestAction::class)
        ->public()
        ->args(
            [
                new Reference(UserDataProcessor::class),
                new Reference(EntityManagerInterface::class),
                new Reference(UserMailer::class),
            ]
        )
        ->tag('controller.service_arguments');
    $services->alias('silverback.api_components.action.user.password_request', PasswordRequestAction::class)->public();

    $services
        ->set('silverback.api_components.form.password_update_type')
        ->class(PasswordUpdateType::class)
        ->args(
            [
                new Reference('request_stack'),
                new Reference('silverback.repository.user'),
                '', // injected in dependency injection
            ]
        )
        ->tag('form.type');
    $services->alias(PasswordUpdateType::class, 'silverback.api_components.form.password_update_type');

    $services
        ->set('silverback.api_components.event_listener.form.password_update')
        ->class(PasswordUpdateListener::class)
        ->parent(EntityPersistFormListener::class)
        ->args(
            [
                new Reference(UserDataProcessor::class),
            ]
        )
        ->tag('kernel.event_listener', ['event' => FormSuccessEvent::class]);
    $services->alias(PasswordUpdateListener::class, 'silverback.api_components.event_listener.form.password_update');

    $services
        ->set('silverback.api_components.attribute_reader.publishable')
        ->class(PublishableAttributeReader::class)
        ->parent(AttributeReader::class);
    $services->alias(PublishableAttributeReader::class, 'silverback.api_components.attribute_reader.publishable');

    $services
        ->set('silverback.api_components.serializer.context_builder.publishable')
        ->class(PublishableContextBuilder::class)
        ->decorate('api_platform.serializer.context_builder')
        ->args(
            [
                new Reference('silverback.api_components.serializer.context_builder.publishable.inner'),
                new Reference(PublishableStatusChecker::class),
            ]
        )
        ->autoconfigure(false);
    $services->alias(PublishableContextBuilder::class, 'silverback.api_components.serializer.context_builder.publishable');

    $services
        ->set('silverback.api_components.event_listener.api.publishable')
        ->class(PublishableEventListener::class)
        ->args(
            [
                new Reference(PublishableStatusChecker::class),
                new Reference('doctrine'),
                new Reference('api_platform.validator'),
                new Reference(UploadableFileManager::class),
            ]
        )
        ->tag('kernel.event_listener', ['event' => RequestEvent::class, 'priority' => EventPriorities::POST_READ, 'method' => 'onPostRead'])
        ->tag('kernel.event_listener', ['event' => ViewEvent::class, 'priority' => EventPriorities::PRE_WRITE, 'method' => 'onPreWrite'])
        ->tag('kernel.event_listener', ['event' => RequestEvent::class, 'priority' => EventPriorities::POST_DESERIALIZE, 'method' => 'onPostDeserialize'])
        ->tag('kernel.event_listener', ['event' => ResponseEvent::class, 'priority' => EventPriorities::POST_RESPOND, 'method' => 'onPostRespond']);
    $services->alias(PublishableEventListener::class, 'silverback.api_components.event_listener.api.publishable');

    $services
        ->set('silverback.api_components.helper.publishable.status_checker')
        ->class(PublishableStatusChecker::class)
        ->args(
            [
                new Reference(ManagerRegistry::class),
                new Reference(PublishableAttributeReader::class),
                new Reference(AuthorizationCheckerInterface::class),
                '', // injected with dependency injection
            ]
        );
    $services->alias(PublishableStatusChecker::class, 'silverback.api_components.helper.publishable.status_checker');

    $services
        ->set('silverback.api_components.doctrine.event_listener.publishable')
        ->class(PublishableListener::class)
        ->args([new Reference(PublishableAttributeReader::class)])
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata']);
    $services->alias(PublishableListener::class, 'silverback.api_components.doctrine.event_listener.publishable');

    $services
        ->set('silverback.api_components.mercure.authorization')
        ->class(MercureAuthorization::class)
        ->args(
            [
                new Reference(ResourceNameCollectionFactoryInterface::class),
                new Reference(ResourceMetadataCollectionFactoryInterface::class),
                new Reference(PublishableStatusChecker::class),
                new Reference('router.request_context'),
                new Reference(Authorization::class),
                new Reference('request_stack'),
                new Reference(AuthorizationCheckerInterface::class),
                '', // $cookieSameSite — injected via DI
                null, // $hubName — injected via DI
                false, // $secureSubscriptions — injected via DI
            ]
        );
    $services->alias(MercureAuthorization::class, 'silverback.api_components.mercure.authorization');

    $services
        ->set('silverback.api_components.mercure.publishable_aware_hub')
        ->class(PublishableAwareHub::class)
        ->decorate('mercure.hub.default', null, -1)
        ->args(
            [
                new Reference('silverback.api_components.mercure.publishable_aware_hub.inner'),
                new Reference(PublishableStatusChecker::class),
                new Reference(IriConverterInterface::class),
            ]
        )
        ->tag('mercure.hub');
    $services->alias(PublishableAwareHub::class, 'silverback.api_components.mercure.publishable_aware_hub');

    // High priority for item because of queryBuilder reset
    $services
        ->set('silverback.api_components.doctrine.orm.extension.publishable')
        ->class(PublishableExtension::class)
        ->args(
            [
                new Reference(PublishableStatusChecker::class),
                new Reference('request_stack'),
                new Reference('doctrine'),
            ]
        )
        ->tag('api_platform.doctrine.orm.query_extension.item', ['priority' => 100])
        ->tag('api_platform.doctrine.orm.query_extension.collection');
    $services->alias(PublishableExtension::class, 'silverback.api_components.doctrine.orm.extension.publishable');

    $services
        ->set('silverback.api_components.validator.publishable')
        ->class(PublishableValidator::class)
        ->decorate('api_platform.validator')
        ->args(
            [
                new Reference('silverback.api_components.validator.publishable.inner'),
                new Reference(PublishableStatusChecker::class),
            ]
        );
    $services->alias(PublishableValidator::class, 'silverback.api_components.validator.publishable');

    $services
        ->set('silverback.api_components.serializer.mapping_loader.publishable')
        ->class(PublishableLoader::class);
    $services->alias(PublishableLoader::class, 'silverback.api_components.serializer.mapping_loader.publishable');

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
                new Reference(CwaCollectorData::class),
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
            new Reference(CwaCollectorData::class),
        ])
        ->tag('kernel.event_listener', ['event' => Events::JWT_INVALID, 'method' => 'onJwtInvalid'])
        ->tag('kernel.event_listener', ['event' => Events::JWT_EXPIRED, 'method' => 'onJwtExpired'])
        ->tag('kernel.event_listener', ['event' => KernelEvents::RESPONSE, 'method' => 'onKernelResponse']);
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
        ->set('silverback.security.access_denied_listener')
        ->class(AccessDeniedListener::class)
        ->args(
            [
                new Reference(MercureAuthorization::class),
            ]
        )
        ->tag('kernel.event_listener', ['event' => ResponseEvent::class, 'priority' => EventPriorities::POST_RESPOND, 'method' => 'onPostRespond']);

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
        ->set('silverback.api_components.validator.resource_iri')
        ->class(ResourceIriValidator::class)
        ->args(
            [
                new Reference(ApiResourceRouteFinder::class),
            ]
        )
        ->tag('validator.constraint_validator');
    $services->alias(ResourceIriValidator::class, 'silverback.api_components.validator.resource_iri');

    $services
        ->set('silverback.api_components.helper.referer_url_resolver')
        ->class(RefererUrlResolver::class)
        ->args(
            [
                new Reference(RequestStack::class),
            ]
        );
    $services->alias(RefererUrlResolver::class, 'silverback.api_components.helper.referer_url_resolver');

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
        ->set('silverback.api_components.event_listener.resource_changed')
        ->class(ResourceChangedEventListener::class)
        ->tag('kernel.event_listener', ['event' => ResourceChangedEvent::class])
        ->args(
            [
                '$resourceChangedPropagators' => new TaggedIteratorArgument('silverback_api_components.resource_changed_propagator'),
            ]
        );
    $services->alias(ResourceChangedEventListener::class, 'silverback.api_components.event_listener.resource_changed');

    $services
        ->set('silverback.api_components.serializer.resource_metadata_provider')
        ->class(ResourceMetadataProvider::class);
    $services->alias(ResourceMetadataProvider::class, 'silverback.api_components.serializer.resource_metadata_provider');

    $services
        ->set(RouteStateProvider::class)
        ->args(
            [
                new Reference('silverback.doctrine.repository.route'),
                new Reference('api_platform.state_provider'),
                new Reference(CwaCollectorData::class),
            ]
        )
        ->autoconfigure(false)
        ->tag('api_platform.state_provider');
    $services->alias('silverback.api_components.api_platform.state_provider.route', RouteStateProvider::class);

    $services
        ->set(RouteChildrenStateProvider::class)
        ->args(
            [
                new Reference('silverback.doctrine.repository.route'),
                new Reference(ManagerRegistry::class),
                new Reference(IriConverterInterface::class),
            ]
        )
        ->autoconfigure(false)
        ->tag('api_platform.state_provider');
    $services->alias('silverback.api_components.api_platform.state_provider.route_children', RouteChildrenStateProvider::class);

    $services
        ->set(ComponentGroupStateProvider::class)
        ->args(
            [
                new Reference('silverback.doctrine.repository.component_group'),
                new Reference('api_platform.state_provider'),
            ]
        )
        ->autoconfigure(false)
        ->tag('api_platform.state_provider');
    $services->alias('silverback.api_components.api_platform.state_provider.component_group', ComponentGroupStateProvider::class);

    $services
        ->set(FormStateProvider::class)
        ->args(
            [
                new Reference('api_platform.state_provider'),
            ]
        )
        ->autoconfigure(false)
        ->tag('api_platform.state_provider');
    $services->alias('silverback.api_components.api_platform.state_provider.form', FormStateProvider::class);

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
        ->set('silverback.api_components.doctrine.orm.extension.route')
        ->class(RouteExtension::class)
        ->args(
            [
                '', // added in dependency injection
                new Reference('api_platform.security.resource_access_checker'),
            ]
        )
        ->tag('api_platform.doctrine.orm.query_extension.collection');
    $services->alias(RouteExtension::class, 'silverback.api_components.doctrine.orm.extension.route');

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
        ->set(SiteConfigParameterRepository::class)
        ->args(
            [
                new Reference(ManagerRegistry::class),
            ]
        )
        ->tag('doctrine.repository_service');
    $services->alias('silverback.doctrine.repository.site_config_parameter', SiteConfigParameterRepository::class);

    $services
        ->set('silverback.api_components.security.voter.route')
        ->class(RouteVoter::class)
        ->args([
            '', // added in dependency injection
            new Reference('api_platform.security.resource_access_checker'),
        ])
        ->tag('security.voter');
    $services->alias(RouteVoter::class, 'silverback.api_components.security.voter.route');

    $services
        ->set('silverback.api_components.security.voter.site_config_parameter')
        ->class(SiteConfigParameterVoter::class)
        ->args([
            '', // added in dependency injection
            new Reference(AuthorizationCheckerInterface::class),
        ])
        ->tag('security.voter');
    $services->alias(SiteConfigParameterVoter::class, 'silverback.api_components.security.voter.site_config_parameter');

    $services
        ->set('silverback.api_components.doctrine.orm.extension.routable')
        ->class(RoutableExtension::class)
        ->args(
            [
                '', // added in dependency injection
                new Reference('api_platform.security.resource_access_checker'),
            ]
        )
        ->tag('api_platform.doctrine.orm.query_extension.collection');
    $services->alias(RoutableExtension::class, 'silverback.api_components.doctrine.orm.extension.routable');

    $services
        ->set('silverback.api_components.security.voter.routable')
        ->class(RoutableVoter::class)
        ->args([
            '', // added in dependency injection
            new Reference('api_platform.security.resource_access_checker'),
            new Reference(Security::class),
            new Reference('silverback.doctrine.repository.page_data'),
            new Reference(DenyAccessListener::class),
        ])
        ->tag('security.voter');
    $services->alias(RoutableVoter::class, 'silverback.api_components.security.voter.routable');

    $services
        ->set('silverback.api_components.security.voter.resource_manifest')
        ->class(ResourceManifestVoter::class)
        ->args([new Reference(Security::class)])
        ->tag('security.voter');
    $services->alias(ResourceManifestVoter::class, 'silverback.api_components.security.voter.resource_manifest');

    $services
        ->set(ResourceManifestStateProvider::class)
        ->args([
            new Reference('silverback.doctrine.repository.route'),
            new Reference(EntityManagerInterface::class),
        ])
        ->autoconfigure(false)
        ->tag('api_platform.state_provider');
    $services->alias('silverback.api_components.api_platform.state_provider.resource_manifest', ResourceManifestStateProvider::class);

    $services
        ->set('silverback.api_components.api_platform.metadata.resource.routing_prefix_factory')
        ->class(RoutingPrefixResourceMetadataCollectionFactory::class)
        ->decorate('api_platform.metadata.resource.metadata_collection_factory')
        ->args(
            [
                new Reference('silverback.api_components.api_platform.metadata.resource.routing_prefix_factory.inner'),
            ]
        );
    $services->alias(RoutingPrefixResourceMetadataCollectionFactory::class, 'silverback.api_components.api_platform.metadata.resource.routing_prefix_factory');

    $services
        ->set('silverback.api_components.api_platform.metadata.resource.routable_factory')
        ->class(RoutableResourceMetadataCollectionFactory::class)
        ->decorate('api_platform.metadata.resource.metadata_collection_factory')
        ->args(
            [
                new Reference('silverback.api_components.api_platform.metadata.resource.routable_factory.inner'),
            ]
        );
    $services->alias(RoutableResourceMetadataCollectionFactory::class, 'silverback.api_components.api_platform.metadata.resource.routable_factory');

    $services
        ->set('silverback.api_components.serializer.format_resolver')
        ->class(SerializeFormatResolver::class)
        ->args(
            [
                new Reference(RequestStack::class),
                'jsonld',
            ]
        );
    $services->alias(SerializeFormatResolver::class, 'silverback.api_components.serializer.format_resolver');

    $services
        ->set('silverback.api_components.doctrine.event_listener.table_prefix')
        ->class(TablePrefixExtension::class)
        ->args(
            [
                '', // injected in dependency injection
            ]
        )
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata']);
    $services->alias(TablePrefixExtension::class, 'silverback.api_components.doctrine.event_listener.table_prefix');

    $services
        ->set('silverback.api_components.attribute_reader.timestamped')
        ->class(TimestampedAttributeReader::class)
        ->parent(AttributeReader::class);
    $services->alias(TimestampedAttributeReader::class, 'silverback.api_components.attribute_reader.timestamped');

    $services
        ->set('silverback.api_components.serializer.context_builder.timestamped')
        ->class(TimestampedContextBuilder::class)
        ->decorate('api_platform.serializer.context_builder', null, -1)
        ->args(
            [
                new Reference('silverback.api_components.serializer.context_builder.timestamped.inner'),
            ]
        )
        ->autoconfigure(false);
    $services->alias(TimestampedContextBuilder::class, 'silverback.api_components.serializer.context_builder.timestamped');

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
        ->set('silverback.api_components.doctrine.event_listener.timestamped')
        ->class(TimestampedListener::class)
        ->args(
            [
                new Reference(TimestampedAttributeReader::class),
            ]
        )
        ->tag('doctrine.event_listener', $getTimestampedListenerTagArgs('loadClassMetadata'));
    $services->alias(TimestampedListener::class, 'silverback.api_components.doctrine.event_listener.timestamped');

    $services
        ->set('silverback.api_components.serializer.mapping_loader.timestamped')
        ->class(TimestampedLoader::class);
    $services->alias(TimestampedLoader::class, 'silverback.api_components.serializer.mapping_loader.timestamped');

    $services
        ->set('silverback.api_components.validator.mapping_loader.timestamped')
        ->class(TimestampedValidatorMappingLoader::class)
        ->args(
            [
                new Reference(TimestampedAttributeReader::class),
            ]
        );
    $services->alias(TimestampedValidatorMappingLoader::class, 'silverback.api_components.validator.mapping_loader.timestamped');

    $services
        ->set('silverback.api_components.validator.timestamped')
        ->class(TimestampedValidator::class)
        ->decorate('api_platform.validator')
        ->args(
            [
                new Reference('silverback.api_components.validator.timestamped.inner'),
                new Reference(TimestampedAttributeReader::class),
            ]
        );
    $services->alias(TimestampedValidator::class, 'silverback.api_components.validator.timestamped');

    $services
        ->set(UploadAction::class)
        ->public()
        ->tag('controller.service_arguments')
        ->args([
            '$publishableNormalizer' => new Reference(PublishableNormalizer::class),
        ]);
    $services->alias('silverback.api_components.action.uploadable.upload', UploadAction::class)->public();

    $services
        ->set('silverback.api_components.attribute_reader.uploadable')
        ->class(UploadableAttributeReader::class)
        ->parent(AttributeReader::class);
    $services->alias(UploadableAttributeReader::class, 'silverback.api_components.attribute_reader.uploadable');

    $services
        ->set('silverback.api_components.serializer.context_builder.uploadable')
        ->class(UploadableContextBuilder::class)
        ->decorate('api_platform.serializer.context_builder', null, -1)
        ->args(
            [
                new Reference('silverback.api_components.serializer.context_builder.uploadable.inner'),
            ]
        )
        ->autoconfigure(false);
    $services->alias(UploadableContextBuilder::class, 'silverback.api_components.serializer.context_builder.uploadable');

    $services
        ->set('silverback.api_components.event_listener.api.uploadable')
        ->class(UploadableEventListener::class)
        ->args(
            [
                new Reference(UploadableAttributeReader::class),
                new Reference(UploadableFileManager::class),
            ]
        )
        ->tag('kernel.event_listener', ['event' => ViewEvent::class, 'priority' => EventPriorities::PRE_WRITE, 'method' => 'onPreWrite'])
        ->tag('kernel.event_listener', ['event' => ViewEvent::class, 'priority' => EventPriorities::POST_WRITE, 'method' => 'onPostWrite']);
    $services->alias(UploadableEventListener::class, 'silverback.api_components.event_listener.api.uploadable');

    $services
        ->set('silverback.api_components.helper.uploadable.file_manager')
        ->class(UploadableFileManager::class)
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
    $services->alias(UploadableFileManager::class, 'silverback.api_components.helper.uploadable.file_manager');

    $services
        ->set('silverback.api_components.doctrine.event_listener.uploadable')
        ->class(UploadableListener::class)
        ->args(
            [
                new Reference(UploadableAttributeReader::class),
            ]
        )
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata']);
    $services->alias(UploadableListener::class, 'silverback.api_components.doctrine.event_listener.uploadable');

    $services
        ->set('silverback.api_components.serializer.mapping_loader.uploadable')
        ->class(UploadableLoader::class)
        ->args(
            [
                new Reference(UploadableAttributeReader::class),
            ]
        );
    $services->alias(UploadableLoader::class, 'silverback.api_components.serializer.mapping_loader.uploadable');

    $services
        ->set('silverback.api_components.api_platform.metadata.resource.uploadable_factory')
        ->class(UploadableResourceMetadataCollectionFactory::class)
        ->decorate('api_platform.metadata.resource.metadata_collection_factory')
        ->args(
            [
                new Reference('silverback.api_components.api_platform.metadata.resource.uploadable_factory.inner'),
                new Reference(UploadableAttributeReader::class),
                new Reference('api_platform.path_segment_name_generator'),
            ]
        )
        ->autoconfigure(false);
    $services->alias(UploadableResourceMetadataCollectionFactory::class, 'silverback.api_components.api_platform.metadata.resource.uploadable_factory');

    //    COMPILER PASS REQUIRED AS WELL
    //    $services
    //        ->set(UploadableLoader::class)
    //        ->args([
    //            new Reference(UploadableAnnotationReader::class),
    //        ]);

    $services
        ->set('silverback.api_components.security.user_checker')
        ->class(UserChecker::class)
        ->args(
            [
                '', // injected in dependency injection
            ]
        );
    $services->alias(UserChecker::class, 'silverback.api_components.security.user_checker');

    $services
        ->set('silverback.api_components.serializer.context_builder.user')
        ->class(UserContextBuilder::class)
        ->decorate('api_platform.serializer.context_builder')
        ->args(
            [
                new Reference('silverback.api_components.serializer.context_builder.user.inner'),
                new Reference(AuthorizationCheckerInterface::class),
            ]
        )
        ->autoconfigure(false);
    $services->alias(UserContextBuilder::class, 'silverback.api_components.serializer.context_builder.user');

    $services
        ->set('silverback.api_components.command.user_create')
        ->class(UserCreateCommand::class)
        ->tag('console.command')
        ->args(
            [
                new Reference(UserFactory::class),
            ]
        );
    $services->alias(UserCreateCommand::class, 'silverback.api_components.command.user_create');

    $services
        ->set('silverback.api_components.command.clean_orphaned')
        ->class(CleanOrphanedCommand::class)
        ->tag('console.command')
        ->args(
            [
                new Reference('silverback.helper.orphaned_resource_helper'),
                new Reference(ManagerRegistry::class),
            ]
        );
    $services->alias(CleanOrphanedCommand::class, 'silverback.api_components.command.clean_orphaned');

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
    $services->alias('silverback.api_components.api_platform.state_provider.user', UserStateProvider::class);

    $services
        ->set('silverback.api_components.factory.user.mailer.user_enabled_email')
        ->class(UserEnabledEmailFactory::class)
        ->parent(AbstractUserEmailFactory::class);
    $services->alias(UserEnabledEmailFactory::class, 'silverback.api_components.factory.user.mailer.user_enabled_email');

    $services
        ->set('silverback.api_components.event_listener.api.user')
        ->class(UserEventListener::class)
        ->args(
            [
                new Reference(UserMailer::class),
                new Reference(Security::class),
            ]
        )
        ->tag('kernel.event_listener', ['event' => ViewEvent::class, 'priority' => EventPriorities::POST_WRITE, 'method' => 'onPostWrite'])
        ->tag('kernel.event_listener', ['event' => RequestEvent::class, 'priority' => EventPriorities::PRE_READ, 'method' => 'onPreRead'])
        ->tag('kernel.event_listener', ['event' => RequestEvent::class, 'priority' => EventPriorities::POST_READ, 'method' => 'onPostRead']);
    $services->alias(UserEventListener::class, 'silverback.api_components.event_listener.api.user');

    $services
        ->set('silverback.api_components.factory.user')
        ->class(UserFactory::class)
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
    $services->alias(UserFactory::class, 'silverback.api_components.factory.user');

    $services
        ->set('silverback.api_components.form.user_login_type')
        ->class(UserLoginType::class)
        ->args([new Reference(RouterInterface::class)])
        ->tag('form.type');
    $services->alias(UserLoginType::class, 'silverback.api_components.form.user_login_type');

    $services
        ->set('silverback.api_components.helper.user.mailer')
        ->class(UserMailer::class)
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
                    'doctrine.orm.entity_manager' => new Reference('doctrine.orm.entity_manager'),
                    'logger' => new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
                ]),
                '', // injected in dependency injection
            ]
        );
    $services->alias(UserMailer::class, 'silverback.api_components.helper.user.mailer');

    $services
        ->set('silverback.api_components.factory.user.mailer.username_changed_email')
        ->class(UsernameChangedEmailFactory::class)
        ->parent(AbstractUserEmailFactory::class);
    $services->alias(UsernameChangedEmailFactory::class, 'silverback.api_components.factory.user.mailer.username_changed_email');

    $services
        ->set('silverback.api_components.helper.user.data_processor')
        ->class(UserDataProcessor::class)
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
    $services->alias(UserDataProcessor::class, 'silverback.api_components.helper.user.data_processor');

    $services
        ->set('silverback.api_components.validator.user_password')
        ->class(UserPasswordValidator::class)
        ->args([
            new Reference(TokenStorageInterface::class),
            new Reference(PasswordHasherFactoryInterface::class),
            new Reference('silverback.repository.user'),
        ])
        ->decorate('security.validator.user_password');
    $services->alias(UserPasswordValidator::class, 'silverback.api_components.validator.user_password');

    $services
        ->set('silverback.api_components.event_listener.form.user_register')
        ->class(UserRegisterListener::class)
        ->parent(EntityPersistFormListener::class)
        ->tag('kernel.event_listener', ['event' => FormSuccessEvent::class]);
    $services->alias(UserRegisterListener::class, 'silverback.api_components.event_listener.form.user_register');

    $services
        ->set('silverback.api_components.form.user_register_type')
        ->class(UserRegisterType::class)
        ->args(
            [
                '', // injected in dependency injection
            ]
        )
        ->tag('form.type');
    $services->alias(UserRegisterType::class, 'silverback.api_components.form.user_register_type');

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
        ->set('silverback.api_components.api_platform.metadata.resource.user_factory')
        ->class(UserResourceMetadataCollectionFactory::class)
        ->decorate('api_platform.metadata.resource.metadata_collection_factory')
        ->args(
            [
                new Reference('silverback.api_components.api_platform.metadata.resource.user_factory.inner'),
            ]
        );
    $services->alias(UserResourceMetadataCollectionFactory::class, 'silverback.api_components.api_platform.metadata.resource.user_factory');

    $services
        ->set(VerifyEmailAddressAction::class)
        ->public()
        ->args(
            [
                new Reference(EmailAddressManager::class),
            ]
        )
        ->tag('controller.service_arguments');
    $services->alias('silverback.api_components.action.user.verify_email_address', VerifyEmailAddressAction::class)->public();

    $services
        ->set(ResendVerifyEmailAddressAction::class)
        ->public()
        ->args([
            new Reference(UserMailer::class),
            new Reference(UserDataProcessor::class),
        ])
        ->tag('controller.service_arguments');
    $services->alias('silverback.api_components.action.user.resend_verify_email_address', ResendVerifyEmailAddressAction::class)->public();

    $services
        ->set(ResendVerifyNewEmailAddressAction::class)
        ->public()
        ->args([
            new Reference(UserMailer::class),
            new Reference(UserDataProcessor::class),
        ])
        ->tag('controller.service_arguments');
    $services->alias('silverback.api_components.action.user.resend_verify_new_email_address', ResendVerifyNewEmailAddressAction::class)->public();

    $services
        ->set('silverback.api_components.factory.user.mailer.verify_email')
        ->class(VerifyEmailFactory::class)
        ->parent(AbstractUserEmailFactory::class);
    $services->alias(VerifyEmailFactory::class, 'silverback.api_components.factory.user.mailer.verify_email');

    $services
        ->set('silverback.api_components.factory.user.mailer.welcome_email')
        ->class(WelcomeEmailFactory::class)
        ->parent(AbstractUserEmailFactory::class);
    $services->alias(WelcomeEmailFactory::class, 'silverback.api_components.factory.user.mailer.welcome_email');

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
        ->set('silverback.doctrine.repository.component_group')
        ->class(ComponentGroupRepository::class)
        ->args(
            [
                new Reference(ManagerRegistry::class),
            ]
        )
        ->tag('doctrine.repository_service');

    $services
        ->set('silverback.event_listener.api.orphaned_component')
        ->class(DeletedResourceEventListener::class)
        ->args(
            [
                new Reference('silverback.helper.orphaned_resource_helper'),
            ]
        )
        ->tag('kernel.event_listener', ['event' => ViewEvent::class, 'priority' => EventPriorities::PRE_WRITE, 'method' => 'onPreWrite']);

    $services
        ->set('silverback.helper.orphaned_resource_helper')
        ->class(OrphanedResourceHelper::class)
        ->args(
            [
                new Reference('silverback.metadata_factory.page_data'),
                new Reference('silverback.metadata_factory.component_usage'),
                new Reference(ManagerRegistry::class),
            ]
        );

    $services
        ->set('silverback.event_listener.api.position_remove')
        ->class(ComponentPositionEventListener::class)
        ->args(
            [
                new Reference(ManagerRegistry::class),
                new Reference(PublishableStatusChecker::class),
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
    $services->alias('silverback.api_components.api_platform.state_provider.page_data_metadata', PageDataMetadataStateProvider::class);

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
        ->set('silverback.api_components.api_platform.uuid_uri_variable_transformer')
        ->class(UuidUriVariableTransformer::class)
        ->decorate('api_platform.ramsey_uuid.uri_variables.transformer.uuid')
        ->args(
            [
                new Reference('silverback.api_components.api_platform.uuid_uri_variable_transformer.inner'),
            ]
        );
    $services->alias(UuidUriVariableTransformer::class, 'silverback.api_components.api_platform.uuid_uri_variable_transformer');

    $services
        ->set('silverback.api_components.api_platform.iri_converter')
        ->class(IriConverter::class)
        ->decorate('api_platform.iri_converter')
        ->args([
            new Reference('silverback.api_components.api_platform.iri_converter.inner'),
            new Reference('api_platform.metadata.resource.metadata_collection_factory'),
        ]);
    $services->alias('silverback.iri_converter', 'silverback.api_components.api_platform.iri_converter');
    $services->alias(IriConverter::class, 'silverback.api_components.api_platform.iri_converter');
    $services->alias(IriConverterInterface::class, 'silverback.api_components.api_platform.iri_converter');

    $services
        ->set('silverback.api_components.mercure.iri_converter')
        ->class(MercureIriConverter::class)
        ->args([
            new Reference('api_platform.iri_converter'),
            new Reference(PublishableStatusChecker::class),
        ]);
    $services->alias(MercureIriConverter::class, 'silverback.api_components.mercure.iri_converter');

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
        ->set('silverback.api_components.doctrine.event_listener.sqlite_foreign_key_enabler')
        ->class(SqlLiteForeignKeyEnabler::class)
        ->tag('doctrine.event_listener', ['event' => DoctrineEvents::preFlush]);
    $services->alias(SqlLiteForeignKeyEnabler::class, 'silverback.api_components.doctrine.event_listener.sqlite_foreign_key_enabler');

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

    $services
        ->set('silverback.api_components.fixture.cwa_fixture_builder')
        ->class(CwaFixtureBuilder::class)
        ->args([
            new Reference(TimestampedDataPersister::class),
            new Reference(RouteGeneratorInterface::class),
            new Reference(IriConverterInterface::class),
        ]);
    $services->alias(CwaFixtureBuilder::class, 'silverback.api_components.fixture.cwa_fixture_builder');

    $services
        ->set('silverback.api_components.uploadable.url_generator.api')
        ->class(ApiUrlGenerator::class)
        ->args([
            new Reference(IriConverterInterface::class),
            new Reference(UrlHelper::class),
        ])
        ->tag(UploadableUrlGeneratorInterface::TAG, ['alias' => 'api']);

    $services
        ->set('silverback.api_components.uploadable.url_generator.public')
        ->class(PublicUrlGenerator::class)
        ->tag(UploadableUrlGeneratorInterface::TAG, ['alias' => 'public']);

    $services
        ->set('silverback.api_components.uploadable.url_generator.temporary')
        ->class(TemporaryUrlGenerator::class)
        ->tag(UploadableUrlGeneratorInterface::TAG, ['alias' => 'temporary']);

    $services
        ->set('silverback.api_components.data_collector.data')
        ->class(CwaCollectorData::class);
    $services->alias(CwaCollectorData::class, 'silverback.api_components.data_collector.data');

    $services
        ->set('silverback.api_components.data_collector')
        ->class(CwaDataCollector::class)
        ->args([new Reference(CwaCollectorData::class)])
        ->tag('data_collector', [
            'template' => '@SilverbackApiComponents/Collector/cwa.html.twig',
            'id' => 'cwa',
        ]);
    $services->alias(CwaDataCollector::class, 'silverback.api_components.data_collector');
};
