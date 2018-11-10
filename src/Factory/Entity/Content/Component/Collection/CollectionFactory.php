<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Collection;

use Silverback\ApiComponentBundle\Entity\Component\Collection\Collection;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\AbstractComponentFactory;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class CollectionFactory extends AbstractComponentFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): Collection
    {
        $component = new Collection();
        $this->init($component, $ops);
        $this->validate($component);
        return $component;
    }

    /**
     * @inheritdoc
     */
    public static function defaultOps(): array
    {
        return array_merge(
            parent::defaultOps(),
            [
                'resource' => '',
                'perPage' => null,
                'title' => null
            ]
        );
    }
}
