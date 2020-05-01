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

namespace Silverback\ApiComponentsBundle\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use Silverback\ApiComponentsBundle\Entity\Core\PageTemplate;
use Silverback\ApiComponentsBundle\Repository\Core\LayoutRepository;

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
