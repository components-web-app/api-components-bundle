<?php

namespace Silverback\ApiComponentBundle\DataModifier;

use Silverback\ApiComponentBundle\Entity\Content\Page\Dynamic\DynamicContent;
use Silverback\ApiComponentBundle\Repository\Content\Page\DynamicPageRepository;

class DynamicContentModifier extends AbstractModifier
{
    /**
     * @param DynamicContent $page
     * @param array $context
     * @param null|string $format
     * @return object|void
     */
    public function process($page, array $context = array(), ?string $format = null)
    {
        /** @var DynamicPageRepository $repository */
        $repository = $this->container->get(DynamicPageRepository::class);
        $dynamicPage = $repository->findOneBy([
            'dynamicPageClass' => \get_class($page)
        ]);
        $page->setDynamicPage($dynamicPage);
    }

    public function supportsData($data): bool
    {
        return $data instanceof DynamicContent;
    }

    public static function getSubscribedServices(): array
    {
        return [
            '?' . DynamicPageRepository::class
        ];
    }
}
