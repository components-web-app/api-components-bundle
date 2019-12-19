<?php

namespace Silverback\ApiComponentBundle\Entity;

use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

trait TimestampedEntityTrait
{
    /**
     * @ORM\Column(type="datetime_immutable")
     * @var DateTimeImmutable
     */
    protected $created;

    /**
     * @ORM\Column(type="datetime")
     * @var DateTime
     */
    protected $modified;

    /**
     * @return DateTimeImmutable
     */
    public function getCreated(): DateTimeImmutable
    {
        return $this->created;
    }

    /**
     * @param DateTimeImmutable $created
     * @return static
     */
    public function setCreated(DateTimeImmutable $created)
    {
        if (!$this->created) {
            $this->created = $created;
        }
        return $this;
    }

    /**
     * @return DateTime
     */
    public function getModified(): DateTime
    {
        return $this->modified;
    }

    /**
     * @param DateTime $modified
     * @return static
     */
    public function setModified(DateTime $modified)
    {
        $this->modified = $modified;
        return $this;
    }
}
