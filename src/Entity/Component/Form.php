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

namespace Silverback\ApiComponentsBundle\Entity\Component;

use ApiPlatform\Metadata\ApiProperty;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Utility\TimestampedTrait;
use Silverback\ApiComponentsBundle\Model\Form\FormView;
use Silverback\ApiComponentsBundle\Validator\Constraints as AcbAssert;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * @author Daniel West <daniel@silverback.is>
 */
#[Silverback\Timestamped]
#[ORM\Entity]
class Form extends AbstractComponent
{
    use TimestampedTrait;

    #[ORM\Column(nullable: false)]
    public string $formType;

    #[ApiProperty(writable: false)]
    public ?FormView $formView = null;

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $metadata->addPropertyConstraints(
            'formType',
            [
                new Assert\NotBlank(),
                new AcbAssert\FormTypeClass(),
            ]
        );
    }
}
