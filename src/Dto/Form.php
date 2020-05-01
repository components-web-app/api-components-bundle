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

namespace Silverback\ApiComponentsBundle\Dto;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView as SymfonyFormView;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class Form
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
        'value',
    ];

    private array $vars;
    private Collection $children;
    private bool $rendered;
    private bool $methodRendered;
    private FormInterface $rootForm;

    public function __construct(FormInterface $rootForm, SymfonyFormView $formView = null, bool $children = true)
    {
        $this->setRootForm($rootForm, $formView, $children);
    }

    public function getVars(): array
    {
        return $this->vars;
    }

    public function getChildren(): Collection
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

    public function getRootForm(): FormInterface
    {
        return $this->rootForm;
    }

    public function setRootForm(FormInterface $rootForm, SymfonyFormView $formView = null, bool $children = true): self
    {
        if (!$this->rootForm->isRoot()) {
            throw new \InvalidArgumentException('The $rootForm cannot be set. The supplied Form is not the root.');
        }
        $this->rootForm = $rootForm;
        if (!$formView) {
            $formView = $rootForm->createView();
        }
        $this->initFormView($formView, $children);

        return $this;
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
        $formView = new self($this->rootForm, $formViews);
        $this->children->add($formView);
    }

    private function initFormView(SymfonyFormView $formView, bool $children = true): void
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
}
