<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\DependencyInjection;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource\RoutableResourceMetadataCollectionFactory;
use Silverback\ApiComponentsBundle\ApiResource\ResourceManifest;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Command\DeduplicateFileInfoCommand;
use Silverback\ApiComponentsBundle\Command\ImagineGenerateCommand;
use Silverback\ApiComponentsBundle\Command\PrefixPageUiComponentsCommand;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\RealtimeEventStateProcessor;
use Silverback\ApiComponentsBundle\DependencyInjection\SilverbackApiComponentsExtension;
use Silverback\ApiComponentsBundle\Doctrine\Extension\ORM\RoutableExtension;
use Silverback\ApiComponentsBundle\Doctrine\Extension\ORM\RouteExtension;
use Silverback\ApiComponentsBundle\Doctrine\Extension\ORM\TablePrefixExtension;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\RoutableInterface;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Entity\Core\SiteConfigParameter;
use Silverback\ApiComponentsBundle\EventListener\Console\FixtureLoadListener;
use Silverback\ApiComponentsBundle\Exception\ApiPlatformAuthenticationException;
use Silverback\ApiComponentsBundle\Exception\DisallowedRequestOriginException;
use Silverback\ApiComponentsBundle\Exception\HttpCacheFlushFailedException;
use Silverback\ApiComponentsBundle\Exception\HttpCachePurgeFailedException;
use Silverback\ApiComponentsBundle\Exception\UnparseableRequestHeaderException;
use Silverback\ApiComponentsBundle\Exception\UnroutedParentException;
use Silverback\ApiComponentsBundle\Exception\UserDisabledException;
use Silverback\ApiComponentsBundle\Factory\OrphanedResource\OrphanedResourcesChangedEmailFactory;
use Silverback\ApiComponentsBundle\Factory\Uploadable\MediaObjectFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\ChangeEmailConfirmationEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\PasswordResetEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\VerifyEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\WelcomeEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\UserFactory;
use Silverback\ApiComponentsBundle\Fixture\Asset\FixtureAssetStoreFactory;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Helper\OrphanedFile\OrphanedFileDetector;
use Silverback\ApiComponentsBundle\Helper\OrphanedFile\StoredFileConstraintChecker;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceNotifier;
use Silverback\ApiComponentsBundle\Helper\Page\PageUiComponentPrefixer;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Helper\RefererUrlResolver;
use Silverback\ApiComponentsBundle\Helper\Uploadable\FileInfoCacheManager;
use Silverback\ApiComponentsBundle\Helper\Uploadable\FileInfoDeduplicator;
use Silverback\ApiComponentsBundle\Helper\Uploadable\ImagineVariantGenerator;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableCacheControl;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Silverback\ApiComponentsBundle\Helper\User\UserDataProcessor;
use Silverback\ApiComponentsBundle\Helper\User\UserMailer;
use Silverback\ApiComponentsBundle\Imagine\FlysystemDataLoader;
use Silverback\ApiComponentsBundle\Imagine\ImagineFilterGenerator;
use Silverback\ApiComponentsBundle\Imagine\PhpMemoryLimit;
use Silverback\ApiComponentsBundle\Mercure\MercureAuthorization;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;
use Silverback\ApiComponentsBundle\Security\UserChecker;
use Silverback\ApiComponentsBundle\Security\Voter\RoutableVoter;
use Silverback\ApiComponentsBundle\Security\Voter\RouteVoter;
use Silverback\ApiComponentsBundle\Security\Voter\SiteConfigParameterVoter;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\MetadataNormalizer;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\PageUiComponentNormalizer;
use Silverback\ApiComponentsBundle\Validator\Constraints\UploadedImageValidator;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Configuration as FrameworkConfiguration;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The extension reads `user.email_verification` keys straight out of the processed configuration.
 * Those keys were declared `isRequired()` under a node carrying a default, so they were never
 * enforced and never present: loading the extension emitted undefined-array-key warnings and wired
 * the services with null. These tests load the extension against the smallest configuration an
 * application can supply and assert that neither happens.
 *
 * @author Daniel West <daniel@silverback.is>
 */
#[CoversClass(SilverbackApiComponentsExtension::class)]
class SilverbackApiComponentsExtensionTest extends TestCase
{
    /**
     * `refresh_token.handler_id` deliberately avoids the doctrine storage: that branch reads
     * `refresh_token.options.class`, an unrelated instance of the same latent pattern which is out
     * of scope here and would otherwise trip the error handler below.
     */
    private static function minimalConfig(): array
    {
        return [
            'refresh_token' => [
                'handler_id' => 'app.refresh_token.storage',
                'cookie_name' => 'api_components',
                'ttl' => 604800,
                'database_user_provider' => 'database',
            ],
            'website_name' => 'Test Website',
            'user' => ['class_name' => 'App\Entity\User'],
            'publishable' => ['permission' => "is_granted('ROLE_ADMIN')"],
        ];
    }

    /**
     * Warnings and notices only. Reading a key that is not there raises E_WARNING, which is the
     * symptom under test; deprecations are third-party noise (a lowest-dependencies MakerBundle
     * raises one while its classes autoload here) and are handed back to the normal handler so the
     * phpunit-bridge still accounts for them.
     */
    private const REPORTED_LEVELS = \E_WARNING | \E_NOTICE | \E_USER_WARNING | \E_USER_NOTICE;

    /**
     * @return array{0: ContainerBuilder, 1: list<string>} the built container and any warnings or
     *                                                     notices raised while loading it
     */
    public function test_bundle_exceptions_are_mapped_to_their_http_status(): void
    {
        $container = new ContainerBuilder();
        $container->prependExtensionConfig('silverback_api_components', self::minimalConfig());

        (new SilverbackApiComponentsExtension())->prepend($container);

        $mapped = array_merge(...array_column($container->getExtensionConfig('api_platform'), 'exception_to_status'));

        self::assertSame(400, $mapped[UnparseableRequestHeaderException::class]);
        self::assertSame(401, $mapped[ApiPlatformAuthenticationException::class]);
        self::assertSame(401, $mapped[UserDisabledException::class]);
        self::assertSame(422, $mapped[UnroutedParentException::class]);
        self::assertSame(502, $mapped[HttpCachePurgeFailedException::class]);
        self::assertSame(502, $mapped[HttpCacheFlushFailedException::class]);
    }

    public function test_the_bundle_leaves_use_symfony_listeners_to_api_platform_and_the_application(): void
    {
        $container = new ContainerBuilder();
        $container->prependExtensionConfig('silverback_api_components', self::minimalConfig());

        (new SilverbackApiComponentsExtension())->prepend($container);

        $enabled = array_column($container->getExtensionConfig('api_platform'), 'use_symfony_listeners');

        self::assertSame([], $enabled);
    }

    public function test_the_realtime_rate_limiter_pool_is_a_framework_pool_over_cache_app_by_default(): void
    {
        self::assertSame(['cache.app'], self::frameworkPools([])[RealtimeEventStateProcessor::RATE_LIMITER_POOL]['adapters']);
    }

    public function test_an_application_can_point_the_realtime_rate_limiter_pool_at_another_adapter(): void
    {
        $pools = self::frameworkPools(['cache' => ['pools' => [RealtimeEventStateProcessor::RATE_LIMITER_POOL => ['adapter' => 'cache.adapter.redis']]]]);

        self::assertSame(['cache.adapter.redis'], $pools[RealtimeEventStateProcessor::RATE_LIMITER_POOL]['adapters']);
    }

    /**
     * @param array<string, mixed> $applicationConfig
     *
     * @return array<string, array{adapters: list<string>}>
     */
    private static function frameworkPools(array $applicationConfig): array
    {
        $container = new ContainerBuilder();
        $container->prependExtensionConfig('silverback_api_components', self::minimalConfig());

        (new SilverbackApiComponentsExtension())->prepend($container);

        return (new Processor())->processConfiguration(new FrameworkConfiguration(false), [...$container->getExtensionConfig('framework'), $applicationConfig])['cache']['pools'];
    }

    private function load(array $config, bool $imagineEnabled = false): array
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->setParameter('kernel.debug', false);
        $container->setParameter('api_components.imagine_enabled', $imagineEnabled);

        $errors = [];
        set_error_handler(static function (int $errno, string $message) use (&$errors): bool {
            if (!($errno & self::REPORTED_LEVELS)) {
                return false;
            }

            $errors[] = $message;

            return true;
        });

        try {
            (new SilverbackApiComponentsExtension())->load([$config], $container);
        } finally {
            restore_error_handler();
        }

        return [$container, $errors];
    }

    private function argument(ContainerBuilder $container, string $id, string $argument): mixed
    {
        $definition = $container->findDefinition($id);
        self::assertInstanceOf(Definition::class, $definition);

        return $definition->getArgument($argument);
    }

    public function test_by_default_a_flush_dispatches_no_warm(): void
    {
        [$container] = $this->load(self::minimalConfig());

        self::assertArrayNotHasKey('$warmDispatcher', $container->findDefinition('silverback.api_components.http_cache.flusher')->getArguments());
    }

    public function test_an_enabled_warm_after_flush_reaches_the_flusher_and_the_handler(): void
    {
        $config = self::minimalConfig();
        $config['http_cache']['warm_after_flush'] = ['base_url' => 'https://cache.test', 'max_time' => 15, 'concurrency' => 3, 'timeout' => 9, 'limit' => 50, 'requests_per_connection' => 3];

        [$container, $errors] = $this->load($config);

        self::assertSame([], $errors);
        $dispatcher = $this->argument($container, 'silverback.api_components.http_cache.flusher', '$warmDispatcher');
        self::assertInstanceOf(Reference::class, $dispatcher);
        self::assertSame('silverback.api_components.http_cache.warm_dispatcher', (string) $dispatcher);
        $handler = 'silverback.api_components.message_handler.warm_http_cache';
        self::assertSame('https://cache.test', $this->argument($container, $handler, '$baseUrl'));
        self::assertSame(15.0, $this->argument($container, $handler, '$maxTime'));
        self::assertSame(3, $this->argument($container, $handler, '$concurrency'));
        self::assertSame(9.0, $this->argument($container, $handler, '$timeout'));
        self::assertSame(50, $this->argument($container, $handler, '$limit'));
        self::assertSame(3, $this->argument($container, $handler, '$requestsPerConnection'));
    }

    public function test_with_imagine_enabled_both_filter_callers_generate_through_the_memory_guard(): void
    {
        [$container] = $this->load(self::minimalConfig(), true);

        $generator = $container->findDefinition(ImagineFilterGenerator::class);
        self::assertSame(ImagineFilterGenerator::class, $generator->getClass());
        self::assertEquals(
            [
                new Reference('liip_imagine.service.filter'),
                new Reference('liip_imagine.cache.manager'),
                new Reference(PhpMemoryLimit::class),
                '%liip_imagine.driver_service%',
                '320M',
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ],
            $generator->getArguments()
        );
        self::assertEquals(new Reference(ImagineFilterGenerator::class), $this->argument($container, MediaObjectFactory::class, '$imagineFilterGenerator'));
        self::assertEquals(new Reference(ImagineFilterGenerator::class), $this->argument($container, UploadableFileManager::class, '$imagineFilterGenerator'));
        self::assertEquals(new Reference('liip_imagine.cache.manager'), $this->argument($container, UploadableFileManager::class, '$imagineCacheManager'));
    }

    public function test_the_upload_cache_control_reaches_its_service_and_every_writer(): void
    {
        $config = self::minimalConfig();
        $config['uploadable']['cache_control'] = ['original' => 'public, max-age=60', 'imagine' => null];

        [$container] = $this->load($config, true);

        self::assertSame('public, max-age=60', $this->argument($container, UploadableCacheControl::class, '$original'));
        self::assertNull($this->argument($container, UploadableCacheControl::class, '$imagine'));
        self::assertEquals(new Reference(UploadableCacheControl::class), $this->argument($container, UploadableFileManager::class, 5));
        self::assertEquals(new Reference(UploadableCacheControl::class), $this->argument($container, MediaObjectFactory::class, '$cacheControl'));
    }

    public function test_the_default_upload_cache_control_reaches_its_service(): void
    {
        [$container] = $this->load(self::minimalConfig());

        self::assertSame('public, max-age=31536000, immutable', $this->argument($container, UploadableCacheControl::class, '$original'));
        self::assertSame('public, max-age=2592000', $this->argument($container, UploadableCacheControl::class, '$imagine'));
    }

    public function test_the_uploaded_image_pixel_limit_reaches_the_upload_validator_and_the_orphaned_file_scan(): void
    {
        $config = self::minimalConfig();
        $config['uploadable']['image_max_pixels'] = 300000;

        [$container] = $this->load($config);

        $validator = $container->findDefinition(UploadedImageValidator::class);
        self::assertSame(UploadedImageValidator::class, $validator->getClass());
        self::assertTrue($validator->hasTag('validator.constraint_validator'));
        self::assertSame(300000, $validator->getArgument('$defaultMaxPixels'));
        self::assertSame('silverback.api_components.validator.uploaded_image', (string) $container->getAlias(UploadedImageValidator::class));
        self::assertSame(300000, $this->argument($container, StoredFileConstraintChecker::class, '$defaultMaxPixels'));
    }

    public function test_the_default_uploaded_image_pixel_limit_reaches_both_validators(): void
    {
        [$container] = $this->load(self::minimalConfig());

        self::assertSame(30000000, $this->argument($container, UploadedImageValidator::class, '$defaultMaxPixels'));
        self::assertSame(30000000, $this->argument($container, StoredFileConstraintChecker::class, '$defaultMaxPixels'));
    }

    public function test_with_imagine_enabled_the_generate_command_is_registered_with_its_helper(): void
    {
        [$container] = $this->load(self::minimalConfig(), true);

        $command = $container->findDefinition(ImagineGenerateCommand::class);
        self::assertSame(ImagineGenerateCommand::class, $command->getClass());
        self::assertFalse($command->isAutoconfigured());
        self::assertSame([['command' => ImagineGenerateCommand::NAME]], $command->getTag('console.command'));
        self::assertEquals([new Reference(ImagineVariantGenerator::class), new Reference('liip_imagine.filter.configuration')], $command->getArguments());
        self::assertSame('silverback.api_components.command.imagine_generate', (string) $container->getAlias(ImagineGenerateCommand::class));

        $generator = $container->findDefinition(ImagineVariantGenerator::class);
        self::assertSame(ImagineVariantGenerator::class, $generator->getClass());
        self::assertFalse($generator->isAutoconfigured());
        self::assertEquals(
            [
                new Reference(ManagerRegistry::class),
                new Reference(UploadableAttributeReader::class),
                new Reference(FilesystemProvider::class),
                new Reference(FlysystemDataLoader::class),
                new Reference(FileInfoCacheManager::class),
                new Reference(UploadableCacheControl::class),
                new Reference(ImagineFilterGenerator::class),
                50,
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ],
            $generator->getArguments()
        );
        self::assertSame('silverback.api_components.helper.uploadable.imagine_variant_generator', (string) $container->getAlias(ImagineVariantGenerator::class));
    }

    public function test_the_configured_imagine_memory_limit_reaches_the_filter_generator(): void
    {
        $config = self::minimalConfig();
        $config['imagine']['memory_limit'] = null;

        [$container] = $this->load($config, true);

        self::assertNull($container->findDefinition(ImagineFilterGenerator::class)->getArgument(4));
    }

    public function test_the_file_info_deduplication_command_is_registered_with_its_helper(): void
    {
        [$container] = $this->load(self::minimalConfig());

        $command = $container->findDefinition(DeduplicateFileInfoCommand::class);
        self::assertSame([['command' => 'silverback:api-components:deduplicate-file-info']], $command->getTag('console.command'));
        self::assertEquals([new Reference(FileInfoDeduplicator::class)], $command->getArguments());
        self::assertSame(FileInfoDeduplicator::class, $container->findDefinition(FileInfoDeduplicator::class)->getClass());
    }

    public function test_the_page_ui_component_prefix_command_is_registered_with_its_helper(): void
    {
        [$container] = $this->load(self::minimalConfig());

        $command = $container->findDefinition(PrefixPageUiComponentsCommand::class);
        self::assertSame([['command' => 'silverback:api-components:prefix-page-ui-components']], $command->getTag('console.command'));
        self::assertEquals([new Reference(PageUiComponentPrefixer::class)], $command->getArguments());
        $prefixer = $container->findDefinition(PageUiComponentPrefixer::class);
        self::assertSame(PageUiComponentPrefixer::class, $prefixer->getClass());
        self::assertEquals([new Reference(EntityManagerInterface::class)], $prefixer->getArguments());
    }

    public function test_the_page_ui_component_normalizer_runs_before_the_item_denormalizers(): void
    {
        [$container] = $this->load(self::minimalConfig());

        $normalizer = $container->findDefinition(PageUiComponentNormalizer::class);
        self::assertSame(PageUiComponentNormalizer::class, $normalizer->getClass());
        self::assertSame([['priority' => -499]], $normalizer->getTag('serializer.normalizer'));
        self::assertFalse($normalizer->isAutoconfigured());
    }

    public function test_the_fixture_load_listener_says_to_generate_variants_only_with_imagine_enabled(): void
    {
        [$withImagine] = $this->load(self::minimalConfig(), true);
        [$withoutImagine] = $this->load(self::minimalConfig());

        self::assertTrue($this->argument($withImagine, FixtureLoadListener::class, '$imagineEnabled'));
        self::assertFalse($this->argument($withoutImagine, FixtureLoadListener::class, '$imagineEnabled'));
    }

    public function test_without_imagine_no_filter_generator_is_registered(): void
    {
        [$container] = $this->load(self::minimalConfig());

        self::assertFalse($container->has(ImagineFilterGenerator::class));
        self::assertFalse($container->has(ImagineVariantGenerator::class));
        self::assertFalse($container->has(ImagineGenerateCommand::class));
        self::assertArrayNotHasKey('$imagineFilterGenerator', $container->findDefinition(MediaObjectFactory::class)->getArguments());
        self::assertArrayNotHasKey('$imagineFilterGenerator', $container->findDefinition(UploadableFileManager::class)->getArguments());
    }

    public function test_loading_the_minimal_config_raises_no_php_errors(): void
    {
        [, $errors] = $this->load(self::minimalConfig());

        self::assertSame([], $errors);
    }

    public function test_email_verification_services_are_never_wired_with_null(): void
    {
        [$container] = $this->load(self::minimalConfig());

        self::assertFalse($this->argument($container, UserChecker::class, '$denyUnverifiedLogin'));
        self::assertFalse($this->argument($container, UserDataProcessor::class, '$initialEmailVerifiedState'));
        self::assertFalse($this->argument($container, UserDataProcessor::class, '$verifyEmailOnRegister'));
        self::assertFalse($this->argument($container, UserDataProcessor::class, '$verifyEmailOnChange'));
    }

    public function test_an_explicit_email_verification_config_reaches_the_services(): void
    {
        $config = self::minimalConfig();
        $config['user']['email_verification'] = [
            'default_value' => false,
            'verify_on_register' => true,
            'verify_on_change' => true,
            'deny_unverified_login' => true,
            'email' => ['default_redirect_path' => '/verify-email/{{ username }}/{{ token }}'],
        ];

        [$container, $errors] = $this->load($config);

        self::assertSame([], $errors);
        self::assertTrue($this->argument($container, UserChecker::class, '$denyUnverifiedLogin'));
        self::assertFalse($this->argument($container, UserDataProcessor::class, '$initialEmailVerifiedState'));
        self::assertTrue($this->argument($container, UserDataProcessor::class, '$verifyEmailOnRegister'));
        self::assertTrue($this->argument($container, UserDataProcessor::class, '$verifyEmailOnChange'));
        self::assertSame(
            '/verify-email/{{ username }}/{{ token }}',
            $this->argument($container, VerifyEmailFactory::class, '$defaultRedirectPath')
        );
    }

    public function test_disabling_email_verification_disables_the_verification_email(): void
    {
        $config = self::minimalConfig();
        $config['user']['email_verification'] = false;

        [$container] = $this->load($config);

        self::assertFalse($this->argument($container, VerifyEmailFactory::class, '$enabled'));
    }

    public function test_the_verification_email_is_enabled_by_default(): void
    {
        [$container] = $this->load(self::minimalConfig());

        self::assertTrue($this->argument($container, VerifyEmailFactory::class, '$enabled'));
    }

    public function test_defaulted_configuration_reaches_the_services_that_consume_it(): void
    {
        [$container] = $this->load(self::minimalConfig());

        self::assertSame('_acb_', $this->argument($container, TablePrefixExtension::class, '$prefix'));
        self::assertSame('_metadata', $this->argument($container, MetadataNormalizer::class, '$metadataKey'));
        self::assertSame(3600, $this->argument($container, UserRepositoryInterface::class, '$passwordRequestTimeout'));
        self::assertSame(86400, $this->argument($container, UserRepositoryInterface::class, '$newEmailConfirmTimeout'));
        self::assertSame(86400, $this->argument($container, UserDataProcessor::class, '$passwordResetRepeatTtl'));
        self::assertSame(300, $this->argument($container, UserDataProcessor::class, '$newEmailConfirmationRepeatTtl'));
        self::assertSame(300, $this->argument($container, UserDataProcessor::class, '$emailVerificationRepeatTtl'));
        self::assertSame(604800, $container->getParameter('silverback.api_components.refresh_token.ttl'));

        self::assertSame([], $this->argument($container, RouteExtension::class, '$config'));
        self::assertSame([], $this->argument($container, RouteVoter::class, '$config'));
        self::assertNull($this->argument($container, RoutableExtension::class, '$securityStr'));
        self::assertNull($this->argument($container, RoutableVoter::class, '$securityStr'));
        self::assertNull($this->argument($container, RoutableResourceMetadataCollectionFactory::class, '$securityStr'));

        self::assertSame('%silverback.api_components.refresh_token.ttl%', $container->findDefinition(MercureAuthorization::class)->getArgument(8));
        self::assertSame('cookieLifetime', new \ReflectionMethod(MercureAuthorization::class, '__construct')->getParameters()[8]->getName());
        self::assertSame(Cookie::SAMESITE_STRICT, $this->argument($container, MercureAuthorization::class, '$cookieSameSite'));
        self::assertNull($this->argument($container, MercureAuthorization::class, '$hubName'));
        self::assertNull($this->argument($container, RealtimeEventStateProcessor::class, '$hubName'));
        self::assertFalse($this->argument($container, MercureAuthorization::class, '$secureSubscriptions'));

        self::assertSame(
            [Route::class, ResourceManifest::class, ComponentPosition::class],
            $this->argument($container, 'silverback.api_components.event_listener.api.cache_headers', '$personalisedResourceClasses')
        );

        self::assertSame(
            [Route::class, RoutableInterface::class, ResourceManifest::class],
            $this->argument($container, 'silverback.api_components.event_listener.api.cache_headers', '$scheduledExpiryResourceClasses')
        );

        self::assertSame(
            [SiteConfigParameter::class],
            $this->argument($container, 'silverback.api_components.http_cache.purger', '$purgeRenderedHtmlClasses')
        );

        self::assertFalse($this->argument($container, UploadableAttributeReader::class, '$imagineBundleEnabled'));
    }

    public function test_explicit_configuration_reaches_the_services_that_consume_it(): void
    {
        $config = self::minimalConfig();
        $config['table_prefix'] = 'app_';
        $config['metadata_key'] = '_meta';
        $config['routable_security'] = "is_granted('ROLE_ADMIN')";
        $config['route_security'] = [['route' => '/user-area*', 'security' => "is_granted('ROLE_USER')"]];
        $config['mercure'] = ['hub_name' => 'default', 'secure_subscriptions' => true, 'cookie' => ['samesite' => Cookie::SAMESITE_LAX]];
        $config['http_cache'] = ['personalised_resource_classes' => [Route::class], 'scheduled_expiry_resource_classes' => [ResourceManifest::class], 'purge_rendered_html_classes' => [ComponentPosition::class]];
        $config['user']['password_reset'] = ['repeat_ttl_seconds' => 60, 'request_timeout_seconds' => 120];
        $config['user']['new_email_confirmation'] = ['request_timeout_seconds' => 180, 'repeat_ttl_seconds' => 240];
        $config['user']['email_verification'] = ['repeat_ttl_seconds' => 270];

        [$container, $errors] = $this->load($config);

        self::assertSame([], $errors);
        self::assertSame('app_', $this->argument($container, TablePrefixExtension::class, '$prefix'));
        self::assertSame('_meta', $this->argument($container, MetadataNormalizer::class, '$metadataKey'));
        self::assertSame('App\Entity\User', $this->argument($container, UserRepositoryInterface::class, '$entityClass'));
        self::assertSame('App\Entity\User', $this->argument($container, UserFactory::class, '$userClass'));
        self::assertSame(120, $this->argument($container, UserRepositoryInterface::class, '$passwordRequestTimeout'));
        self::assertSame(180, $this->argument($container, UserRepositoryInterface::class, '$newEmailConfirmTimeout'));
        self::assertSame(60, $this->argument($container, UserDataProcessor::class, '$passwordResetRepeatTtl'));
        self::assertSame(240, $this->argument($container, UserDataProcessor::class, '$newEmailConfirmationRepeatTtl'));
        self::assertSame(270, $this->argument($container, UserDataProcessor::class, '$emailVerificationRepeatTtl'));

        self::assertSame($config['route_security'], $this->argument($container, RouteVoter::class, '$config'));
        self::assertSame("is_granted('ROLE_ADMIN')", $this->argument($container, RoutableVoter::class, '$securityStr'));
        self::assertSame("is_granted('ROLE_ADMIN')", $this->argument($container, PublishableStatusChecker::class, '$permission'));
        self::assertSame("is_granted('ROLE_ADMIN')", $this->argument($container, SiteConfigParameterVoter::class, '$permission'));

        self::assertSame(Cookie::SAMESITE_LAX, $this->argument($container, MercureAuthorization::class, '$cookieSameSite'));
        self::assertSame('default', $this->argument($container, MercureAuthorization::class, '$hubName'));
        self::assertSame('default', $this->argument($container, RealtimeEventStateProcessor::class, '$hubName'));
        self::assertTrue($this->argument($container, MercureAuthorization::class, '$secureSubscriptions'));
        self::assertSame([Route::class], $this->argument($container, 'silverback.api_components.event_listener.api.cache_headers', '$personalisedResourceClasses'));
        self::assertSame([ResourceManifest::class], $this->argument($container, 'silverback.api_components.event_listener.api.cache_headers', '$scheduledExpiryResourceClasses'));
        self::assertSame([ComponentPosition::class], $this->argument($container, 'silverback.api_components.http_cache.purger', '$purgeRenderedHtmlClasses'));
    }

    public function test_the_mailer_subjects_default_and_can_be_overridden(): void
    {
        [$container] = $this->load(self::minimalConfig());

        self::assertSame('Please verify your email', $this->argument($container, VerifyEmailFactory::class, '$subject'));
        self::assertSame('Your password reset request', $this->argument($container, PasswordResetEmailFactory::class, '$subject'));
        self::assertSame('Please confirm your new email address', $this->argument($container, ChangeEmailConfirmationEmailFactory::class, '$subject'));
        self::assertSame('Welcome to {{ website_name }}', $this->argument($container, WelcomeEmailFactory::class, '$subject'));
        self::assertTrue($this->argument($container, WelcomeEmailFactory::class, '$enabled'));
        self::assertSame(['website_name' => 'Test Website'], $this->argument($container, UserMailer::class, '$context'));

        self::assertTrue($this->argument($container, PasswordResetEmailFactory::class, '$enabled'));
        self::assertTrue($this->argument($container, ChangeEmailConfirmationEmailFactory::class, '$enabled'));
    }

    public function test_a_disabled_welcome_email_is_wired_as_disabled(): void
    {
        $config = self::minimalConfig();
        $config['user']['emails'] = ['welcome' => ['enabled' => false, 'subject' => 'Hi']];

        [$container] = $this->load($config);

        self::assertFalse($this->argument($container, WelcomeEmailFactory::class, '$enabled'));
        self::assertSame('Hi', $this->argument($container, WelcomeEmailFactory::class, '$subject'));
    }

    public function test_the_welcome_email_uses_the_email_verification_redirect(): void
    {
        $config = self::minimalConfig();
        $config['user']['email_verification'] = [
            'verify_on_register' => true,
            'email' => [
                'default_redirect_path' => '/verify/{{ token }}',
                'redirect_path_query' => 'email_redirect',
            ],
        ];

        [$container] = $this->load($config);

        self::assertSame('/verify/{{ token }}', $this->argument($container, WelcomeEmailFactory::class, '$defaultRedirectPath'));
        self::assertSame('email_redirect', $this->argument($container, WelcomeEmailFactory::class, '$redirectPathQueryKey'));
    }

    public function test_email_links_allow_no_origin_by_default(): void
    {
        [$container] = $this->load(self::minimalConfig());

        self::assertSame([], $this->argument($container, RefererUrlResolver::class, '$allowedOrigins'));
        self::assertNull($this->argument($container, RefererUrlResolver::class, '$defaultOrigin'));
    }

    public function test_email_link_configuration_reaches_the_resolver(): void
    {
        $config = self::minimalConfig();
        $config['user']['email_links'] = [
            'allowed_origins' => ['https://www\.example\.com'],
            'default_origin' => 'https://www.example.com',
        ];

        [$container] = $this->load($config);

        self::assertSame(['https://www\.example\.com'], $this->argument($container, RefererUrlResolver::class, '$allowedOrigins'));
        self::assertSame('https://www.example.com', $this->argument($container, RefererUrlResolver::class, '$defaultOrigin'));
    }

    public function test_a_disallowed_request_origin_is_mapped_to_bad_request(): void
    {
        self::assertTrue(is_a(DisallowedRequestOriginException::class, UnparseableRequestHeaderException::class, true));
    }

    public function test_with_no_fixture_asset_store_configured_the_factory_is_wired_disabled(): void
    {
        [$container] = $this->load(self::minimalConfig());

        self::assertFalse($this->argument($container, FixtureAssetStoreFactory::class, '$enabled'));
        self::assertSame('local', $this->argument($container, FixtureAssetStoreFactory::class, '$store'));
        self::assertNull($this->argument($container, FixtureAssetStoreFactory::class, '$path'));
        self::assertNull($this->argument($container, FixtureAssetStoreFactory::class, '$adapter'));
        self::assertSame('', $this->argument($container, FixtureAssetStoreFactory::class, '$prefix'));
    }

    public function test_the_fixture_asset_store_configuration_reaches_the_factory(): void
    {
        $config = self::minimalConfig();
        $config['fixtures']['assets'] = ['store' => 'flysystem', 'path' => '/app/assets', 'adapter' => 'gcloud', 'prefix' => 'fixtures/'];

        [$container] = $this->load($config);

        self::assertTrue($this->argument($container, FixtureAssetStoreFactory::class, '$enabled'));
        self::assertSame('flysystem', $this->argument($container, FixtureAssetStoreFactory::class, '$store'));
        self::assertSame('/app/assets', $this->argument($container, FixtureAssetStoreFactory::class, '$path'));
        self::assertSame('gcloud', $this->argument($container, FixtureAssetStoreFactory::class, '$adapter'));
        self::assertSame('fixtures/', $this->argument($container, FixtureAssetStoreFactory::class, '$prefix'));
    }

    public function test_the_orphaned_files_configuration_reaches_the_detector(): void
    {
        $config = self::minimalConfig();
        $config['orphaned_files'] = ['minimum_age' => 60, 'excluded_paths' => ['exports/']];

        [$container] = $this->load($config);

        self::assertSame(60, $this->argument($container, OrphanedFileDetector::class, '$minimumAge'));
        self::assertSame(['exports/'], $this->argument($container, OrphanedFileDetector::class, '$excludedPaths'));
    }

    public function test_orphaned_resource_notifications_are_wired_with_no_recipients_by_default(): void
    {
        [$container] = $this->load(self::minimalConfig());

        self::assertSame([], $this->argument($container, OrphanedResourceNotifier::class, '$recipients'));
        self::assertSame('/_cwa/orphaned', $this->argument($container, OrphanedResourceNotifier::class, '$adminPagePath'));
        self::assertSame('Orphaned resources changed on {{ website_name }}', $this->argument($container, OrphanedResourcesChangedEmailFactory::class, '$subject'));
        self::assertSame(['website_name' => 'Test Website'], $this->argument($container, OrphanedResourcesChangedEmailFactory::class, '$context'));
    }

    public function test_orphaned_resource_notification_configuration_reaches_the_notifier(): void
    {
        $config = self::minimalConfig();
        $config['orphaned_resources']['notify'] = [
            'recipients' => ['admin@example.com'],
            'admin_page_path' => '/admin/orphans',
            'subject' => 'Orphans',
        ];

        [$container] = $this->load($config);

        self::assertSame(['admin@example.com'], $this->argument($container, OrphanedResourceNotifier::class, '$recipients'));
        self::assertSame('/admin/orphans', $this->argument($container, OrphanedResourceNotifier::class, '$adminPagePath'));
        self::assertSame('Orphans', $this->argument($container, OrphanedResourcesChangedEmailFactory::class, '$subject'));
    }
}
