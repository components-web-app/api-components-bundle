<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Article;

use Silverback\ApiComponentBundle\Entity\Content\Component\Article\Article;
use Silverback\ApiComponentBundle\Factory\Entity\AbstractFactory;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class ArticleFactory extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): Article
    {
        $component = new Article();
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
            AbstractFactory::COMPONENT_CLASSES,
            [
                'title' => 'Untitled',
                'subtitle' => null,
                'content' => 'Article content',
                'filePath' => null
            ]
        );
    }
}
