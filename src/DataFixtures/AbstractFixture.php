<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\DataFixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\DataFixtures\AbstractFixture as BaseAbstractFixture;
use Doctrine\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Exception\InvalidEntityException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class AbstractFixture extends BaseAbstractFixture
{
    protected $validator;
    /** @var ArrayCollection */
    private $entities;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
        $this->resetEntities();
    }

    private function resetEntities(): void
    {
        $this->entities = new ArrayCollection();
    }


    protected function addEntities(array $entities): void
    {
        foreach ($entities as $entity) {
            $this->addEntity($entity);
        }
    }

    private function validateEntity(object $entity): void
    {
        $errors = $this->validator->validate($entity);
        if (\count($errors)) {
            throw new InvalidEntityException($errors, sprintf('%s failed validation: %s', \get_class($entity), (string) $errors));
        }
    }

    protected function addEntity(object $entity, bool $skipValidation = false): void
    {
        if (!$skipValidation) {
            $this->validateEntity($entity);
        }
        $this->entities->add($entity);
    }

    protected function flushEntity(ObjectManager $manager, object $entity, bool $skipValidation = false): void
    {
        if (!$skipValidation) {
            $this->validateEntity($entity);
        }
        $this->flush($manager, new ArrayCollection([ $entity ]));
    }

    protected function flush(ObjectManager $manager, ?ArrayCollection $entities = null): void
    {
        $useEntityArgument = $entities !== null;
        $flushingEntities = $useEntityArgument ? $entities : $this->entities;
        foreach ($flushingEntities as $entity) {
            $manager->persist($entity);
        }
        $manager->flush();
        if (!$useEntityArgument) {
            $this->resetEntities();
        }
    }
}
