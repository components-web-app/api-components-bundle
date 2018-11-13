<?php

namespace Silverback\ApiComponentBundle\Serializer\Middleware;

use Silverback\ApiComponentBundle\Entity\Content\Page\Page;
use Silverback\ApiComponentBundle\Repository\LayoutRepository;

class PageMiddleware extends AbstractMiddleware
{
    /**
     * @param Page $page
     * @param array $context
     * @return object|void
     */
    public function process($page, array $context = array())
    {
        /** @var LayoutRepository $repository */
        $repository = $this->container->get(LayoutRepository::class);
        $page->setLayout($repository->findOneBy(['default' => true]));
    }

    public function supportsData($data): bool
    {
        return $data instanceof Page && !$data->getLayout();
    }

    public static function getSubscribedServices(): array
    {
        return [
            '?' . LayoutRepository::class
        ];
    }
}
