<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Content;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 *
 * @ApiResource()
 */
class Content extends AbstractComponent
{
    /**
     * @ORM\Column(type="text")
     * @Groups({"page"})
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
