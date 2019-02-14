<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Component\Feature;

use Silverback\ApiComponentBundle\Entity\Route\Route;

interface FeatureItemInterface
{
    public function getTitle(): string;

    public function setTitle(string $title): AbstractFeatureItem;

    public function getUrl(): ?string;

    public function setUrl(?string $link): AbstractFeatureItem;

    public function getRoute(): ?Route;

    public function setRoute(?Route $route): AbstractFeatureItem;
}
