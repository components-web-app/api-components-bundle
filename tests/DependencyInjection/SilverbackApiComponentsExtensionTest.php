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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource\RoutableResourceMetadataCollectionFactory;
use Silverback\ApiComponentsBundle\ApiResource\ResourceManifest;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\DependencyInjection\SilverbackApiComponentsExtension;
use Silverback\ApiComponentsBundle\Doctrine\Extension\ORM\RoutableExtension;
use Silverback\ApiComponentsBundle\Doctrine\Extension\ORM\RouteExtension;
use Silverback\ApiComponentsBundle\Doctrine\Extension\ORM\TablePrefixExtension;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\ChangeEmailConfirmationEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\PasswordResetEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\VerifyEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\WelcomeEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\UserFactory;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Helper\User\UserDataProcessor;
use Silverback\ApiComponentsBundle\Helper\User\UserMailer;
use Silverback\ApiComponentsBundle\Mercure\MercureAuthorization;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;
use Silverback\ApiComponentsBundle\Security\UserChecker;
use Silverback\ApiComponentsBundle\Security\Voter\RoutableVoter;
use Silverback\ApiComponentsBundle\Security\Voter\RouteVoter;
use Silverback\ApiComponentsBundle\Security\Voter\SiteConfigParameterVoter;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\MetadataNormalizer;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
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
    private function load(array $config): array
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->setParameter('kernel.debug', false);
        $container->setParameter('api_components.imagine_enabled', false);

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
        // canBeDisabled() was decorative: the flag could not be set (the node hard-failed on its
        // required children) and VerifyEmailFactory was wired with a hardcoded true regardless.
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
        self::assertSame(86400, $this->argument($container, UserDataProcessor::class, '$tokenTtl'));
        self::assertSame(604800, $container->getParameter('silverback.api_components.refresh_token.ttl'));

        self::assertSame([], $this->argument($container, RouteExtension::class, '$config'));
        self::assertSame([], $this->argument($container, RouteVoter::class, '$config'));
        self::assertNull($this->argument($container, RoutableExtension::class, '$securityStr'));
        self::assertNull($this->argument($container, RoutableVoter::class, '$securityStr'));
        self::assertNull($this->argument($container, RoutableResourceMetadataCollectionFactory::class, '$securityStr'));

        self::assertSame(Cookie::SAMESITE_STRICT, $this->argument($container, MercureAuthorization::class, '$cookieSameSite'));
        self::assertNull($this->argument($container, MercureAuthorization::class, '$hubName'));
        self::assertFalse($this->argument($container, MercureAuthorization::class, '$secureSubscriptions'));

        self::assertSame(
            [Route::class, ResourceManifest::class, ComponentPosition::class],
            $this->argument($container, 'silverback.api_components.event_listener.api.cache_headers', '$personalisedResourceClasses')
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
        $config['http_cache'] = ['personalised_resource_classes' => [Route::class]];
        $config['user']['password_reset'] = ['repeat_ttl_seconds' => 60, 'request_timeout_seconds' => 120];
        $config['user']['new_email_confirmation'] = ['request_timeout_seconds' => 180];

        [$container, $errors] = $this->load($config);

        self::assertSame([], $errors);
        self::assertSame('app_', $this->argument($container, TablePrefixExtension::class, '$prefix'));
        self::assertSame('_meta', $this->argument($container, MetadataNormalizer::class, '$metadataKey'));
        self::assertSame('App\Entity\User', $this->argument($container, UserRepositoryInterface::class, '$entityClass'));
        self::assertSame('App\Entity\User', $this->argument($container, UserFactory::class, '$userClass'));
        self::assertSame(120, $this->argument($container, UserRepositoryInterface::class, '$passwordRequestTimeout'));
        self::assertSame(180, $this->argument($container, UserRepositoryInterface::class, '$newEmailConfirmTimeout'));
        self::assertSame(60, $this->argument($container, UserDataProcessor::class, '$tokenTtl'));

        self::assertSame($config['route_security'], $this->argument($container, RouteVoter::class, '$config'));
        self::assertSame("is_granted('ROLE_ADMIN')", $this->argument($container, RoutableVoter::class, '$securityStr'));
        self::assertSame("is_granted('ROLE_ADMIN')", $this->argument($container, PublishableStatusChecker::class, '$permission'));
        self::assertSame("is_granted('ROLE_ADMIN')", $this->argument($container, SiteConfigParameterVoter::class, '$permission'));

        self::assertSame(Cookie::SAMESITE_LAX, $this->argument($container, MercureAuthorization::class, '$cookieSameSite'));
        self::assertSame('default', $this->argument($container, MercureAuthorization::class, '$hubName'));
        self::assertTrue($this->argument($container, MercureAuthorization::class, '$secureSubscriptions'));
        self::assertSame([Route::class], $this->argument($container, 'silverback.api_components.event_listener.api.cache_headers', '$personalisedResourceClasses'));
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

        // The other two are always available; only email_verification has a switch.
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
}
