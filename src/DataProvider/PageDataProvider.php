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

namespace Silverback\ApiComponentsBundle\DataProvider;

use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Repository\Core\RouteRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class PageDataProvider
{
    private RequestStack $requestStack;
    private RouteRepository $routeRepository;

    public function __construct(RequestStack $requestStack, RouteRepository $routeRepository)
    {
        $this->requestStack = $requestStack;
        $this->routeRepository = $routeRepository;
    }

    private function getOriginalRequestPath(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return null;
        }
        $path = $request->headers->get('path');
        if (!$path) {
            throw new BadRequestHttpException('Could not find referer header to retrieve page data');
        }

        return parse_url($path, PHP_URL_PATH);
    }

    public function getPageData(): ?AbstractPageData
    {
        $path = $this->getOriginalRequestPath();
        if (!$path) {
            return null;
        }

        $route = $this->routeRepository->findOneByIdOrPath($path);
        if (!$route) {
            return null;
        }

        return $route->getPageData();
    }
}
