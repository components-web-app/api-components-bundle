<?php

namespace Silverback\ApiComponentBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Silverback\ApiComponentBundle\Entity\Component\Form\Form;
use Silverback\ApiComponentBundle\Entity\Component\Form\FormView;
use Silverback\ApiComponentBundle\Factory\FormFactory;
use Silverback\ApiComponentBundle\Form\Handler\FormHandlerInterface;
use Symfony\Component\DependencyInjection\ServiceSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

class FormSubmitPost extends AbstractForm implements ServiceSubscriberInterface
{
    /**
     * @var iterable
     */
    private $handlers;

    public function __construct(
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        FormFactory $formFactory,
        iterable $formHandlers
    )
    {
        parent::__construct($entityManager, $serializer, $formFactory);
        $this->handlers = $formHandlers;
    }

    /**
     * @Route(
     *     name="silverback_api_component_form_submit",
     *     path="/forms/{id}/submit.{_format}",
     *     requirements={"id"="\d+"},
     *     defaults={
     *         "_api_resource_class"=Form::class,
     *         "_api_item_operation_name"="validate_form",
     *         "_format"="jsonld"
     *     }
     * )
     * @Method("POST")
     * @param Request $request
     * @param Form $data
     * @param string $_format
     * @return Response
     * @throws \BadMethodCallException
     */
    public function __invoke(Request $request, Form $data, string $_format)
    {
        $form = $this->formFactory->createForm($data);
        $formData = $this->deserializeFormData($form, $request->getContent());
        $form->submit($formData, true);
        if (!$form->isSubmitted()) {
            return $this->getResponse($data, $_format, false);
        }
        $valid = $form->isValid();
        $data->setForm(new FormView($form->createView()));
        if ($valid && $data->getSuccessHandler()) {
            /**
             * @var FormHandlerInterface $handler
             */
            foreach ($this->handlers as $handler)
            {
                $refl = new \ReflectionClass($handler);
                if ($data->getSuccessHandler() === get_class($handler) || $refl->isSubclassOf($data->getSuccessHandler()))
                {
                    $handler->success($data);
                    break;
                }
            }
        }
        return $this->getResponse($data, $_format, $valid);
    }
}
