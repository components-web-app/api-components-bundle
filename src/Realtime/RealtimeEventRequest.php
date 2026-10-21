<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Realtime;

use ApiPlatform\Validator\Exception\ValidationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

final readonly class RealtimeEventRequest
{
    public const int MAX_BYTES = 16384;
    public const string CLIENT_ID = 'clientId';
    public const string EVENT = 'event';
    public const string IRI = 'iri';
    public const string DATA = 'data';
    public const string TOO_LARGE_MESSAGE = 'A real-time event body cannot be larger than 16384 bytes.';
    public const string INVALID_BODY_MESSAGE = 'The body must be a JSON object with "clientId" and "event".';
    public const string CLIENT_ID_MESSAGE = 'This value should be a UUID.';
    public const string EVENT_MESSAGE = 'This value should be a lowercase event name of at most 64 characters starting with a letter, using only a-z, 0-9, "_", ".", ":" and "-".';
    public const string IRI_MESSAGE = 'This value should be of type string.';
    public const string DATA_MESSAGE = 'This value should not contain a number too large to be represented.';
    public const string UNSUPPORTED_MEDIA_TYPE_MESSAGE = 'A real-time event must be sent as application/json; only a presence event whose data.state is "left" may be sent as text/plain.';
    private const array JSON_FORMATS = ['json', 'jsonld'];
    private const string BEACON_FORMAT = 'txt';
    private const string PRESENCE_EVENT = 'presence';
    private const string STATE = 'state';
    private const string LEFT_STATE = 'left';
    private const string UUID_PATTERN = '/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i';
    private const string EVENT_PATTERN = '/\A[a-z][a-z0-9_.:-]{0,63}\z/';

    private function __construct(
        public string $clientId,
        public string $event,
        public ?string $iri,
        public mixed $data,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $content = $request->getContent();
        if (\strlen($content) > self::MAX_BYTES) {
            throw new HttpException(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, self::TOO_LARGE_MESSAGE);
        }

        if (!\in_array($request->getContentTypeFormat(), self::JSON_FORMATS, true) && !(self::isText($request) && self::isPresenceLeft($content))) {
            throw new UnsupportedMediaTypeHttpException(self::UNSUPPORTED_MEDIA_TYPE_MESSAGE);
        }

        return self::fromContent($content);
    }

    public static function fromContent(string $content): self
    {
        if (\strlen($content) > self::MAX_BYTES) {
            throw new HttpException(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, self::TOO_LARGE_MESSAGE);
        }

        $body = json_decode($content);
        if (!$body instanceof \stdClass) {
            throw self::invalid([['', self::INVALID_BODY_MESSAGE]]);
        }

        $fields = get_object_vars($body);
        $clientId = self::matching($fields, self::CLIENT_ID, self::UUID_PATTERN);
        $event = self::matching($fields, self::EVENT, self::EVENT_PATTERN);
        $iri = $fields[self::IRI] ?? null;
        $data = $fields[self::DATA] ?? null;
        $encodable = false !== json_encode($data);

        if (null !== $clientId && null !== $event && (null === $iri || \is_string($iri)) && $encodable) {
            return new self($clientId, $event, $iri, $data);
        }

        throw self::invalid(array_filter([null === $clientId ? [self::CLIENT_ID, self::CLIENT_ID_MESSAGE] : null, null === $event ? [self::EVENT, self::EVENT_MESSAGE] : null, null === $iri || \is_string($iri) ? null : [self::IRI, self::IRI_MESSAGE], $encodable ? null : [self::DATA, self::DATA_MESSAGE]]));
    }

    public static function isText(Request $request): bool
    {
        return self::BEACON_FORMAT === $request->getContentTypeFormat();
    }

    public static function isClientId(string $value): bool
    {
        return 1 === preg_match(self::UUID_PATTERN, $value);
    }

    private static function isPresenceLeft(string $content): bool
    {
        $body = [json_decode($content, true)];

        return [self::PRESENCE_EVENT] === array_column($body, self::EVENT) && [self::LEFT_STATE] === array_column(array_column($body, self::DATA), self::STATE);
    }

    /**
     * @param array<array-key, mixed> $fields
     */
    private static function matching(array $fields, string $field, string $pattern): ?string
    {
        $value = $fields[$field] ?? null;

        return \is_string($value) && 1 === preg_match($pattern, $value) ? $value : null;
    }

    /**
     * @param array<array{string, string}> $violations
     */
    private static function invalid(array $violations): ValidationException
    {
        $list = new ConstraintViolationList();
        foreach ($violations as [$propertyPath, $message]) {
            $list->add(new ConstraintViolation($message, $message, [], null, $propertyPath, null));
        }

        return new ValidationException($list);
    }
}
