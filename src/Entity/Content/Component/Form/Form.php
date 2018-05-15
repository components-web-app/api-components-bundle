<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Component\Form;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Controller\FormSubmitPatch;
use Silverback\ApiComponentBundle\Controller\FormSubmitPost;
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
 *         "get",
 *         "post",
 *     },
 *     itemOperations={
 *         "get",
 *         "delete",
 *         "put",
 *         "patch"={
 *              "method"="PATCH",
 *              "path"="/forms/{id}/submit.{_format}",
 *              "requirements"={"id"="[^/]+"},
 *              "denormalization_context"={"groups"={"none"}},
 *              "controller"=FormSubmitPatch::class
 *         },
 *         "post"={
 *              "method"="POST",
 *              "path"="/forms/{id}/submit.{_format}",
 *              "requirements"={"id"="[^/]+"},
 *              "denormalization_context"={"groups"={"none"}},
 *              "controller"=FormSubmitPost::class
 *         }
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
     * @ApiProperty(writable=false)
     * @Groups({"component", "content"})
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
