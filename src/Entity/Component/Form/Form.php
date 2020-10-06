<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Component\Form;

use ApiPlatform\Core\Annotation\ApiProperty;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Dto\Form\FormView;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Validator\Constraints as ACBAssert;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Class Form
 * @package Silverback\ApiComponentBundle\Entity\Component\Form
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity()
 */
class Form extends AbstractComponent
{
    /**
     * @ORM\Column()
     * @Groups({"component_write"})
     * @var string
     */
    private $formType;

    /**
     * @ORM\Column()
     * @Groups({"component_write"})
     * @var null|string
     */
    private $successHandler;

    /**
     * @ApiProperty(writable=false)
     * @var null|\DateTime
     */
    private $lastModified;

    /**
     * @ApiProperty(writable=false)
     * @Groups({"component", "content"})
     * @var null|\Silverback\ApiComponentBundle\Dto\Form\FormView
     */
    private $form;

    /**
     * @ORM\Column(type="json")
     * @Groups({"component_write"})
     * @var array|null
     */
    private $formOptions;

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $metadata->addPropertyConstraints(
            'formType',
            [
                new ACBAssert\FormTypeClass(),
                new Assert\NotBlank()
            ]
        );
        $metadata->addPropertyConstraint(
            'successHandler',
            new ACBAssert\FormHandlerClass()
        );
    }

    /**
     * @return null|string
     */
    public function getFormType(): ?string
    {
        return $this->formType;
    }

    /**
     * @param string $formType
     * @return Form
     */
    public function setFormType(string $formType): Form
    {
        $this->formType = $formType;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getSuccessHandler(): ?string
    {
        return $this->successHandler;
    }

    /**
     * @param null|string $successHandler
     * @return Form
     */
    public function setSuccessHandler(?string $successHandler): Form
    {
        $this->successHandler = $successHandler;
        return $this;
    }

    /**
     * @return null|FormView
     */
    public function getForm(): ?FormView
    {
        return $this->form;
    }

    /**
     * @param null|FormView $form
     * @return Form
     */
    public function setForm(?FormView $form): Form
    {
        $this->form = $form;
        return $this;
    }

    /**
     * @return \DateTime|null
     */
    public function getLastModified(): ?\DateTime
    {
        return $this->lastModified;
    }

    /**
     * @param \DateTime|null $lastModified
     * @return Form
     */
    public function setLastModified(?\DateTime $lastModified): Form
    {
        $this->lastModified = $lastModified;
        return $this;
    }

    public function getFormOptions(): array
    {
        return $this->formOptions ?: [];
    }

    public function setFormOptions(?array $formOptions): void
    {
        $this->formOptions = $formOptions;
    }
}
