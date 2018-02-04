<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Form;

use ApiPlatform\Core\Annotation\ApiProperty;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Validator\Constraints as ACBAssert;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Form
 * @package Silverback\ApiComponentBundle\Entity\Component\Form
 * @author Daniel West <daniel@silverback.is>
 */
class Form extends AbstractComponent
{
    /**
     * @ORM\Column(type="string")
     * @Groups({"form_write"})
     * @ACBAssert\FormTypeClass()
     * @Assert\NotBlank()
     * @var string
     */
    private $formType;

    /**
     * @ORM\Column(type="string")
     * @ACBAssert\FormHandlerClass()
     * @Groups({"form_write"})
     * @var null|string
     */
    private $successHandler;

    /**
     * @ApiProperty(writable=false)
     * @var null|FormView
     */
    private $form;

    /**
     * @ORM\Column(type="datetime")
     * @ApiProperty(writable=false)
     * @var null|\DateTime
     */
    private $lastModified;

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
     * @param null|FormView $form
     */
    public function setForm(?FormView $form): void
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
