<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Component;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Dto\Form\FormView;
use Silverback\ApiComponentBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentBundle\Validator\Constraints as ACBAssert;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource
 * @ORM\Entity
 */
class Form extends AbstractComponent
{
    public string $formType;
    public string $successHandler;

    /** @ApiProperty(writable=false) */
    public FormView $form;

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
}
