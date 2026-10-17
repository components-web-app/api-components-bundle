<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\HttpCache;

use ApiPlatform\Validator\Exception\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\HttpCache\ScopedPurgeRequest;

class ScopedPurgeRequestTest extends TestCase
{
    public function test_an_empty_body_is_a_full_flush(): void
    {
        self::assertNull(ScopedPurgeRequest::fromContent(''));
    }

    public function test_paths_and_tags_are_read_in_order(): void
    {
        $request = ScopedPurgeRequest::fromContent('{"paths": ["/b", "/a"], "tags": ["cwa-html", "/_/layouts"]}');

        self::assertSame(['/b', '/a'], $request->paths);
        self::assertSame(['cwa-html', '/_/layouts'], $request->tags);
    }

    public function test_either_key_alone_is_enough(): void
    {
        self::assertSame([], ScopedPurgeRequest::fromContent('{"paths": ["/"]}')->tags);
        self::assertSame([], ScopedPurgeRequest::fromContent('{"tags": ["cwa-html"]}')->paths);
    }

    /**
     * @return iterable<string, array{string, list<array{string, string}>}>
     */
    public static function invalidBodies(): iterable
    {
        yield 'malformed json' => ['{"tags": [', [['', ScopedPurgeRequest::INVALID_BODY_MESSAGE]]];
        yield 'whitespace only' => [' ', [['', ScopedPurgeRequest::INVALID_BODY_MESSAGE]]];
        yield 'null' => ['null', [['', ScopedPurgeRequest::INVALID_BODY_MESSAGE]]];
        yield 'a list' => ['[]', [['', ScopedPurgeRequest::INVALID_BODY_MESSAGE]]];
        yield 'a string' => ['"cwa-html"', [['', ScopedPurgeRequest::INVALID_BODY_MESSAGE]]];
        yield 'an empty object' => ['{}', [['', ScopedPurgeRequest::NOTHING_TO_PURGE_MESSAGE]]];
        yield 'only an unknown key' => ['{"tag": ["a"]}', [['tag', ScopedPurgeRequest::UNEXPECTED_FIELD_MESSAGE], ['', ScopedPurgeRequest::NOTHING_TO_PURGE_MESSAGE]]];
        yield 'an unknown key beside tags' => ['{"tags": ["a"], "0": true}', [['0', ScopedPurgeRequest::UNEXPECTED_FIELD_MESSAGE]]];
        yield 'empty tags' => ['{"tags": []}', [['tags', ScopedPurgeRequest::NOT_A_LIST_MESSAGE]]];
        yield 'empty paths' => ['{"paths": []}', [['paths', ScopedPurgeRequest::NOT_A_LIST_MESSAGE]]];
        yield 'null paths' => ['{"paths": null, "tags": ["a"]}', [['paths', ScopedPurgeRequest::NOT_A_LIST_MESSAGE]]];
        yield 'a string as tags' => ['{"tags": "a"}', [['tags', ScopedPurgeRequest::NOT_A_LIST_MESSAGE]]];
        yield 'an object as tags' => ['{"tags": {"a": "b"}}', [['tags', ScopedPurgeRequest::NOT_A_LIST_MESSAGE]]];
        yield 'a non-string tag' => ['{"tags": ["a", 1]}', [['tags[1]', ScopedPurgeRequest::NOT_A_STRING_MESSAGE]]];
        yield 'an empty tag' => ['{"tags": [""]}', [['tags[0]', ScopedPurgeRequest::EMPTY_TAG_MESSAGE]]];
        yield 'a tag with a comma' => ['{"tags": ["/one,/two"]}', [['tags[0]', ScopedPurgeRequest::SPLIT_TAG_MESSAGE]]];
        yield 'a tag with a tab' => ['{"tags": ["/one\t/two"]}', [['tags[0]', ScopedPurgeRequest::SPLIT_TAG_MESSAGE]]];
        yield 'a relative path' => ['{"paths": ["/a", "about"]}', [['paths[1]', ScopedPurgeRequest::PATH_MESSAGE]]];
        yield 'an empty path' => ['{"paths": [""]}', [['paths[0]', ScopedPurgeRequest::PATH_MESSAGE]]];
        yield 'a non-string path' => ['{"paths": [null]}', [['paths[0]', ScopedPurgeRequest::NOT_A_STRING_MESSAGE]]];
        yield 'every violation at once' => ['{"paths": ["x"], "tags": [""], "all": true}', [
            ['all', ScopedPurgeRequest::UNEXPECTED_FIELD_MESSAGE],
            ['paths[0]', ScopedPurgeRequest::PATH_MESSAGE],
            ['tags[0]', ScopedPurgeRequest::EMPTY_TAG_MESSAGE],
        ]];
    }

    /**
     * @param list<array{string, string}> $expected
     */
    #[DataProvider('invalidBodies')]
    public function test_an_invalid_body_is_refused_with_a_violation_per_problem(string $content, array $expected): void
    {
        try {
            ScopedPurgeRequest::fromContent($content);
            self::fail('The body should have been refused');
        } catch (ValidationException $exception) {
            self::assertSame($expected, $this->violations($exception));
        }
    }

    public function test_paths_resolve_through_the_route_tag_and_are_merged_after_the_tags_once(): void
    {
        $request = ScopedPurgeRequest::fromContent('{"paths": ["/a", "/b"], "tags": ["cwa-html", "route:/a"]}');

        self::assertSame(['cwa-html', 'route:/a', 'route:/b'], $request->resolveTags(static fn (string $path): string => 'route:' . $path));
    }

    public function test_a_path_whose_route_tag_would_be_split_is_refused_at_that_path(): void
    {
        $request = ScopedPurgeRequest::fromContent('{"paths": ["/a", "/b,c", "/d e"]}');

        try {
            $request->resolveTags(static fn (string $path): string => $path);
            self::fail('The path should have been refused');
        } catch (ValidationException $exception) {
            self::assertSame([
                ['paths[1]', ScopedPurgeRequest::SPLIT_TAG_MESSAGE],
                ['paths[2]', ScopedPurgeRequest::SPLIT_TAG_MESSAGE],
            ], $this->violations($exception));
        }
    }

    public function test_a_path_whose_route_tag_is_empty_is_refused(): void
    {
        $request = ScopedPurgeRequest::fromContent('{"paths": ["/a"]}');

        $this->expectException(ValidationException::class);
        $request->resolveTags(static fn (): string => '');
    }

    /**
     * @return list<array{string, string}>
     */
    private function violations(ValidationException $exception): array
    {
        $violations = [];
        foreach ($exception->getConstraintViolationList() as $violation) {
            $violations[] = [$violation->getPropertyPath(), (string) $violation->getMessage()];
        }

        return $violations;
    }
}
