<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Action\Health;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class HealthAction
{
    public function __construct(
        private Connection $connection,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        try {
            $this->connection->executeQuery($this->connection->getDatabasePlatform()->getDummySelectSQL());
        } catch (DBALException $exception) {
            $this->logger?->warning('Health check failed: the database could not be reached.', ['exception' => $exception]);

            return $this->neverStored(['status' => 'unavailable', 'reason' => 'database'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->neverStored(['status' => 'ok'], Response::HTTP_OK);
    }

    /**
     * @param array<string, string> $body
     */
    private function neverStored(array $body, int $status): JsonResponse
    {
        $response = new JsonResponse($body, $status);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
