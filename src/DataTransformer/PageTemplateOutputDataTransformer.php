<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use Silverback\ApiComponentBundle\Entity\Core\PageTemplate;
use Silverback\ApiComponentBundle\Repository\Core\LayoutRepository;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class PageTemplateOutputDataTransformer implements DataTransformerInterface
{
    private LayoutRepository $layoutRepository;

    public function __construct(LayoutRepository $layoutRepository)
    {
        $this->layoutRepository = $layoutRepository;
    }

    /**
     * @param PageTemplate $object
     */
    public function transform($object, string $to, array $context = [])
    {
        $object->layout = $this->layoutRepository->findDefault();
        return $object;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return $data instanceof PageTemplate && !$data->layout;
    }
}
