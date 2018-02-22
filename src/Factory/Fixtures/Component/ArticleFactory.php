<?php

namespace Silverback\ApiComponentBundle\Factory\Fixtures\Component;

use Silverback\ApiComponentBundle\Entity\Component\Article\Article;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class ArticleFactory extends AbstractComponentFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null, ?AbstractContent $owner = null): Article
    {
        $component = new Article();
        $this->init($component, $ops);
        $component->setTitle($this->ops['title']);
        $component->setSubtitle($this->ops['subtitle']);
        $component->setContent($this->ops['content']);
        $component->setFilePath($this->ops['filePath']);
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
                'title' => 'Untitled',
                'subtitle' => null,
                'content' => 'Article content',
                'filePath' => null
            ]
        );
    }
}
