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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\BaseNode;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * `user.email_verification` used to declare `isRequired()` children under a node that carries a
 * default value. ArrayNode::finalizeValue() inserts the default and skips finalisation for an
 * omitted child, so those isRequired() checks never ran — the node resolved to `['enabled' => true]`
 * and SilverbackApiComponentsExtension then read keys that were not there, emitting
 * undefined-array-key warnings and wiring services with null. The same declaration made the node
 * impossible to configure partially: supplying anything at all (including `enabled: false`) ran
 * finalisation and hard-failed on the first missing required child.
 *
 * These tests pin the resolved configuration so every key the extension reads is always present.
 *
 * @author Daniel West <daniel@silverback.is>
 */
#[CoversClass(Configuration::class)]
class ConfigurationTest extends TestCase
{
    /**
     * The smallest configuration an application can supply. Everything the bundle does not force
     * the application to declare must resolve to a usable default from here.
     */
    private static function minimalConfig(): array
    {
        return [
            'refresh_token' => [
                'handler_id' => 'silverback.api_components.refresh_token.storage.doctrine',
                'cookie_name' => 'api_components',
                'ttl' => 604800,
                'database_user_provider' => 'database',
                'options' => ['class' => 'App\\Entity\\RefreshToken'],
            ],
            'website_name' => 'Test Website',
            'user' => ['class_name' => 'App\Entity\User'],
            'publishable' => ['permission' => "is_granted('ROLE_ADMIN')"],
        ];
    }

    #[DataProvider('requiredNodeProvider')]
    public function test_omitting_a_required_node_is_rejected_by_name(string $node): void
    {
        $config = self::minimalConfig();
        unset($config[$node]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(\sprintf('The child config "%s" under "silverback_api_components" must be configured.', $node));

        $this->process($config);
    }

    public static function requiredNodeProvider(): iterable
    {
        yield 'user' => ['user'];
        yield 'refresh_token' => ['refresh_token'];
        yield 'publishable' => ['publishable'];
    }

    public function test_a_required_node_still_resolves_its_defaulted_children(): void
    {
        $user = $this->process(self::minimalConfig())['user'];

        self::assertArrayHasKey('email_verification', $user);
        self::assertSame(86400, $user['password_reset']['repeat_ttl_seconds']);
    }

    public function test_the_doctrine_refresh_token_storage_demands_an_entity_class(): void
    {
        $config = self::minimalConfig();
        unset($config['refresh_token']['options']);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('silverback_api_components.refresh_token.options.class');

        $this->process($config);
    }

    public function test_a_custom_refresh_token_storage_needs_no_entity_class(): void
    {
        $config = self::minimalConfig();
        $config['refresh_token']['handler_id'] = 'app.refresh_token.storage';
        unset($config['refresh_token']['options']);

        self::assertSame([], $this->process($config)['refresh_token']['options']);
    }

    public function test_an_empty_user_class_name_is_rejected(): void
    {
        $config = self::minimalConfig();
        $config['user']['class_name'] = '';

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('cannot contain an empty value');

        $this->process($config);
    }

    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }

    public function test_email_verification_resolves_every_key_the_extension_reads(): void
    {
        $emailVerification = $this->process(self::minimalConfig())['user']['email_verification'];

        self::assertArrayHasKey('deny_unverified_login', $emailVerification);
        self::assertArrayHasKey('default_value', $emailVerification);
        self::assertArrayHasKey('verify_on_register', $emailVerification);
        self::assertArrayHasKey('verify_on_change', $emailVerification);
    }

    public function test_email_verification_defaults_to_doing_nothing(): void
    {
        $emailVerification = $this->process(self::minimalConfig())['user']['email_verification'];

        self::assertFalse($emailVerification['verify_on_register']);
        self::assertFalse($emailVerification['verify_on_change']);
        self::assertFalse($emailVerification['deny_unverified_login']);
        self::assertFalse($emailVerification['default_value']);
    }

    #[DataProvider('emailNodeProvider')]
    public function test_email_redirect_keys_are_always_resolved(string $node): void
    {
        $email = $this->process(self::minimalConfig())['user'][$node]['email'];

        self::assertArrayHasKey('default_redirect_path', $email);
        self::assertArrayHasKey('redirect_path_query', $email);
        self::assertNull($email['default_redirect_path']);
        self::assertNull($email['redirect_path_query']);
        self::assertNotEmpty($email['subject']);
    }

    public static function emailNodeProvider(): iterable
    {
        yield 'email verification' => ['email_verification'];
        yield 'new email confirmation' => ['new_email_confirmation'];
        yield 'password reset' => ['password_reset'];
    }

    public function test_each_email_flow_has_its_own_repeat_throttle_by_default(): void
    {
        $user = $this->process(self::minimalConfig())['user'];

        self::assertSame(86400, $user['password_reset']['repeat_ttl_seconds']);
        self::assertSame(300, $user['new_email_confirmation']['repeat_ttl_seconds']);
        self::assertSame(300, $user['email_verification']['repeat_ttl_seconds']);
    }

    public function test_each_email_flow_repeat_throttle_can_be_configured(): void
    {
        $config = self::minimalConfig();
        $config['user']['password_reset'] = ['repeat_ttl_seconds' => 60];
        $config['user']['new_email_confirmation'] = ['repeat_ttl_seconds' => 120];
        $config['user']['email_verification'] = ['repeat_ttl_seconds' => 180];

        $user = $this->process($config)['user'];

        self::assertSame(60, $user['password_reset']['repeat_ttl_seconds']);
        self::assertSame(120, $user['new_email_confirmation']['repeat_ttl_seconds']);
        self::assertSame(180, $user['email_verification']['repeat_ttl_seconds']);
        self::assertSame(86400, $user['new_email_confirmation']['request_timeout_seconds']);
        self::assertTrue($user['email_verification']['enabled']);
    }

    public function test_a_disabled_email_verification_still_resolves_its_repeat_throttle(): void
    {
        $config = self::minimalConfig();
        $config['user']['email_verification'] = false;

        self::assertSame(300, $this->process($config)['user']['email_verification']['repeat_ttl_seconds']);
    }

    public function test_email_verification_can_be_disabled(): void
    {
        $config = self::minimalConfig();
        $config['user']['email_verification'] = false;

        self::assertFalse($this->process($config)['user']['email_verification']['enabled']);
    }

    public function test_email_verification_can_be_configured_partially(): void
    {
        $config = self::minimalConfig();
        $config['user']['email_verification'] = ['deny_unverified_login' => true];

        $emailVerification = $this->process($config)['user']['email_verification'];

        self::assertTrue($emailVerification['deny_unverified_login']);
        self::assertFalse($emailVerification['verify_on_register']);
    }

    public function test_an_explicit_email_verification_config_is_preserved(): void
    {
        $config = self::minimalConfig();
        $config['user']['email_verification'] = [
            'default_value' => false,
            'verify_on_register' => true,
            'verify_on_change' => true,
            'deny_unverified_login' => true,
            'email' => [
                'redirect_path_query' => 'email_redirect',
                'default_redirect_path' => '/verify-email/{{ username }}/{{ token }}',
                'subject' => 'Verify please',
            ],
        ];

        $emailVerification = $this->process($config)['user']['email_verification'];

        self::assertTrue($emailVerification['enabled']);
        self::assertFalse($emailVerification['default_value']);
        self::assertTrue($emailVerification['verify_on_register']);
        self::assertTrue($emailVerification['verify_on_change']);
        self::assertTrue($emailVerification['deny_unverified_login']);
        self::assertSame('/verify-email/{{ username }}/{{ token }}', $emailVerification['email']['default_redirect_path']);
        self::assertSame('email_redirect', $emailVerification['email']['redirect_path_query']);
        self::assertSame('Verify please', $emailVerification['email']['subject']);
    }

    #[DataProvider('verificationTriggerProvider')]
    public function test_requesting_verification_emails_without_a_redirect_target_is_rejected(string $trigger): void
    {
        $config = self::minimalConfig();
        $config['user']['email_verification'] = [$trigger => true];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('user.email_verification.email.default_redirect_path');

        $this->process($config);
    }

    public static function verificationTriggerProvider(): iterable
    {
        yield 'on register' => ['verify_on_register'];
        yield 'on change' => ['verify_on_change'];
    }

    public function test_requesting_verification_emails_with_only_a_redirect_query_key_is_allowed(): void
    {
        $config = self::minimalConfig();
        $config['user']['email_verification'] = [
            'verify_on_register' => true,
            'email' => ['redirect_path_query' => 'email_redirect'],
        ];

        $emailVerification = $this->process($config)['user']['email_verification'];

        self::assertSame('email_redirect', $emailVerification['email']['redirect_path_query']);
        self::assertNull($emailVerification['email']['default_redirect_path']);
    }

    public function test_a_disabled_email_verification_never_demands_a_redirect_target(): void
    {
        $config = self::minimalConfig();
        $config['user']['email_verification'] = ['enabled' => false, 'verify_on_register' => true];

        self::assertFalse($this->process($config)['user']['email_verification']['enabled']);
    }

    public function test_email_links_allow_no_origin_and_have_no_default_origin_by_default(): void
    {
        $emailLinks = $this->process(self::minimalConfig())['user']['email_links'];

        self::assertSame([], $emailLinks['allowed_origins']);
        self::assertNull($emailLinks['default_origin']);
    }

    public function test_email_links_accept_allowed_origin_patterns_and_a_default_origin(): void
    {
        $config = self::minimalConfig();
        $config['user']['email_links'] = [
            'allowed_origins' => ['https://www\.example\.com', 'https://[a-z]+\.example\.com(:8443)?'],
            'default_origin' => 'https://www.example.com',
        ];

        $emailLinks = $this->process($config)['user']['email_links'];

        self::assertSame(['https://www\.example\.com', 'https://[a-z]+\.example\.com(:8443)?'], $emailLinks['allowed_origins']);
        self::assertSame('https://www.example.com', $emailLinks['default_origin']);
    }

    public function test_an_allowed_origin_that_is_not_a_regular_expression_is_rejected(): void
    {
        $config = self::minimalConfig();
        $config['user']['email_links'] = ['allowed_origins' => ['https://(www']];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('is not a valid regular expression');

        $this->process($config);
    }

    #[DataProvider('invalidDefaultOriginProvider')]
    public function test_a_default_origin_that_is_not_an_origin_is_rejected(string $defaultOrigin): void
    {
        $config = self::minimalConfig();
        $config['user']['email_links'] = ['default_origin' => $defaultOrigin];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must be a scheme and host with an optional port');

        $this->process($config);
    }

    public static function invalidDefaultOriginProvider(): iterable
    {
        yield 'no scheme' => ['www.example.com'];
        yield 'not http' => ['ftp://www.example.com'];
        yield 'with a path' => ['https://www.example.com/app'];
        yield 'with a query' => ['https://www.example.com?a=b'];
    }

    public function test_email_link_values_from_environment_variables_are_left_to_run_time(): void
    {
        BaseNode::setPlaceholderUniquePrefix('env_email_links_test');
        try {
            $config = self::minimalConfig();
            $config['user']['email_links'] = [
                'allowed_origins' => ['env_email_links_test_ALLOWED'],
                'default_origin' => 'env_email_links_test_DEFAULT',
            ];

            $emailLinks = $this->process($config)['user']['email_links'];
        } finally {
            BaseNode::resetPlaceholders();
        }

        self::assertSame(['env_email_links_test_ALLOWED'], $emailLinks['allowed_origins']);
        self::assertSame('env_email_links_test_DEFAULT', $emailLinks['default_origin']);
    }

    public function test_an_empty_default_origin_is_accepted_as_no_default(): void
    {
        $config = self::minimalConfig();
        $config['user']['email_links'] = ['default_origin' => ''];

        self::assertSame('', $this->process($config)['user']['email_links']['default_origin']);
    }

    public function test_unresolved_email_link_parameters_seen_while_prepending_are_not_validated(): void
    {
        $config = self::minimalConfig();
        $config['user']['email_links'] = [
            'allowed_origins' => ['%env(ALLOWED_ORIGIN)%'],
            'default_origin' => '%env(default::DEFAULT_ORIGIN)%',
        ];

        $emailLinks = $this->process($config)['user']['email_links'];

        self::assertSame(['%env(ALLOWED_ORIGIN)%'], $emailLinks['allowed_origins']);
        self::assertSame('%env(default::DEFAULT_ORIGIN)%', $emailLinks['default_origin']);
    }

    public function test_orphaned_resource_notifications_have_no_recipients_by_default(): void
    {
        self::assertSame(
            [
                'recipients' => [],
                'admin_page_path' => '/_cwa/orphaned',
                'subject' => 'Orphaned resources changed on {{ website_name }}',
            ],
            $this->process(self::minimalConfig())['orphaned_resources']['notify']
        );
    }

    public function test_orphaned_resource_notifications_can_be_configured(): void
    {
        $config = self::minimalConfig();
        $config['orphaned_resources']['notify'] = [
            'recipients' => ['admin@example.com', 'ops@example.com'],
            'admin_page_path' => '/admin/orphans',
            'subject' => 'Orphans',
        ];

        self::assertSame(
            ['recipients' => ['admin@example.com', 'ops@example.com'], 'admin_page_path' => '/admin/orphans', 'subject' => 'Orphans'],
            $this->process($config)['orphaned_resources']['notify']
        );
    }

    public function test_an_orphaned_resource_notification_recipient_that_is_not_an_email_address_is_rejected(): void
    {
        $config = self::minimalConfig();
        $config['orphaned_resources']['notify']['recipients'] = ['admin@example.com', 'not an email'];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('not an email');
        $this->process($config);
    }

    public function test_orphaned_resource_notification_recipients_from_environment_variables_are_left_to_run_time(): void
    {
        $config = self::minimalConfig();
        $config['orphaned_resources']['notify']['recipients'] = ['%env(ORPHAN_RECIPIENT)%'];

        self::assertSame(['%env(ORPHAN_RECIPIENT)%'], $this->process($config)['orphaned_resources']['notify']['recipients']);
    }

    public function test_orphaned_resource_notification_recipients_may_be_one_comma_separated_string(): void
    {
        $config = self::minimalConfig();
        $config['orphaned_resources']['notify']['recipients'] = 'admin@example.com, ops@example.com';

        self::assertSame('admin@example.com, ops@example.com', $this->process($config)['orphaned_resources']['notify']['recipients']);
    }

    public function test_an_invalid_address_in_a_comma_separated_recipients_string_is_rejected(): void
    {
        $config = self::minimalConfig();
        $config['orphaned_resources']['notify']['recipients'] = 'admin@example.com,not an email';

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('not an email');
        $this->process($config);
    }

    public function test_orphaned_resource_notification_recipients_may_be_an_unresolved_parameter_while_prepending(): void
    {
        $config = self::minimalConfig();
        $config['orphaned_resources']['notify']['recipients'] = '%env(ORPHAN_RECIPIENTS)%';

        self::assertSame('%env(ORPHAN_RECIPIENTS)%', $this->process($config)['orphaned_resources']['notify']['recipients']);
    }

    public function test_orphaned_resource_notification_recipients_from_an_environment_variable_are_left_to_run_time(): void
    {
        BaseNode::setPlaceholderUniquePrefix('env_orphan_test');
        try {
            $config = self::minimalConfig();
            $config['orphaned_resources']['notify']['recipients'] = 'env_orphan_test_RECIPIENTS';

            $recipients = $this->process($config)['orphaned_resources']['notify']['recipients'];
        } finally {
            BaseNode::resetPlaceholders();
        }

        self::assertSame('env_orphan_test_RECIPIENTS', $recipients);
    }

    #[DataProvider('invalidRecipientsProvider')]
    public function test_orphaned_resource_notification_recipients_that_are_not_strings_are_rejected(mixed $recipients): void
    {
        $config = self::minimalConfig();
        $config['orphaned_resources']['notify']['recipients'] = $recipients;

        $this->expectException(InvalidConfigurationException::class);
        $this->process($config);
    }

    public static function invalidRecipientsProvider(): iterable
    {
        yield 'a number' => [5];
        yield 'a list holding a list' => [[['admin@example.com']]];
    }

    public function test_an_empty_orphaned_resource_notification_subject_is_rejected(): void
    {
        $config = self::minimalConfig();
        $config['orphaned_resources']['notify']['subject'] = '';

        $this->expectException(InvalidConfigurationException::class);
        $this->process($config);
    }

    public function test_an_empty_orphaned_resource_admin_page_path_is_rejected(): void
    {
        $config = self::minimalConfig();
        $config['orphaned_resources']['notify']['admin_page_path'] = '';

        $this->expectException(InvalidConfigurationException::class);
        $this->process($config);
    }
}
