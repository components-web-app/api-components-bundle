<?php

namespace Silverback\ApiComponentBundle\Entity;

interface PublishableInterface
{
    public function isPublished(): bool;

    /**
     * @param bool $published
     * @return static
     */
    public function setPublished(bool $published);

    public function getPublishedDate(): ?\DateTime;

    /**
     * @param \DateTime $publishedDate
     * @return static
     */
    public function setPublishedDate(\DateTime $publishedDate);
}
