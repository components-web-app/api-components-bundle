<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\EventListener\Api;

use ApiPlatform\Metadata\HttpOperation;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Helper\Route\RouteGeneratorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RouteEventListener
{
    private RouteGeneratorInterface $routeGenerator;
    private ManagerRegistry $registry;

    public function __construct(RouteGeneratorInterface $routeGenerator, ManagerRegistry $registry)
    {
        $this->routeGenerator = $routeGenerator;
        $this->registry = $registry;
    }

    public function onPostValidate(ViewEvent $event): void
    {
        $request = $event->getRequest();
        $data = $request->attributes->get('data');
        $operationName = $request->attributes->get('_api_operation_name');
        if (
            empty($data) ||
            !$data instanceof Route ||
            '_api_/routes/generate{._format}_post' !== $operationName
        ) {
            return;
        }

        $this->generateRoute($data, $request);
    }

    public function onPostWrite(ViewEvent $event): void
    {
        $request = $event->getRequest();
        $data = $request->attributes->get('data');
        /** @var HttpOperation $operation */
        $operation = $request->attributes->get('_api_operation');
        if (
            empty($data) ||
            !$data instanceof Route ||
            !\in_array($operation->getMethod(), [HttpOperation::METHOD_PUT, HttpOperation::METHOD_PATCH], true)
        ) {
            return;
        }
        $entityManager = $this->registry->getManagerForClass($className = Route::class);
        if (!$entityManager) {
            throw new InvalidArgumentException(sprintf('Could not find entity manager for %s', $className));
        }

        // create a redirect fro the old route
        $previousRouteData = $request->attributes->get('previous_data');
        $previousPath = $previousRouteData->getPath();
        if ($previousPath !== $data->getPath()) {
            $newRedirect = $this->routeGenerator->createRedirect($previousPath, $data);
            $entityManager->persist($newRedirect);
            $entityManager->flush();
        }
    }

    private function generateRoute(Route $data, Request $request): void
    {
        $page = $data->getPageData() ?? $data->getPage();
        if (!$page) {
            throw new \LogicException('Validation should have already checked if the pageData or page values are set.');
        }

        $route = $this->routeGenerator->create($page, $data);

        $request->attributes->set('data', $route);
    }
}
