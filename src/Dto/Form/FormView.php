<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Dto\Form;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView as SymfonyFormView;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class FormView
 * @package Silverback\ApiComponentBundle\Entity\Component\Form
 * @author Daniel West <daniel@silverback.is>
 */
class FormView
{
    private const ARRAY_OUTPUT_VARS = [
        'choices',
        'preferred_choices',
        'errors',
        'is_selected'
    ];

    private const OUTPUT_VARS = [
        'action',
        'api_request',
        'attr',
        'block_prefixes',
        'checked',
        'disabled',
        'expanded',
        'full_name',
        'help',
        'id',
        'is_selected',
        'label',
        'label_attr',
        'multiple',
        'name',
        'placeholder',
        'placeholder_in_choices',
        'post_app_proxy',
        'realtime_validate',
        'required',
        'submitted',
        'unique_block_prefix',
        'valid',
        'value'
    ];

    /**
     * @Groups({"component", "content"})
     * @var array
     */
    private $vars;

    /**
     * @Groups({"component", "content"})
     * @var Collection
     */
    private $children;

    /**
     * @Groups({"component", "content"})
     * @var bool
     */
    private $rendered;

    /**
     * @Groups({"component", "content"})
     * @var bool
     */
    private $methodRendered;

    private $form;

    public function __construct(SymfonyFormView $formView, FormInterface $form, bool $children = true)
    {
        $this->init($formView, $form, $children);
    }

    private function init(SymfonyFormView $formView, FormInterface $form, bool $children = true)
    {
        $this->form = $form;
        $this->rendered = $formView->isRendered();
        $this->methodRendered = $formView->isMethodRendered();
        $this->processViewVars($formView);
        if ($children) {
            $this->children = new ArrayCollection();
            foreach ($formView->getIterator() as $view) {
                $this->addChild($view);
            }
            if (array_key_exists('prototype', $formView->vars)) {
                $this->addChild($formView->vars['prototype']);
            }
        }
    }

    private function processViewVars(SymfonyFormView $formView): void
    {
        $outputVars = array_merge(self::ARRAY_OUTPUT_VARS, self::OUTPUT_VARS);
        foreach ($formView->vars as $key=>$value) {
            if (strpos($key, 'custom_') === 0 || in_array($key, $outputVars, true)) {
                $this->vars[$key] = $formView->vars[$key];
                $this->convertVarToArray($key);
            }
        }
    }

    private function convertVarToArray($var): void
    {
        if (\in_array($var, self::ARRAY_OUTPUT_VARS, true)) {
            /** @var iterable $choices */
            $choices = $this->vars[$var];
            $this->vars[$var] = [];
            foreach ($choices as $choice) {
                if (method_exists($choice, 'getMessage')) {
                    $this->vars[$var][] = $choice->getMessage();
                } else {
                    $this->vars[$var][] = (array) $choice;
                }
            }
        }
    }

    private function addChild(SymfonyFormView $formViews): void
    {
        $formView = new FormView($formViews, $this->form);
        $this->children->add($formView);
    }

    /**
     * @return array
     */
    public function getVars(): array
    {
        return $this->vars;
    }

    /**
     * @return Collection
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    /**
     * @return bool
     */
    public function isRendered(): bool
    {
        return $this->rendered;
    }

    /**
     * @return bool
     */
    public function isMethodRendered(): bool
    {
        return $this->methodRendered;
    }

    public function getForm(): FormInterface
    {
        return $this->form;
    }

    public function setForm(FormInterface $form): self
    {
        $this->init($form->createView(), $form, true);
        return $this;
    }
}
