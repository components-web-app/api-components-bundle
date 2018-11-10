<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Form;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
        'value',
        'attr',
        'id',
        'name',
        'full_name',
        'disabled',
        'label',
        'block_prefixes',
        'unique_block_prefix',
        'valid',
        'required',
        'label_attr',
        'expanded',
        'submitted',
        'placeholder',
        'is_selected',
        'placeholder_in_choices',
        'checked',
        'action',
        'multiple'
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

    public function __construct(SymfonyFormView $formView, bool $children = true)
    {
        $this->rendered = $formView->isRendered();
        $this->methodRendered = $formView->isMethodRendered();
        $this->processViewVars($formView);
        if ($children) {
            $this->children = new ArrayCollection();
            foreach ($formView->getIterator() as $view) {
                $this->addChild($view);
            }
        }
    }

    private function processViewVars(SymfonyFormView $formView): void
    {
        $outputVars = array_merge(self::ARRAY_OUTPUT_VARS, self::OUTPUT_VARS);
        foreach ($outputVars as $var) {
            if (isset($formView->vars[$var])) {
                $this->vars[$var] = $formView->vars[$var];
                $this->convertVarToArray($var);
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
        $formView = new FormView($formViews);
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
}
