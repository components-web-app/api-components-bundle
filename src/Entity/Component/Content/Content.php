<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Content;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class Content
 * @package Silverback\ApiComponentBundle\Entity\Component\Content
 * @ApiResource(shortName="component/content")
 * @ORM\Entity()
 */
class Content extends AbstractComponent
{
    /**
     * @ORM\Column(type="text")
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
