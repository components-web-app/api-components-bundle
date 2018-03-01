<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Component\Content;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\Component\AbstractComponent;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Class Content
 * @package Silverback\ApiComponentBundle\Entity\Content\Component\Content
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

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $metadata->addPropertyConstraint(
            'content',
            new Assert\NotNull()
        );
    }

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
