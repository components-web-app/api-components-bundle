<?php

namespace Silverback\ApiComponentBundle\DataModifier;

use Silverback\ApiComponentBundle\Entity\Content\Page\Dynamic\AbstractDynamicPage;
use Silverback\ApiComponentBundle\Repository\ComponentLocationRepository;

class DynamicPageModifier extends AbstractModifier
{
    public function process($page, array $context = array())
    {
        /** @var ComponentLocationRepository $repository */
        $repository = $this->container->get(ComponentLocationRepository::class);
        $locations = $repository->findByDynamicPage($page);
        if (!empty($locations)) {
            $page->setComponentLocations($locations);
        }
    }

    public function supportsData($data): bool
    {
        return $data instanceof AbstractDynamicPage;
    }

    public static function getSubscribedServices(): array
    {
        return [
            '?' . ComponentLocationRepository::class
        ];
    }
}
