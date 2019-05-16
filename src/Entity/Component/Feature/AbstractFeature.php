<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Component\Feature;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Content\ComponentGroup\ComponentGroup;
use Symfony\Component\Serializer\Annotation\Groups;

abstract class AbstractFeature extends AbstractComponent
{
    /**
     * @ORM\Column()
     * @Groups({"component", "content"})
     * @var null|string
     */
    protected $title;

    public function __construct()
    {
        parent::__construct();
        $this->addValidComponent(AbstractFeatureItem::class);
        $this->addComponentGroup(new ComponentGroup());
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

    public function onDeleteCascade(): bool
    {
        return true;
    }
}
