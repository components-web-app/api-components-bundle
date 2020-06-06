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

use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Route\RouteGenerator;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RouteEventListener
{
    private RouteGenerator $routeGenerator;

    public function __construct(RouteGenerator $routeGenerator)
    {
        $this->routeGenerator = $routeGenerator;
    }

    public function onPreValidate(ViewEvent $event): void
    {
        $request = $event->getRequest();
        $data = $request->attributes->get('data');
        if (
            empty($data) ||
            !$data instanceof Route ||
            'generate' !== $request->attributes->get('_api_collection_operation_name')
        ) {
            return;
        }

        $page = $data->getPageData() ?? $data->getPage();
        if (!$page) {
            throw new BadRequestHttpException('You must submit a page or pageData to generate a route.');
        }

        $route = $this->routeGenerator->createFromPage($page, $data);
        $request->attributes->set('data', $route);
    }
}
