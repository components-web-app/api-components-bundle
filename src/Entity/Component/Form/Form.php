<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Form;

use ApiPlatform\Core\Annotation\ApiProperty;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\DTO\Form\FormView;
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
     * @var null|\Silverback\ApiComponentBundle\DTO\Form\FormView
     */
    private $form;

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
     * @return string
     */
    public function getFormType(): string
    {
        return $this->formType;
    }

    /**
     * @param string $formType
     */
    public function setFormType(string $formType): void
    {
        $this->formType = $formType;
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
     */
    public function setSuccessHandler(?string $successHandler): void
    {
        $this->successHandler = $successHandler;
    }

    /**
     * @return null|FormView
     */
    public function getForm(): ?FormView
    {
        return $this->form;
    }

    /**
     * @param null|\Silverback\ApiComponentBundle\DTO\Form\FormView $form
     */
    public function setForm(?\Silverback\ApiComponentBundle\DTO\Form\FormView $form): void
    {
        $this->form = $form;
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
     */
    public function setLastModified(?\DateTime $lastModified): void
    {
        $this->lastModified = $lastModified;
    }
}
