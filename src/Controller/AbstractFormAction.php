<?php

namespace Silverback\ApiComponentBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentBundle\Dto\Form\FormView;
use Silverback\ApiComponentBundle\Factory\Form\FormFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\SerializerInterface;

abstract class AbstractFormAction extends AbstractController
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
    ) {
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->formFactory = $formFactory;
    }

    /**
     * @param $data
     * @param $_format
     * @param $valid
     * @param Response|null $response
     * @param int|null $statusCode
     * @param array|null $context
     * @return Response
     */
    protected function getResponse(
        $data,
        $_format,
        $valid,
        Response $response = null,
        ?int $statusCode = null,
        ?array $context = ['groups' => ['component']]
    ): Response {
        if (!$response) {
            $response = new Response();
        }
        $response->setStatusCode($statusCode ?? ($valid ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
        $response->setContent($this->serializer->serialize($data, $_format, $context));
        return $response;
    }

    /**
     * @param \Silverback\ApiComponentBundle\DTO\Form\FormView $formView
     * @return mixed
     */
    protected function getFormValid(FormView $formView)
    {
        return $formView->getVars()['valid'];
    }

    /**
     * @param FormInterface $form
     * @param $content
     * @return array
     * @throws BadRequestHttpException
     */
    public function deserializeFormData(FormInterface $form, $content): array
    {
        $content = \GuzzleHttp\json_decode($content, true);
        if (!isset($content[$form->getName()])) {
            throw new BadRequestHttpException(
                sprintf('Form object key could not be found. Expected: <b>%s</b>: { "input_name": "input_value" }', $form->getName())
            );
        }
        return $content[$form->getName()];
    }
}
