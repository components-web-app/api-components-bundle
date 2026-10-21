<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Realtime;

use ApiPlatform\Validator\Exception\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Realtime\RealtimeEventRequest;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RealtimeEventRequestTest extends TestCase
{
    private const string CLIENT_ID = '0b9f7a3e-2c4d-4e5f-8a6b-7c8d9e0f1a2b';

    public function test_every_field_is_read(): void
    {
        $request = RealtimeEventRequest::fromContent('{"clientId": "' . self::CLIENT_ID . '", "event": "tiptap:selection", "iri": "/component/dummy_components/1", "data": {"from": 3, "to": 9}}');

        self::assertSame(self::CLIENT_ID, $request->clientId);
        self::assertSame('tiptap:selection', $request->event);
        self::assertSame('/component/dummy_components/1', $request->iri);
        self::assertSame('{"from":3,"to":9}', json_encode($request->data, \JSON_THROW_ON_ERROR));
    }

    public function test_iri_and_data_are_optional_and_null_when_absent(): void
    {
        $request = RealtimeEventRequest::fromContent('{"clientId": "' . self::CLIENT_ID . '", "event": "presence"}');

        self::assertSame(self::CLIENT_ID, $request->clientId);
        self::assertSame('presence', $request->event);
        self::assertNull($request->iri);
        self::assertNull($request->data);
    }

    public function test_data_is_passed_through_as_sent_so_an_empty_object_stays_an_object(): void
    {
        $request = RealtimeEventRequest::fromContent('{"clientId": "' . self::CLIENT_ID . '", "event": "presence", "iri": null, "data": {"page": "/_/pages/1", "nested": {}, "list": []}}');

        self::assertNull($request->iri);
        self::assertSame('{"page":"\/_\/pages\/1","nested":{},"list":[]}', json_encode($request->data, \JSON_THROW_ON_ERROR));
    }

    public function test_data_that_is_not_an_object_is_not_validated(): void
    {
        self::assertSame('[1,"two"]', json_encode(RealtimeEventRequest::fromContent('{"clientId": "' . self::CLIENT_ID . '", "event": "presence", "data": [1, "two"]}')->data, \JSON_THROW_ON_ERROR));
        self::assertSame('"text"', json_encode(RealtimeEventRequest::fromContent('{"clientId": "' . self::CLIENT_ID . '", "event": "presence", "data": "text"}')->data, \JSON_THROW_ON_ERROR));
    }

    public function test_unknown_fields_are_ignored(): void
    {
        $request = RealtimeEventRequest::fromContent('{"clientId": "' . self::CLIENT_ID . '", "event": "presence", "user": {"id": "/users/forged"}}');

        self::assertSame('presence', $request->event);
    }

    public function test_an_uppercase_uuid_is_a_valid_client_id(): void
    {
        self::assertSame(strtoupper(self::CLIENT_ID), RealtimeEventRequest::fromContent('{"clientId": "' . strtoupper(self::CLIENT_ID) . '", "event": "presence"}')->clientId);
    }

    public function test_an_event_of_sixty_four_characters_is_accepted(): void
    {
        $event = 'a' . str_repeat('b', 63);

        self::assertSame($event, RealtimeEventRequest::fromContent('{"clientId": "' . self::CLIENT_ID . '", "event": "' . $event . '"}')->event);
    }

    public function test_a_body_of_exactly_the_maximum_size_is_accepted(): void
    {
        $content = self::bodyOfLength(RealtimeEventRequest::MAX_BYTES);

        self::assertSame(RealtimeEventRequest::MAX_BYTES, \strlen($content));
        self::assertSame('presence', RealtimeEventRequest::fromContent($content)->event);
    }

    public function test_a_body_over_the_maximum_size_is_refused_with_413_before_it_is_parsed(): void
    {
        foreach ([self::bodyOfLength(RealtimeEventRequest::MAX_BYTES + 1), str_repeat('x', RealtimeEventRequest::MAX_BYTES + 1)] as $content) {
            try {
                RealtimeEventRequest::fromContent($content);
                self::fail('The body should have been refused');
            } catch (HttpException $exception) {
                self::assertSame(413, $exception->getStatusCode());
                self::assertSame(RealtimeEventRequest::TOO_LARGE_MESSAGE, $exception->getMessage());
            }
        }
    }

    public function test_a_client_id_is_a_uuid_in_either_case_and_nothing_around_it(): void
    {
        self::assertTrue(RealtimeEventRequest::isClientId(self::CLIENT_ID));
        self::assertTrue(RealtimeEventRequest::isClientId(strtoupper(self::CLIENT_ID)));
        self::assertFalse(RealtimeEventRequest::isClientId(self::CLIENT_ID . "\n"));
        self::assertFalse(RealtimeEventRequest::isClientId(' ' . self::CLIENT_ID));
        self::assertFalse(RealtimeEventRequest::isClientId('0b9f7a3e2c4d4e5f8a6b7c8d9e0f1a2b'));
        self::assertFalse(RealtimeEventRequest::isClientId(''));
    }

    public function test_the_maximum_size_is_sixteen_kilobytes(): void
    {
        self::assertSame(16384, RealtimeEventRequest::MAX_BYTES);
    }

    /**
     * @return iterable<string, array{string, list<array{string, string}>}>
     */
    public static function invalidBodies(): iterable
    {
        $id = self::CLIENT_ID;

        yield 'an empty body' => ['', [['', RealtimeEventRequest::INVALID_BODY_MESSAGE]]];
        yield 'malformed json' => ['{"event": ', [['', RealtimeEventRequest::INVALID_BODY_MESSAGE]]];
        yield 'null' => ['null', [['', RealtimeEventRequest::INVALID_BODY_MESSAGE]]];
        yield 'a list' => ['[]', [['', RealtimeEventRequest::INVALID_BODY_MESSAGE]]];
        yield 'a string' => ['"presence"', [['', RealtimeEventRequest::INVALID_BODY_MESSAGE]]];
        yield 'an empty object' => ['{}', [['clientId', RealtimeEventRequest::CLIENT_ID_MESSAGE], ['event', RealtimeEventRequest::EVENT_MESSAGE]]];
        yield 'a client id that is not a uuid' => ['{"clientId": "tab-1", "event": "presence"}', [['clientId', RealtimeEventRequest::CLIENT_ID_MESSAGE]]];
        yield 'a client id with a trailing newline' => ['{"clientId": "' . $id . '\n", "event": "presence"}', [['clientId', RealtimeEventRequest::CLIENT_ID_MESSAGE]]];
        yield 'a client id in braces' => ['{"clientId": "{' . $id . '}", "event": "presence"}', [['clientId', RealtimeEventRequest::CLIENT_ID_MESSAGE]]];
        yield 'a client id that is a number' => ['{"clientId": 1, "event": "presence"}', [['clientId', RealtimeEventRequest::CLIENT_ID_MESSAGE]]];
        yield 'a missing event' => ['{"clientId": "' . $id . '"}', [['event', RealtimeEventRequest::EVENT_MESSAGE]]];
        yield 'an empty event' => ['{"clientId": "' . $id . '", "event": ""}', [['event', RealtimeEventRequest::EVENT_MESSAGE]]];
        yield 'an event starting with a digit' => ['{"clientId": "' . $id . '", "event": "1presence"}', [['event', RealtimeEventRequest::EVENT_MESSAGE]]];
        yield 'an uppercase event' => ['{"clientId": "' . $id . '", "event": "Presence"}', [['event', RealtimeEventRequest::EVENT_MESSAGE]]];
        yield 'an event with a space' => ['{"clientId": "' . $id . '", "event": "pre sence"}', [['event', RealtimeEventRequest::EVENT_MESSAGE]]];
        yield 'an event with a trailing newline' => ['{"clientId": "' . $id . '", "event": "presence\n"}', [['event', RealtimeEventRequest::EVENT_MESSAGE]]];
        yield 'an event of sixty five characters' => ['{"clientId": "' . $id . '", "event": "a' . str_repeat('b', 64) . '"}', [['event', RealtimeEventRequest::EVENT_MESSAGE]]];
        yield 'an event that is not a string' => ['{"clientId": "' . $id . '", "event": ["presence"]}', [['event', RealtimeEventRequest::EVENT_MESSAGE]]];
        yield 'an iri that is not a string' => ['{"clientId": "' . $id . '", "event": "presence", "iri": 1}', [['iri', RealtimeEventRequest::IRI_MESSAGE]]];
        yield 'data with a number too large to encode again' => ['{"clientId": "' . $id . '", "event": "presence", "data": {"x": 1e999}}', [['data', RealtimeEventRequest::DATA_MESSAGE]]];
        yield 'data that is a negative number too large to encode again' => ['{"clientId": "' . $id . '", "event": "presence", "data": [-1e999]}', [['data', RealtimeEventRequest::DATA_MESSAGE]]];
        yield 'every violation at once' => ['{"clientId": "x", "event": "X", "iri": {}, "data": 1e999}', [
            ['clientId', RealtimeEventRequest::CLIENT_ID_MESSAGE],
            ['event', RealtimeEventRequest::EVENT_MESSAGE],
            ['iri', RealtimeEventRequest::IRI_MESSAGE],
            ['data', RealtimeEventRequest::DATA_MESSAGE],
        ]];
    }

    /**
     * @param list<array{string, string}> $expected
     */
    #[DataProvider('invalidBodies')]
    public function test_an_invalid_body_is_refused_with_a_violation_per_field(string $content, array $expected): void
    {
        try {
            RealtimeEventRequest::fromContent($content);
            self::fail('The body should have been refused');
        } catch (ValidationException $exception) {
            $violations = [];
            foreach ($exception->getConstraintViolationList() as $violation) {
                $violations[] = [$violation->getPropertyPath(), (string) $violation->getMessage()];
            }
            self::assertSame($expected, $violations);
        }
    }

    private static function bodyOfLength(int $length): string
    {
        $prefix = '{"clientId": "' . self::CLIENT_ID . '", "event": "presence", "data": "';
        $suffix = '"}';

        return $prefix . str_repeat('x', $length - \strlen($prefix) - \strlen($suffix)) . $suffix;
    }
}
