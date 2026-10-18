<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\User;

use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Form\NestedType;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Form\TestRepeatedType;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Form\TestType;

final class FormTest extends FunctionalTestCase
{
    use UserTrait;

    public function test_create_a_form_component_and_receive_serialized_form_views(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        self::requestJsonLd($client, 'POST', '/component/forms', ['formType' => TestType::class]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonLdResponse();
        self::assertJsonMatchesSchemaFile('form.schema.json');
    }

    #[DataProvider('singleFieldValidations')]
    public function test_i_can_validate_a_single_form_field(string $name, int $status): void
    {
        $client = static::createClient();
        $formIri = self::createForm(TestType::class);

        self::requestJsonLd($client, 'PATCH', $formIri . '/submit', ['test' => ['name' => $name]]);

        self::assertResponseStatusCodeSame($status);
        self::assertFormResponse();
    }

    public static function singleFieldValidations(): iterable
    {
        yield 'a valid name' => ['John Smith', 200];
        yield 'an empty name' => ['', 422];
    }

    public function test_the_id_in_the_patch_submit_response_is_the_canonical_form_iri_not_the_submit_endpoint(): void
    {
        $client = static::createClient();
        $formIri = self::createForm(TestType::class);

        self::requestJsonLd($client, 'PATCH', $formIri . '/submit', '{"test": {"name": "John Smith"}}');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('@id', $formIri);
    }

    public function test_the_id_in_the_post_submit_response_is_the_canonical_form_iri_not_the_submit_endpoint(): void
    {
        $client = static::createClient();
        $formIri = self::createForm(TestType::class);

        self::requestJsonLd($client, 'POST', $formIri . '/submit', '{"test": {"name": "John Smith", "company": "Silverback"}}');

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeSame('@id', $formIri);
    }

    public function test_a_post_submit_validates_a_required_field_the_request_omits(): void
    {
        $client = static::createClient();
        $formIri = self::createForm(TestType::class);

        self::requestJsonLd($client, 'POST', $formIri . '/submit', '{"test": {"name": "John Smith"}}');

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('formView.vars.valid', false);
        self::assertJsonNodeCount('formView.children[0].vars.errors', 0);
        self::assertJsonNodeSame('formView.children[1].vars.errors[0]', 'Please provide your company');
    }

    public function test_a_patch_submit_validates_only_the_fields_it_sends_and_never_succeeds(): void
    {
        $client = static::createClient();
        $formIri = self::createForm(TestType::class);

        self::requestJsonLd($client, 'PATCH', $formIri . '/submit', '{"test": {"name": "John Smith"}}');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('@type', 'Form');
        self::assertJsonNodeSame('formView.vars.valid', true);
        self::assertJsonNodeCount('formView.children[1].vars.errors', 0);
    }

    #[DataProvider('multipleFieldPatches')]
    public function test_i_send_a_patch_request_to_the_form_with_multiple_fields(string $name, string $company, int $status): void
    {
        $client = static::createClient();
        $formIri = self::createForm(TestType::class);

        self::requestJsonLd($client, 'PATCH', $formIri . '/submit', ['test' => ['name' => $name, 'company' => $company]]);

        self::assertResponseStatusCodeSame($status);
        self::assertFormResponse();
    }

    public static function multipleFieldPatches(): iterable
    {
        yield 'a name and a company' => ['John Smith', 'company', 200];
        yield 'a company only' => ['', 'company', 422];
        yield 'neither' => ['', '', 422];
    }

    public function test_i_send_a_patch_request_to_the_form_with_no_fields(): void
    {
        $client = static::createClient();
        $formIri = self::createForm(TestType::class);

        self::requestJsonLd($client, 'PATCH', $formIri . '/submit', '{"test": {}}');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonLdResponse();
        self::assertJsonMatchesSchemaFile('form.schema.json');
    }

    #[DataProvider('fieldPosts')]
    public function test_i_send_a_post_request_to_the_form_with_fields(string $name, string $company, int $status): void
    {
        $client = static::createClient();
        $formIri = self::createForm(TestType::class);

        self::requestJsonLd($client, 'POST', $formIri . '/submit', ['test' => ['name' => $name, 'company' => $company]]);

        self::assertResponseStatusCodeSame($status);
        self::lastJson();
        self::assertJsonMatchesSchemaFile('form.schema.json');
    }

    public static function fieldPosts(): iterable
    {
        yield 'a name and a company' => ['John Smith', 'company', 201];
        yield 'a company only' => ['', 'company', 422];
        yield 'neither' => ['', '', 422];
    }

    public function test_i_send_a_post_request_to_the_form_with_an_invalid_root_key(): void
    {
        $client = static::createClient();
        $formIri = self::createForm(TestType::class);

        self::requestJsonLd($client, 'POST', $formIri . '/submit', '{"invalid_root_key": {}}');

        self::assertResponseStatusCodeSame(422);
        self::lastJson();
        self::assertStringContainsString('application/problem+json', (string) self::getClient()->getResponse()->headers->get('content-type'));
        self::assertJsonContains(['description' => 'Form object key could not be found. Expected: <b>test</b>: { "input_name": "input_value" }']);
    }

    public function test_collection_type_fields_expose_allow_add_allow_delete_and_prototype_in_the_form_view(): void
    {
        $client = static::createClient();
        $formIri = self::createForm(NestedType::class);

        self::requestJsonLd($client, 'GET', $formIri);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('formView.children[0].vars.allow_add', true);
        self::assertJsonNodeSame('formView.children[0].vars.allow_delete', true);
        self::assertJsonNodeExists('formView.children[0].prototype');
        self::assertJsonNodeSame('formView.children[1].vars.allow_add', true);
        self::assertJsonNodeSame('formView.children[1].vars.allow_delete', true);
        self::assertJsonNodeExists('formView.children[1].prototype');
    }

    /**
     * @param array<string, mixed> $nested
     */
    #[DataProvider('nestedPatches')]
    public function test_a_patch_to_a_nested_form_validates_only_the_submitted_fields(array $nested, int $status): void
    {
        $client = static::createClient();
        $formIri = self::createForm(NestedType::class);

        self::requestJsonLd($client, 'PATCH', $formIri . '/submit', ['nested' => $nested]);

        self::assertResponseStatusCodeSame($status);
        self::assertJsonLdResponse();
        self::assertFormResponse();
    }

    public static function nestedPatches(): iterable
    {
        yield 'I can send a valid field for validation of one of the children in a CollectionType' => [['children' => [new \stdClass(), ['name' => 'John Smith']]], 200];
        yield 'I can send null children in place of an empty object and validation will still pass only for the submitted fields' => [['children' => [null, ['name' => 'John Smith']]], 200];
        yield 'I can send an invalid field for validation of one of the children in a CollectionType' => [['children' => [new \stdClass(), ['name' => '']]], 422];
        yield 'I can validate a valid field that is a collection type with a simple field' => [['text_children' => ['hello']], 200];
        yield 'I can validate a valid field that is a collection type with multiple simple field' => [['text_children' => ['hello', 'another']], 200];
        yield 'I can validate an invalid field that is a collection type with a simple field' => [['text_children' => ['1']], 422];
    }

    #[DataProvider('repeatedFieldPatches')]
    public function test_validate_repeated_field(string $second, int $status): void
    {
        $client = static::createClient();
        $formIri = self::createForm(TestRepeatedType::class);

        self::requestJsonLd($client, 'PATCH', $formIri . '/submit', ['test_repeated' => ['repeat' => ['first' => 'something', 'second' => $second]]]);

        self::assertResponseStatusCodeSame($status);
        self::assertJsonLdResponse();
        self::assertFormResponse();
    }

    public static function repeatedFieldPatches(): iterable
    {
        yield 'valid' => ['something', 200];
        yield 'invalid' => ['no_same', 422];
    }

    /**
     * @param array<string, mixed> $nested
     */
    #[DataProvider('nestedPosts')]
    public function test_a_post_to_a_nested_form_validates_the_collection_lengths(array $nested, int $status): void
    {
        $client = static::createClient();
        $formIri = self::createForm(NestedType::class);

        self::requestJsonLd($client, 'POST', $formIri . '/submit', ['nested' => $nested]);

        self::assertResponseStatusCodeSame($status);
        self::assertJsonLdResponse();
        self::assertJsonMatchesSchemaFile('form.schema.json');
    }

    public static function nestedPosts(): iterable
    {
        yield 'Each text_children should have a minimum length of 1 - post invalid form' => [['children' => [['name' => 'A name']], 'text_children' => ['1']], 422];
        yield 'Children is required - post an invalid form' => [['children' => [], 'text_children' => ['with minimum length']], 422];
        yield 'Post a valid form' => [['children' => [['name' => 'A name']], 'text_children' => ['with minimum length']], 201];
    }

    private static function assertJsonLdResponse(): void
    {
        self::lastJson();
        self::assertStringContainsString('application/ld+json', (string) self::getClient()->getResponse()->headers->get('content-type'));
    }

    private static function assertFormResponse(): void
    {
        self::lastJson();
        self::assertJsonMatchesSchemaFile('form.schema.json');
    }
}
