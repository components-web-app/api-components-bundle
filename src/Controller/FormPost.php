<?php

namespace Silverback\ApiComponentBundle\Controller;

use Silverback\ApiComponentBundle\Entity\Component\Form\Form;
use Silverback\ApiComponentBundle\Entity\Component\Form\FormView;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FormPost extends AbstractForm
{
    /**
     * @Route(
     *     name="api_component_forms_validate",
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
     * @throws \Exception
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
            try {
                $handler = $this->container->get($data->getSuccessHandler());
                $handler->success($data);
            } catch (NotFoundExceptionInterface $error) {
                throw new \Exception("NotFoundExceptionInterface: " . $error->getMessage());
            } catch (ContainerExceptionInterface $error) {
                throw new \Exception("ContainerExceptionInterface: " . $error->getMessage());
            }
        }
        return $this->getResponse($data, $_format, $valid);
    }
}
