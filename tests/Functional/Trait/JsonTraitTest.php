<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Trait;

use PHPUnit\Framework\AssertionFailedError;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;

final class JsonTraitTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use JsonTrait;

    private const array JSON = [
        'member' => [['@id' => '/a', 'tags' => ['x', 'y']], ['@id' => '/b', 'tags' => []]],
        'empty' => null,
        'createdAt' => '2026-10-18T12:00:00+00:00',
    ];

    public function test_a_node_is_read_by_its_path(): void
    {
        self::assertSame('/a', self::jsonNode('member[0].@id', self::JSON));
        self::assertSame('y', self::jsonNode('member[0].tags[1]', self::JSON));
        self::assertSame(self::JSON, self::jsonNode('root', self::JSON));
        self::assertNull(self::jsonNode('empty', self::JSON));
        self::assertTrue(self::jsonNodeExists('empty', self::JSON));
        self::assertFalse(self::jsonNodeExists('member[2]', self::JSON));
        self::assertFalse(self::jsonNodeExists('member[0].@id.deeper', self::JSON));
        self::assertJsonNodeSame('member[1].@id', '/b', self::JSON);
        self::assertJsonNodeExists('member[1].tags', self::JSON);
        self::assertJsonNodeNotExists('member[1].missing', self::JSON);
        self::assertJsonNodeCount('member', 2, self::JSON);
        self::assertJsonNodeCount('member[1].tags', 0, self::JSON);
        self::assertJsonNodeMatches('member[0].@id', '/^\/a$/', self::JSON);
        self::assertJsonNodeListsExactly('member[0].tags', ['y', 'x'], self::JSON);
        self::assertJsonNodeIsNow('createdAt', new \DateTimeImmutable('2026-10-18T12:00:01+00:00'), self::JSON);
    }

    public function test_reading_a_missing_node_fails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('The JSON node "member[0].missing" does not exist');

        self::jsonNode('member[0].missing', self::JSON);
    }

    public function test_a_date_more_than_a_second_from_now_is_not_now(): void
    {
        $this->expectException(AssertionFailedError::class);

        self::assertJsonNodeIsNow('createdAt', new \DateTimeImmutable('2026-10-18T12:00:02+00:00'), self::JSON);
    }

    public function test_the_last_response_is_read_and_validated_against_a_schema_file(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        $response = self::requestJsonLd($client, 'POST', '/component/dummy_components', ['uiComponent' => 'AnotherComponent', 'uiClassNames' => ['my-class']]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame($response->toArray(), self::lastJson());
        self::assertJsonNodeSame('uiClassNames[0]', 'my-class');
        self::assertJsonMatchesSchemaFile('component.schema.json');
        self::assertEachJsonItemMatchesSchemaFile('component.schema.json', '[' . $response->getContent() . ',' . $response->getContent() . ']');
    }

    public function test_json_that_breaks_the_schema_fails_naming_the_violation(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('does not validate against component.schema.json');

        self::assertJsonMatchesSchemaFile('component.schema.json', '{"@id": 1}');
    }
}
