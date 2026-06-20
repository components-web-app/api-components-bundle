<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Entity\Component;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\FormStateProvider;
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
#[ApiResource(
    provider: FormStateProvider::class,
    operations: [
        new GetCollection(),
        new Post(),
        new Get(),
        new Delete(),
        new Put(),
        new Patch(),
        new Patch(
            name: '_api_/forms/{id}/submit{._format}_patch',
            uriTemplate: '/forms/{id}/submit{._format}',
            read: true,
            deserialize: false,
            validate: false,
            write: false,
            serialize: true,
            requirements: ['id' => '[^/]+'],
            normalizationContext: ['item_uri_template' => '/forms/{id}{._format}'],
        ),
        new Post(
            name: '_api_/forms/{id}/submit{._format}_post',
            uriTemplate: '/forms/{id}/submit{._format}',
            read: true,
            deserialize: false,
            validate: false,
            write: false,
            serialize: true,
            requirements: ['id' => '[^/]+'],
            normalizationContext: ['item_uri_template' => '/forms/{id}{._format}'],
        ),
    ]
)]
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
