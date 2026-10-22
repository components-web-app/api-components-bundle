<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Entity\Core;

use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Doctrine\Orm\Filter\IriFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\VersionedInterface;
use Silverback\ApiComponentsBundle\Entity\Utility\VersionedTrait;
use Silverback\ApiComponentsBundle\Repository\Core\CollaborationStateRepository;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CollaborationStateRepository::class)]
#[ORM\Table(name: 'collaboration_state')]
#[ORM\UniqueConstraint(name: 'collaboration_state_component_field', columns: ['component_id', 'field'])]
#[ORM\UniqueConstraint(name: 'collaboration_state_page_data_field', columns: ['page_data_id', 'field'])]
#[UniqueEntity(fields: ['component', 'field'], message: 'This component already has a state for this field.', errorPath: 'component', repositoryMethod: 'findByPublishedComponent')]
#[UniqueEntity(fields: ['pageData', 'field'], message: 'This page data already has a state for this field.', errorPath: 'pageData')]
#[Assert\Expression(
    '(this.getComponent() === null) !== (this.getPageData() === null)',
    message: 'A state belongs to either a component or a page data.',
)]
#[ApiResource(
    security: "is_granted('ROLE_ADMIN')",
    cacheHeaders: ['public' => false, 'no_store' => true],
)]
#[GetCollection(
    parameters: [
        'component' => new QueryParameter(filter: new IriFilter()),
        'pageData' => new QueryParameter(filter: new IriFilter()),
        'field' => new QueryParameter(filter: new ExactFilter()),
    ],
)]
#[Get]
#[Post]
#[Patch]
#[Delete]
class CollaborationState implements VersionedInterface
{
    use IdTrait;
    use VersionedTrait;

    public const int STATE_MAX_LENGTH = 8388608;

    #[ORM\ManyToOne(targetEntity: AbstractComponent::class)]
    #[ORM\JoinColumn(name: 'component_id', nullable: true, onDelete: 'CASCADE')]
    #[ApiProperty(readableLink: false, writableLink: false)]
    private ?AbstractComponent $component = null;

    #[ORM\ManyToOne(targetEntity: AbstractPageData::class)]
    #[ORM\JoinColumn(name: 'page_data_id', nullable: true, onDelete: 'CASCADE')]
    #[ApiProperty(readableLink: false, writableLink: false)]
    private ?AbstractPageData $pageData = null;

    #[ORM\Column(nullable: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $field = null;

    #[ORM\Column(type: Types::TEXT, length: self::STATE_MAX_LENGTH, nullable: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: self::STATE_MAX_LENGTH)]
    private ?string $state = null;

    #[ORM\Column(nullable: true)]
    #[Assert\NotNull]
    #[Assert\Length(max: 255)]
    private ?string $htmlHash = null;

    public function getComponent(): ?AbstractComponent
    {
        return $this->component;
    }

    public function setComponent(?AbstractComponent $component): static
    {
        $this->component = $component;

        return $this;
    }

    public function getPageData(): ?AbstractPageData
    {
        return $this->pageData;
    }

    public function setPageData(?AbstractPageData $pageData): static
    {
        $this->pageData = $pageData;

        return $this;
    }

    public function getField(): ?string
    {
        return $this->field;
    }

    public function setField(?string $field): static
    {
        $this->field = $field;

        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(?string $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function getHtmlHash(): ?string
    {
        return $this->htmlHash;
    }

    public function setHtmlHash(?string $htmlHash): static
    {
        $this->htmlHash = $htmlHash;

        return $this;
    }
}
