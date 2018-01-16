<?php

namespace Silverback\ApiComponentBundle\Controller;

use Silverback\ApiComponentBundle\Entity\Component\Form\FormView;
use Silverback\ApiComponentBundle\Factory\FormFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

abstract class AbstractForm extends AbstractController
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var FormFactory
     */
    protected $formFactory;

    /**
     * AbstractForm constructor.
     * @param EntityManagerInterface $entityManager
     * @param SerializerInterface $serializer
     * @param FormFactory $formFactory
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        FormFactory $formFactory
    )
    {
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->formFactory = $formFactory;
    }

    /**
     * @param $data
     * @param $_format
     * @param $valid
     * @param Response|null $response
     * @return Response
     */
    protected function getResponse ($data, $_format, $valid, Response $response = null)
    {
        if (!$response) {
            $response = new Response();
        }
        $response->setStatusCode($valid ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);
        $response->setContent($this->serializer->serialize($data, $_format, ['groups' => ['page']]));
        return $response;
    }

    /**
     * @param FormView $formView
     * @return mixed
     */
    protected function getFormValid (FormView $formView)
    {
        return $formView->getVars()['valid'];
    }

    /**
     * @param FormInterface $form
     * @param $content
     * @return array
     * @throws BadRequestHttpException
     */
    public function deserializeFormData (FormInterface $form, $content): array
    {
        $content = \GuzzleHttp\json_decode($content, true);
        if (!isset($content[$form->getName()])) {
            throw new BadRequestHttpException(
                "Form object key could not be found. Expected: <b>" . $form->getName() . "</b>: { \"input_name\": \"input_value\" }"
            );
        }
        return $content[$form->getName()];
    }
}
