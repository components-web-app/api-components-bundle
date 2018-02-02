<?php

namespace Silverback\ApiComponentBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Silverback\ApiComponentBundle\Entity\Component\Form\Form;
use Silverback\ApiComponentBundle\Entity\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FormSubmitPatch extends AbstractForm
{
    /**
     * @Route(
     *     name="silverback_api_component_form_validate_item",
     *     path="/forms/{id}/submit.{_format}",
     *     requirements={"id"="\d+"},
     *     defaults={
     *         "_api_resource_class"=Form::class,
     *         "_api_item_operation_name"="validate_item",
     *         "_format"="jsonld"
     *     }
     * )
     * @Method("PATCH")
     * @param Request $request
     * @param Form $data
     * @param string $_format
     * @return Response
     */
    public function __invoke(Request $request, Form $data, string $_format)
    {
        $form = $this->formFactory->createForm($data);
        $formData = $this->deserializeFormData($form, $request->getContent());
        $form->submit($formData, false);

        $dataCount = count($formData);
        if ($dataCount === 1) {
            $data->setForm(new FormView($form->get(key($formData))->createView()));
            return $this->getResponse($data, $_format, $this->getFormValid($data->getForm()));
        }

        $datum = [];
        $valid = true;
        foreach ($formData as $key => $value) {
            $dataItem = clone $data;
            $dataItem->setForm(new FormView($form->get($key)->createView()));
            $datum[] = $dataItem;
            if ($valid && !$this->getFormValid($dataItem->getForm())) {
                $valid = false;
            }
        }

        return $this->getResponse($datum, $_format, $valid);
    }
}
