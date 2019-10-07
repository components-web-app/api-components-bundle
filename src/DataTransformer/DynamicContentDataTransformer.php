<?php

namespace Silverback\ApiComponentBundle\DataTransformer;

use Silverback\ApiComponentBundle\Entity\Content\Page\Dynamic\DynamicContent;
use Silverback\ApiComponentBundle\Repository\Content\Page\DynamicPageRepository;

final class DynamicContentDataTransformer extends AbstractDataTransformer
{
    /**
     * @param DynamicContent $object
     */
    public function transform($object, array $context = []): DynamicContent
    {
        /** @var DynamicPageRepository $repository */
        $repository = $this->container->get(DynamicPageRepository::class);
        $dynamicPage = $repository->findOneBy([
            'dynamicPageClass' => \get_class($object)
        ]);
        $object->setDynamicPage($dynamicPage);
        return $object;
    }

    public function supportsTransformation($data, array $context = []): bool
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
