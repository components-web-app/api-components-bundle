<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Component\Feature;

use Silverback\ApiComponentBundle\Entity\Route\Route;

interface FeatureItemInterface
{
    /**
     * @return string
     */
    public function getTitle(): string;

    /**
     * @param string $title
     */
    public function setTitle(string $title): void;

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
