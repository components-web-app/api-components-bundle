<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Entity\Core;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use Silverback\ApiComponentsBundle\Entity\Utility\UiTrait;
use Silverback\ApiComponentsBundle\Filter\OrSearchFilter;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * @author Daniel West <daniel@silverback.is>
 */
#[ApiResource(mercure: true)]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'reference'], arguments: ['orderParameterName' => 'order'])]
#[ApiFilter(OrSearchFilter::class, properties: ['title' => 'ipartial', 'reference' => 'ipartial', 'isTemplate' => 'exact', 'uiComponent' => 'ipartial', 'layout.reference' => 'ipartial'])]
class Page extends AbstractPage
{
    use UiTrait;

    #[Assert\NotBlank(message: 'Please specify a layout.')]
    #[Groups(['Route:manifest:read'])]
    public ?Layout $layout;

    #[Assert\NotBlank(message: 'Please enter a reference.')]
    public string $reference;

    #[Assert\NotNull(message: 'Please specify if this page is a template or not.')]
    public bool $isTemplate;

    public function __construct()
    {
        $this->initComponentGroups();
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $metadata->addPropertyConstraint('uiComponent', new Assert\NotBlank([
            'message' => 'Please specify a UI component.',
        ]));
    }
}
