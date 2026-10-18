<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Form;

use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Form\NestedType;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Form\TestRepeatedType;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Form\TestType;
use Silverback\ApiComponentsBundle\Tests\Functional\User\UserTrait;

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

    public function test_i_can_send_a_valid_field_for_validation_of_one_of_the_children_in_a_collection_type(): void
    {
        self::patchNestedForm(['children' => [new \stdClass(), ['name' => 'John Smith']]], 200);
    }

    public function test_i_can_send_null_children_in_place_of_an_empty_object_and_validation_will_still_pass_only_for_the_submitted_fields(): void
    {
        self::patchNestedForm(['children' => [null, ['name' => 'John Smith']]], 200);
    }

    public function test_i_can_send_an_invalid_field_for_validation_of_one_of_the_children_in_a_collection_type(): void
    {
        self::patchNestedForm(['children' => [new \stdClass(), ['name' => '']]], 422);
    }

    public function test_i_can_validate_a_valid_field_that_is_a_collection_type_with_a_simple_field(): void
    {
        self::patchNestedForm(['text_children' => ['hello']], 200);
    }

    public function test_i_can_validate_a_valid_field_that_is_a_collection_type_with_multiple_simple_field(): void
    {
        self::patchNestedForm(['text_children' => ['hello', 'another']], 200);
    }

    public function test_i_can_validate_an_invalid_field_that_is_a_collection_type_with_a_simple_field(): void
    {
        self::patchNestedForm(['text_children' => ['1']], 422);
    }

    public function test_validate_repeated_field_valid(): void
    {
        self::patchRepeatedForm('something', 200);
    }

    public function test_validate_repeated_field_invalid(): void
    {
        self::patchRepeatedForm('no_same', 422);
    }

    public function test_each_text_children_should_have_a_minimum_length_of_1_post_invalid_form(): void
    {
        self::postNestedForm(['children' => [['name' => 'A name']], 'text_children' => ['1']], 422);
    }

    public function test_children_is_required_post_an_invalid_form(): void
    {
        self::postNestedForm(['children' => [], 'text_children' => ['with minimum length']], 422);
    }

    public function test_post_a_valid_form(): void
    {
        self::postNestedForm(['children' => [['name' => 'A name']], 'text_children' => ['with minimum length']], 201);
    }

    /**
     * @param array<string, mixed> $nested
     */
    private static function patchNestedForm(array $nested, int $status): void
    {
        $client = static::createClient();
        $formIri = self::createForm(NestedType::class);

        self::requestJsonLd($client, 'PATCH', $formIri . '/submit', ['nested' => $nested]);

        self::assertResponseStatusCodeSame($status);
        self::assertJsonLdResponse();
        self::assertFormResponse();
    }

    private static function patchRepeatedForm(string $second, int $status): void
    {
        $client = static::createClient();
        $formIri = self::createForm(TestRepeatedType::class);

        self::requestJsonLd($client, 'PATCH', $formIri . '/submit', ['test_repeated' => ['repeat' => ['first' => 'something', 'second' => $second]]]);

        self::assertResponseStatusCodeSame($status);
        self::assertJsonLdResponse();
        self::assertFormResponse();
    }

    /**
     * @param array<string, mixed> $nested
     */
    private static function postNestedForm(array $nested, int $status): void
    {
        $client = static::createClient();
        $formIri = self::createForm(NestedType::class);

        self::requestJsonLd($client, 'POST', $formIri . '/submit', ['nested' => $nested]);

        self::assertResponseStatusCodeSame($status);
        self::assertJsonLdResponse();
        self::assertJsonMatchesSchemaFile('form.schema.json');
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
