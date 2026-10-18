<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Publishable;

use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableWithValidation;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\PublishableTrait;

final class PublishableSoftValidationTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use JsonTrait;
    use PublishableTrait;

    private const array RESOURCE_DATA = [
        'invalid_draft' => ['name' => ''],
        'valid_draft' => ['name' => 'John Doe'],
        'valid_published' => ['name' => 'John Doe', 'description' => 'nobody'],
    ];

    private const string INVALID = 'publishable_invalid.schema.json';

    private const string VALID = 'publishable_valid.schema.json';

    private const string ERRORS = 'validation_errors_object.schema.json';

    #[DataProvider('validatePublishedQueryStrings')]
    public function test_a_draft_resource_fetched_with_draft_access_has_the_valid_to_publish_header_and_violations(string $queryString): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $draft = self::createPublishable(null, new DummyPublishableWithValidation());

        self::requestJsonLd($client, 'GET', self::iriOf($draft) . '?' . $queryString);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonMatchesSchemaFile(self::INVALID);
        self::assertResponseHeaderSame('valid-to-publish', '0');
        self::assertJsonNodeExists('_metadata.violations.violations[0]');
    }

    #[DataProvider('validatePublishedQueryStrings')]
    public function test_a_user_without_draft_access_does_not_see_violations_on_a_published_resource_which_is_no_longer_valid_to_publish(string $queryString): void
    {
        $client = static::createClient();
        $published = self::createPublishable(new \DateTimeImmutable('1970-01-01 00:00:00'), new DummyPublishableWithValidation());

        self::requestJsonLd($client, 'GET', self::iriOf($published) . '?' . $queryString);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonMatchesSchemaFile(self::VALID);
        self::assertResponseNotHasHeader('valid-to-publish');
        self::assertJsonNodeNotExists('_metadata.violations');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validatePublishedQueryStrings(): iterable
    {
        yield 'no query string' => [''];
        yield 'validate_published=true' => ['validate_published=true'];
        yield 'validate_published=false' => ['validate_published=false'];
    }

    #[DataProvider('draftUpdates')]
    public function test_updating_a_draft_resource_sets_a_header_saying_whether_it_would_pass_validation_to_publish(string $data, string $validToPublish, string $schema): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $draft = self::createPublishable(null, new DummyPublishableWithValidation());

        self::requestJsonLd($client, 'PATCH', self::iriOf($draft), self::RESOURCE_DATA[$data]);

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('valid-to-publish', $validToPublish);
        self::assertJsonMatchesSchemaFile($schema);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function draftUpdates(): iterable
    {
        yield 'valid for a draft' => ['valid_draft', '0', self::INVALID];
        yield 'valid to publish' => ['valid_published', '1', self::VALID];
    }

    #[DataProvider('softDraftUpdates')]
    public function test_updating_a_draft_resource_with_data_valid_for_a_draft_or_to_publish(string $data, int $status, string $validToPublish, string $queryString, string $schema): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $draft = self::createPublishable(null, new DummyPublishableWithValidation());

        self::requestJsonLd($client, 'PATCH', self::iriOf($draft) . '?' . $queryString, self::body(null, $data));

        self::assertResponseStatusCodeSame($status);
        self::assertResponseHeaderSame('valid-to-publish', $validToPublish);
        self::assertJsonMatchesSchemaFile($schema);
    }

    /**
     * @return iterable<string, array{string, int, string, string, string}>
     */
    public static function softDraftUpdates(): iterable
    {
        yield 'ok for a draft but not to publish' => ['valid_draft', 200, '0', 'validate_published=false', self::INVALID];
        yield 'valid to publish when ready, validate_published=false' => ['valid_published', 200, '1', 'validate_published=false', self::VALID];
        yield 'valid to publish when ready, validate_published=true' => ['valid_published', 200, '1', 'validate_published=true', self::VALID];
    }

    #[DataProvider('hardFailingDraftUpdates')]
    public function test_updating_a_draft_resource_hard_fails_with_validation_errors_and_no_metadata_violations(?string $publishedAt, string $data, string $queryString): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $draft = self::createPublishable(null, new DummyPublishableWithValidation());

        self::requestJsonLd($client, 'PATCH', self::iriOf($draft) . '?' . $queryString, self::body($publishedAt, $data));

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('valid-to-publish', '0');
        self::assertJsonMatchesSchemaFile(self::ERRORS);
        self::assertJsonNodeNotExists('_metadata.violations.violations[0]');
    }

    /**
     * @return iterable<string, array{?string, string, string}>
     */
    public static function hardFailingDraftUpdates(): iterable
    {
        yield 'draft, invalid draft, validate_published=false' => [null, 'invalid_draft', 'validate_published=false'];
        yield 'draft, invalid draft, validate_published=true' => [null, 'invalid_draft', 'validate_published=true'];
        yield 'draft, valid draft, validate_published=true' => [null, 'valid_draft', 'validate_published=true'];
        yield 'now, invalid draft, validate_published=false' => ['now', 'invalid_draft', 'validate_published=false'];
        yield 'now, invalid draft, validate_published=true' => ['now', 'invalid_draft', 'validate_published=true'];
        yield 'now, valid draft, validate_published=true' => ['now', 'valid_draft', 'validate_published=true'];
        yield 'now, valid draft, validate_published=false' => ['now', 'valid_draft', 'validate_published=false'];
    }

    #[DataProvider('validatePublishedQueryStringsSet')]
    public function test_updating_a_draft_to_published_is_unaffected_by_the_query_string(string $queryString): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $draft = self::createPublishable(null, new DummyPublishableWithValidation());

        self::requestJsonLd($client, 'PATCH', self::iriOf($draft) . '?' . $queryString, self::body('now', 'valid_published'));

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('valid-to-publish', '1');
        self::assertJsonMatchesSchemaFile(self::VALID);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validatePublishedQueryStringsSet(): iterable
    {
        yield 'validate_published=true' => ['validate_published=true'];
        yield 'validate_published=false' => ['validate_published=false'];
    }

    public function test_updating_a_published_resource_with_validate_published_false_and_published_true_still_applies_published_validation(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $published = self::createPublishable(new \DateTimeImmutable('1970-12-31T23:59:59+00:00'), new DummyPublishableWithValidation());

        self::requestJsonLd($client, 'PATCH', self::iriOf($published) . '?validate_published=false&published=true', '{"description": ""}');

        self::assertResponseStatusCodeSame(422);
        self::assertJsonMatchesSchemaFile(self::ERRORS);
    }

    #[DataProvider('validDraftCreations')]
    public function test_creating_a_valid_draft_resource(string $data, string $validToPublish): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        self::requestJsonLd($client, 'POST', '/dummy_publishable_with_validations', self::body(null, $data));

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('valid-to-publish', $validToPublish);
        self::assertJsonMatchesSchemaFile('publishable.schema.json');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validDraftCreations(): iterable
    {
        yield 'valid for a draft' => ['valid_draft', '0'];
        yield 'valid to publish' => ['valid_published', '1'];
    }

    #[DataProvider('publishedValidationCreations')]
    public function test_creating_a_resource_validated_against_its_published_state(?string $publishedAt, string $data, int $status, string $queryString, string $schema, string $validToPublish): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        self::requestJsonLd($client, 'POST', '/dummy_publishable_with_validations?' . $queryString, self::body($publishedAt, $data));

        self::assertResponseStatusCodeSame($status);
        self::assertResponseHeaderSame('valid-to-publish', $validToPublish);
        self::assertJsonMatchesSchemaFile($schema);
    }

    #[DataProvider('customGroupDraftCreations')]
    public function test_custom_validation_groups_create_a_draft_resource(string $data, string $validToPublish, string $schema): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        self::requestJsonLd($client, 'POST', '/dummy_publishable_with_validation_custom_groups', self::body(null, $data));

        self::assertJsonMatchesSchemaFile('publishable.schema.json');
        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('valid-to-publish', $validToPublish);
        self::assertJsonMatchesSchemaFile($schema);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function customGroupDraftCreations(): iterable
    {
        yield 'valid to publish' => ['valid_published', '1', self::VALID];
        yield 'valid for a draft' => ['valid_draft', '0', self::INVALID];
    }

    #[DataProvider('publishedValidationCreations')]
    public function test_custom_validation_groups_validate_creating_a_published_resource(?string $publishedAt, string $data, int $status, string $queryString, string $schema, string $validToPublish): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        self::requestJsonLd($client, 'POST', '/dummy_publishable_with_validation_custom_groups?' . $queryString, self::body($publishedAt, $data));

        self::assertResponseStatusCodeSame($status);
        self::assertResponseHeaderSame('valid-to-publish', $validToPublish);
        self::assertJsonMatchesSchemaFile($schema);
    }

    /**
     * @return iterable<string, array{?string, string, int, string, string, string}>
     */
    public static function publishedValidationCreations(): iterable
    {
        yield 'draft, valid draft, validate_published=true' => [null, 'valid_draft', 422, 'validate_published=true', self::ERRORS, '0'];
        yield 'draft, invalid draft, validate_published=true' => [null, 'invalid_draft', 422, 'validate_published=true', self::ERRORS, '0'];
        yield 'now, invalid draft, validate_published=true' => ['now', 'invalid_draft', 422, 'validate_published=true', self::ERRORS, '0'];
        yield 'now, valid draft, validate_published=true' => ['now', 'valid_draft', 422, 'validate_published=true', self::ERRORS, '0'];
        yield 'now, valid to publish, validate_published=true' => ['now', 'valid_published', 201, 'validate_published=true', self::VALID, '1'];
        yield 'draft, invalid draft, validate_published=false' => [null, 'invalid_draft', 422, 'validate_published=false', self::ERRORS, '0'];
        yield 'now, invalid draft, validate_published=false' => ['now', 'invalid_draft', 422, 'validate_published=false', self::ERRORS, '0'];
        yield 'now, valid draft, validate_published=false' => ['now', 'valid_draft', 422, 'validate_published=false', self::ERRORS, '0'];
        yield 'now, valid to publish, validate_published=false' => ['now', 'valid_published', 201, 'validate_published=false', self::VALID, '1'];
    }

    /**
     * @return array<string, mixed>
     */
    private static function body(?string $publishedAt, string $data): array
    {
        return ['publishedAt' => 'now' === $publishedAt ? gmdate('Y-m-d\TH:i:s+00:00') : $publishedAt] + self::RESOURCE_DATA[$data];
    }
}
