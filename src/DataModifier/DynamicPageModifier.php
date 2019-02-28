<?php

namespace Silverback\ApiComponentBundle\DataModifier;

use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Silverback\ApiComponentBundle\Entity\Content\Page\Dynamic\AbstractDynamicPage;
use Silverback\ApiComponentBundle\Repository\ComponentLocationRepository;

class DynamicPageModifier extends AbstractModifier
{
    /**
     * @param AbstractContent $page
     * @param array $context
     * @param null|string $format
     * @return object|void
     */
    public function process($page, array $context = array(), ?string $format = null)
    {
        /** @var ComponentLocationRepository $repository */
        $repository = $this->container->get(ComponentLocationRepository::class);
        $locations = $repository->findByDynamicPage(\get_class($page));
        if (!$locations->isEmpty()) {
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
