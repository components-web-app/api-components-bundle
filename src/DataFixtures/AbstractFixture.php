<?php

namespace Silverback\ApiComponentBundle\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture as BaseAbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Exception\InvalidEntityException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class AbstractFixture extends BaseAbstractFixture
{
    protected $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    protected function persist(ObjectManager $manager, object $entity, bool $skipValidation = false): void
    {
        if (!$skipValidation) {
            $errors = $this->validator->validate($entity);
            if (\count($errors)) {
                throw new InvalidEntityException($errors);
            }
        }
        $manager->persist($entity);
    }
}
