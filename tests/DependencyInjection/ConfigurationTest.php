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
            ],
            'website_name' => 'Test Website',
            'user' => ['class_name' => 'App\Entity\User'],
            'publishable' => ['permission' => "is_granted('ROLE_ADMIN')"],
        ];
    }

    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }

    public function test_email_verification_resolves_every_key_the_extension_reads(): void
    {
        $emailVerification = $this->process(self::minimalConfig())['user']['email_verification'];

        // SilverbackApiComponentsExtension::setEmailVerificationArguments() reads all four.
        self::assertArrayHasKey('deny_unverified_login', $emailVerification);
        self::assertArrayHasKey('default_value', $emailVerification);
        self::assertArrayHasKey('verify_on_register', $emailVerification);
        self::assertArrayHasKey('verify_on_change', $emailVerification);
    }

    public function test_email_verification_defaults_to_doing_nothing(): void
    {
        $emailVerification = $this->process(self::minimalConfig())['user']['email_verification'];

        // An application that never mentions email verification must not start sending verification
        // emails it has given no redirect target for, and must not have its users locked out of
        // logging in.
        self::assertFalse($emailVerification['verify_on_register']);
        self::assertFalse($emailVerification['verify_on_change']);
        self::assertFalse($emailVerification['deny_unverified_login']);
        self::assertFalse($emailVerification['default_value']);
    }

    #[DataProvider('emailNodeProvider')]
    public function test_email_redirect_keys_are_always_resolved(string $node): void
    {
        // SilverbackApiComponentsExtension::setMailerServiceArguments() reads both keys for each of
        // these three nodes. The `email` node had no default of its own, so omitting the parent left
        // no `email` key at all.
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
        // AbstractUserEmailFactory::getTokenPath() throws when it has neither value, so this
        // combination can only ever fail at the point a user registers. Fail at compile time
        // instead.
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
}
