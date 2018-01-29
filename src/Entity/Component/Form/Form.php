<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Form;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Form\Handler\FormHandlerInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Silverback\ApiComponentBundle\Validator\Constraints as ACBAssert;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Form
 * @package Silverback\ApiComponentBundle\Entity\Component\Form
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity()
 * @ORM\EntityListeners({"\Silverback\ApiComponentBundle\EntityListener\FormListener"})
 * @ApiResource(
 *     collectionOperations={
 *         "get"={"method"="GET", "normalization_context"={"groups"={"page"}}},
 *         "post"={"method"="POST", "denormalization_context"={"groups"={"form_write"}}},
 *     },
 *     itemOperations={
 *         "get"={"method"="GET", "normalization_context"={"groups"={"page"}}},
 *         "delete"={"method"="DELETE", "normalization_context"={"groups"={"page"}}},
 *         "put"={"method"="PUT", "denormalization_context"={"groups"={"form_write"}}},
 *         "validate_item"={"method"="PATCH", "route_name"="silverback_api_component_form_validate_item", "denormalization_context"={"groups"={"none"}}},
 *         "validate_form"={"method"="POST", "route_name"="silverback_api_component_form_submit", "denormalization_context"={"groups"={"none"}}}
 *     }
 * )
 */
class Form extends AbstractComponent
{
    /**
     * @ORM\Column(type="string")
     * @Groups({"page", "form_write"})
     * @ACBAssert\FormTypeClass()
     * @Assert\NotBlank()
     * @var string
     */
    private $formType;

    /**
     * @ApiProperty(writable=false)
     * @Groups({"page", "validate"})
     * @var null|FormView
     */
    private $form;

    /**
     * @ORM\Column(type="datetime")
     * @ApiProperty(writable=false)
     * @Groups({"page", "validate"})
     * @var null|\DateTime
     */
    private $lastModified;

    /**
     * @ORM\Column(type="string")
     * @ACBAssert\FormHandlerClass()
     * @Groups({"form_write"})
     * @var null|string
     */
    private $successHandler;

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
}
