<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Model\Form;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView as SymfonyFormView;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FormView
{
    private const ARRAY_OUTPUT_VARS = [
        'choices',
        'preferred_choices',
        'errors',
        'is_selected',
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
    ];

    /**
     * @Groups({"Form:component:read"})
     */
    private array $vars;

    /**
     * @Groups({"Form:component:read"})
     */
    private DoctrineCollection $children;

    /**
     * @Groups({"Form:component:read"})
     */
    private bool $rendered;

    /**
     * @Groups({"Form:component:read"})
     */
    private bool $methodRendered;

    /**
     * @Groups({"Form:component:read"})
     */
    private Form $form;

    public function __construct(Form $form, ?SymfonyFormView $formView = null, bool $children = true)
    {
        $isRoot = !$formView;
        if (!$formView) {
            $formView = $form->createView();
        }
        $this->init($formView, $form, $children, $isRoot);
    }

    private function init(SymfonyFormView $formView, Form $form, bool $children = true, bool $isRoot = false): void
    {
        $this->form = $form;
        $this->rendered = $formView->isRendered();
        $this->methodRendered = $formView->isMethodRendered();
        $this->processViewVars($formView, $isRoot);
        if ($children) {
            $this->children = new ArrayCollection();
            foreach ($formView->getIterator() as $view) {
                $this->addChild($view);
            }
            if (\array_key_exists('prototype', $formView->vars)) {
                $this->addChild($formView->vars['prototype']);
            }
        }
    }

    private function processViewVars(SymfonyFormView $formView, $isRoot): void
    {
        $outputVars = array_merge(self::ARRAY_OUTPUT_VARS, self::OUTPUT_VARS);
        if (!$isRoot) {
            $outputVars[] = 'value';
        }
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

    private function addChild(SymfonyFormView $symfonyFormView): void
    {
        $formView = new self($this->form, $symfonyFormView);
        $this->children->add($formView);
    }

    public function getVars(): array
    {
        return $this->vars;
    }

    public function getChildren(): DoctrineCollection
    {
        return $this->children;
    }

    public function isRendered(): bool
    {
        return $this->rendered;
    }

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
