<?php

namespace Silverback\ApiComponentBundle\DataProvider;

use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\Exception\ResourceClassNotSupportedException;
use ApiPlatform\Core\Exception\RuntimeException;
use Silverback\ApiComponentBundle\Entity\Component\Form\Form;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectRepository;
use Symfony\Component\Form\FormFactoryInterface;

class FormDataProvider implements ItemDataProviderInterface
{
    /**
     * @var FormFactoryInterface
     */
    private $formFactory;

    /**
     * @var ObjectRepository
     */
    private $managerRegistry;

    /**
     * FormDataProvider constructor.
     * @param FormFactoryInterface $formFactory
     * @param ManagerRegistry $managerRegistry
     */
    public function __construct(
        FormFactoryInterface $formFactory,
        ManagerRegistry $managerRegistry
    )
    {
        $this->formFactory = $formFactory;
        $this->managerRegistry = $managerRegistry;
    }

    /**
     * @param string $resourceClass
     * @param $id
     * @param string|null $operationName
     * @param array $context
     *
     * @return null|Form
     * @throws ResourceClassNotSupportedException
     */
    public function getItem(string $resourceClass, $id, string $operationName = null, array $context = [])
    {
        $manager = $this->managerRegistry->getManagerForClass($resourceClass);
        if (null === $manager) {
            throw new ResourceClassNotSupportedException();
        }

        if (Form::class !== $resourceClass) {
            throw new ResourceClassNotSupportedException();
        }

        $repository = $manager->getRepository($resourceClass);
        if (!method_exists($repository, 'find')) {
            throw new RuntimeException('The repository class must have a "find" method.');
        }

        /**
         * @var null|Form $resource
         */
        $resource = $repository->find($id);

        return $resource;
    }
}
