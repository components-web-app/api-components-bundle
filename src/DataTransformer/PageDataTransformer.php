<?php

namespace Silverback\ApiComponentBundle\DataTransformer;

use Silverback\ApiComponentBundle\Entity\Content\Page\StaticPage;
use Silverback\ApiComponentBundle\Repository\Layout\LayoutRepository;

final class PageDataTransformer extends AbstractDataTransformer
{
    /**
     * @param StaticPage $object
     */
    public function transform($object, array $context = []): StaticPage
    {
        /** @var LayoutRepository $repository */
        $repository = $this->container->get(LayoutRepository::class);
        $object->setLayout($repository->findOneBy(['default' => true]));
        return $object;
    }

    public function supportsTransformation($data, array $context = []): bool
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
