<?php

namespace Silverback\ApiComponentBundle\DataModifier;

use Silverback\ApiComponentBundle\Entity\Content\Page\StaticPage;
use Silverback\ApiComponentBundle\Repository\Layout\LayoutRepository;

class PageModifier extends AbstractModifier
{
    /**
     * @param StaticPage $page
     * @param array $context
     * @param null|string $format
     * @return object|void
     */
    public function process($page, array $context = array(), ?string $format = null)
    {
        /** @var LayoutRepository $repository */
        $repository = $this->container->get(LayoutRepository::class);
        $page->setLayout($repository->findOneBy(['default' => true]));
    }

    public function supportsData($data): bool
    {
        return $data instanceof StaticPage && !$data->getLayout();
    }

    public static function getSubscribedServices(): array
    {
        return [
            '?' . LayoutRepository::class
        ];
    }
}
