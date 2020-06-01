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
use Silverback\ApiComponentsBundle\DataProvider\PageDataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Exception\UnexpectedValueException;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ComponentPositionDataTransformer implements DataTransformerInterface
{
    private PageDataProvider $pageDataProvider;

    public function __construct(PageDataProvider $pageDataProvider)
    {
        $this->pageDataProvider = $pageDataProvider;
    }

    /**
     * @param ComponentPosition $object
     */
    public function transform($object, string $to, array $context = [])
    {
        $pageData = $this->pageDataProvider->getPageData();
        if (!$pageData) {
            throw new UnexpectedValueException('Could not find page data.');
        }

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $component = $propertyAccessor->getValue($pageData, $object->pageDataProperty);
        if (!$component) {
            throw new UnexpectedValueException(sprintf('Page data does not contain a value at %s', $object->pageDataProperty));
        }

        if (!$component instanceof AbstractComponent) {
            throw new InvalidArgumentException(sprintf('The page data property %s is not a component', $object->pageDataProperty));
        }

        $object->setComponent($component);

        return $object;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return $data instanceof ComponentPosition && $data->pageDataProperty;
    }
}
