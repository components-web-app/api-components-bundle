<?php

namespace Silverback\ApiComponentBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

trait PublishableTrait
{
    /**
     * @ORM\Column(type="boolean", nullable=false)
     * @var bool
     * @Groups({"default"})
     */
    protected $published = true;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var null|\DateTime
     * @Groups({"default"})
     */
    protected $publishedDate;

    /**
     * @return bool
     */
    public function isPublished(): bool
    {
        return $this->published;
    }

    /**
     * @param bool $published
     * @return static
     */
    public function setPublished(bool $published)
    {
        $this->published = $published;
        return $this;
    }

    /**
     * @return \DateTime|null
     */
    public function getPublishedDate(): ?\DateTime
    {
        return $this->publishedDate;
    }

    /**
     * @param \DateTime|null $publishedDate
     * @return static
     */
    public function setPublishedDate(\DateTime $publishedDate)
    {
        $this->publishedDate = $publishedDate;
        return $this;
    }
}
