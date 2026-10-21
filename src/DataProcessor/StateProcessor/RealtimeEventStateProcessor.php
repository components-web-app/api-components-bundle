<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\DataProcessor\StateProcessor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Silverback\ApiComponentsBundle\Helper\Realtime\RealtimeUserResolver;
use Silverback\ApiComponentsBundle\Mercure\MercureAuthorization;
use Silverback\ApiComponentsBundle\Realtime\RealtimeEventRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\Mercure\Exception\RuntimeException as MercureRuntimeException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;

/**
 * @implements ProcessorInterface<mixed, null>
 */
final class RealtimeEventStateProcessor implements ProcessorInterface
{
    public const string TYPE = 'CwaRealtimeEvent';
    public const int VERSION = 1;
    public const int LIFETIME_SECONDS = 45;
    public const array CLIENT_RATE_LIMITER_CONFIG = [
        'id' => 'cwa_realtime_client',
        'policy' => 'token_bucket',
        'limit' => 20,
        'rate' => ['interval' => '1 second', 'amount' => 10],
    ];
    public const array USER_RATE_LIMITER_CONFIG = [
        'id' => 'cwa_realtime_user',
        'policy' => 'token_bucket',
        'limit' => 60,
        'rate' => ['interval' => '1 second', 'amount' => 30],
    ];
    public const string RATE_LIMITER_POOL = 'silverback.api_components.realtime.rate_limiter_cache';
    public const string TOO_MANY_REQUESTS_MESSAGE = 'Too many real-time events from this user.';
    public const string CROSS_SITE_MESSAGE = 'A real-time event cannot be sent from another site.';
    private const string FETCH_SITE_HEADER = 'Sec-Fetch-Site';
    private const string CROSS_SITE = 'cross-site';

    public function __construct(
        private readonly MercureAuthorization $mercureAuthorization,
        private readonly HubInterface $hub,
        private readonly RateLimiterFactoryInterface $clientRateLimiterFactory,
        private readonly RateLimiterFactoryInterface $userRateLimiterFactory,
        private readonly RealtimeUserResolver $userResolver,
        private readonly ClockInterface $clock,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public static function clientRateLimiterKey(string $userId, string $clientId): string
    {
        return $userId . ' ' . $clientId;
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $request = \array_key_exists('request', $context) ? $context['request'] : null;
        if (!$request instanceof Request) {
            throw new UnsupportedMediaTypeHttpException(RealtimeEventRequest::UNSUPPORTED_MEDIA_TYPE_MESSAGE);
        }

        if (self::CROSS_SITE === $request->headers->get(self::FETCH_SITE_HEADER)) {
            throw new AccessDeniedHttpException(self::CROSS_SITE_MESSAGE);
        }

        $event = RealtimeEventRequest::fromRequest($request);

        $user = $this->userResolver->resolve();
        if (null === $user) {
            throw new AccessDeniedHttpException();
        }

        $this->consumeRateLimits($user['id'], $event->clientId);

        $sentAt = $this->clock->now();
        $topic = $this->mercureAuthorization->getRealtimeTopic();
        $update = new Update($topic, json_encode([
            '@type' => self::TYPE,
            'v' => self::VERSION,
            'clientId' => $event->clientId,
            'user' => $user,
            'event' => $event->event,
            'iri' => $event->iri,
            'data' => $event->data,
            'sentAt' => $sentAt->format(\DateTimeInterface::RFC3339_EXTENDED),
            'expiresAt' => $sentAt->modify(\sprintf('+%d seconds', self::LIFETIME_SECONDS))->format(\DateTimeInterface::RFC3339_EXTENDED),
        ], \JSON_THROW_ON_ERROR), true);

        try {
            $this->hub->publish($update);
        } catch (MercureRuntimeException|HttpClientExceptionInterface $exception) {
            $this->logger?->error(\sprintf('The real-time event "%s" could not be published on "%s": %s', $event->event, $topic, $exception->getMessage()), [
                'topics' => [$topic],
                'event' => $event->event,
                'clientId' => $event->clientId,
                'exception' => $exception,
            ]);
        }

        return null;
    }

    private function consumeRateLimits(string $userId, string $clientId): void
    {
        $limits = [
            $this->clientRateLimiterFactory->create(self::clientRateLimiterKey($userId, $clientId))->consume(),
            $this->userRateLimiterFactory->create($userId)->consume(),
        ];
        $retryAfter = array_map(static fn (RateLimit $limit): int => $limit->getRetryAfter()->getTimestamp(), array_filter($limits, static fn (RateLimit $limit): bool => !$limit->isAccepted()));

        if ([] !== $retryAfter) {
            throw new TooManyRequestsHttpException(max(1, max($retryAfter) - time()), self::TOO_MANY_REQUESTS_MESSAGE);
        }
    }
}
