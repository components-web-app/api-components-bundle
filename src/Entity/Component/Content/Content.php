<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Content;

use ApiPlatform\Core\Annotation\ApiResource;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class Content
 * @package Silverback\ApiComponentBundle\Entity\Component\Content
 * @ApiResource()
 */
class Content extends AbstractComponent
{
    /**
     * @Groups({"content", "component"})
     * @var string
     */
    private $content;

    /**
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @param string $content
     */
    public function setContent(string $content): void
    {
        $this->content = $content;
    }
}
