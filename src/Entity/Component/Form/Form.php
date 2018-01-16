<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Form;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Annotation\ApiSubresource;
use Silverback\ApiComponentBundle\Entity\Component\Component;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class Form
 * @package App\Entity\Component\Form
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity()
 * @ORM\EntityListeners({"\App\EntityListener\FormListener"})
 * @ApiResource(
 *     collectionOperations={
 *         "get"={"method"="GET", "normalization_context"={"groups"={"page"}}},
 *         "post"={"method"="POST", "denormalization_context"={"groups"={"form_write"}}},
 *     },
 *     itemOperations={
 *         "get"={"method"="GET", "normalization_context"={"groups"={"page"}}},
 *         "delete"={"method"="DELETE", "normalization_context"={"groups"={"page"}}},
 *         "put"={"method"="PUT", "denormalization_context"={"groups"={"form_write"}}},
 *         "validate_item"={"method"="PATCH", "route_name"="api_forms_validate_item", "denormalization_context"={"groups"={"none"}}},
 *         "validate_form"={"method"="POST", "route_name"="api_forms_validate", "denormalization_context"={"groups"={"none"}}}
 *     }
 * )
 */
class Form extends Component
{
    /**
     * @ORM\Column(type="string")
     * @Groups({"page", "form_write"})
     * @var string
     */
    private $className;

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
     * @var null|string
     */
    private $successHandler;

    /**
     * @return string
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * @param string $className
     */
    public function setClassName(string $className): void
    {
        $this->className = $className;
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
