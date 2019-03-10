<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Component\Content;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Class Content
 * @package Silverback\ApiComponentBundle\Entity\Component\Content
 * @ORM\Entity()
 */
class Content extends AbstractComponent
{
    /**
     * @ORM\Column()
     * @Groups({"component", "content"})
     * @var null|string
     */
    protected $title;

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

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }
}
