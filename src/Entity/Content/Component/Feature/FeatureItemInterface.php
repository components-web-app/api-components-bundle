<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Component\Feature;

use Silverback\ApiComponentBundle\Entity\Route\Route;

interface FeatureItemInterface
{
    /**
     * @return string
     */
    public function getLabel(): string;

    /**
     * @param string $label
     */
    public function setLabel(string $label): void;

    /**
     * @return null|string
     */
    public function getUrl(): ?string;

    /**
     * @param null|string $link
     */
    public function setUrl(?string $link): void;

    /**
     * @return null|Route
     */
    public function getRoute(): ?Route;

    /**
     * @param null|Route $route
     */
    public function setRoute(?Route $route): void;
}
