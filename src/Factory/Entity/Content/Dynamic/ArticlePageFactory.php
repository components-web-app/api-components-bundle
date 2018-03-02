<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Dynamic;

use Silverback\ApiComponentBundle\Entity\Content\Dynamic\ArticlePage;
use Silverback\ApiComponentBundle\Factory\Entity\AbstractFactory;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class ArticlePageFactory extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): ArticlePage
    {
        $component = new ArticlePage();
        $this->init($component, $ops);
        $this->validate($component);
        return $component;
    }

    /**
     * @inheritdoc
     */
    protected static function defaultOps(): array
    {
        return array_merge(
            parent::defaultOps(),
            [
                'title' => 'Untitled',
                'subtitle' => null,
                'content' => 'Article content',
                'filePath' => null,
                'layout' => null,
                'parent' => null,
                'metaDescription' => null
            ]
        );
    }
}
