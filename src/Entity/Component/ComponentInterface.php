<?php

namespace Silverback\ApiComponentBundle\Entity\Component;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Silverback\ApiComponentBundle\Entity\Content\ComponentGroup;
use Silverback\ApiComponentBundle\Entity\Content\ContentInterface;
use Silverback\ApiComponentBundle\Entity\ValidComponentInterface;

interface ComponentInterface extends ValidComponentInterface
{
    /**
     * ComponentInterface constructor.
     */
    public function __construct();

    /**
     * @return string
     */
    public function getId(): string;

    /**
     * @return null|string
     */
    public function getClassName(): ?string;

    /**
     * @param null|string $className
     * @return Component
     */
    public function setClassName(?string $className): Component;

    /**
     * @param ContentInterface $content
     * @param bool|null $sortLast
     * @return Component
     */
    public function addLocation(ContentInterface $content, ?bool $sortLast = null): Component;

    /**
     * @param ContentInterface $content
     * @return Component
     */
    public function removeLocation(ContentInterface $content): Component;

    /**
     * @param array $componentGroups
     * @return Component
     */
    public function setComponentGroups(array $componentGroups): Component;

    /**
     * @param ComponentGroup $componentGroup
     * @return Component
     */
    public function addComponentGroup(ComponentGroup $componentGroup): Component;

    /**
     * @param ComponentGroup $componentGroup
     * @return Component
     */
    public function removeComponentGroup(ComponentGroup $componentGroup): Component;

    /**
     * @return ArrayCollection|ComponentGroup[]
     */
    public function getComponentGroups(): Collection;

    /**
     * @return string
     */
    public static function getComponentName(): string;
}
