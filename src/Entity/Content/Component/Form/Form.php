<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Component\Form;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Validator\Constraints as ACBAssert;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Class Form
 * @package Silverback\ApiComponentBundle\Entity\Content\Component\Form
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource(
 *     collectionOperations={
 *         "get"={"method"="GET"},
 *         "post"={"method"="POST"},
 *     },
 *     itemOperations={
 *         "get"={"method"="GET"},
 *         "delete"={"method"="DELETE"},
 *         "put"={"method"="PUT"},
 *         "validate_item"={"method"="PATCH", "route_name"="silverback_api_component_form_validate_item", "denormalization_context"={"groups"={"none"}}},
 *         "validate_form"={"method"="POST", "route_name"="silverback_api_component_form_submit", "denormalization_context"={"groups"={"none"}}}
 *     }
 * )
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
     * @var null|FormView
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
