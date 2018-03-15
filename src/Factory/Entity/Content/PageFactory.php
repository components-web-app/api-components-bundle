<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content;

use Silverback\ApiComponentBundle\Entity\Content\Page;
use Silverback\ApiComponentBundle\Factory\Entity\AbstractFactory;

class PageFactory extends AbstractFactory
{
    public const PAGE_OPS = [
        'title' => 'New Page',
        'metaDescription' => null,
        'parent' => null,
        'layout' => null,
        'route' => null
    ];

    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): Page
    {
        $component = new Page();
        $this->init($component, $ops);
        $this->validate($component);
        return $component;
    }

    /**
     * @inheritdoc
     */
    protected static function defaultOps(): array
    {
        return self::PAGE_OPS;
    }
}
