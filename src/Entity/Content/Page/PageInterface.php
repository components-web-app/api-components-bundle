<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Page;

use Silverback\ApiComponentBundle\Entity\Layout\Layout;

interface PageInterface
{
    public function getTitle(): string;

    public function setTitle(string $title);

    public function getMetaDescription(): string;

    public function setMetaDescription(string $metaDescription);

    public function getLayout(): ?Layout;

    public function setLayout(?Layout $layout);
}
