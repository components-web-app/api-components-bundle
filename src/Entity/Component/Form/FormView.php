<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Form;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class FormView
 * @package Silverback\ApiComponentBundle\Entity\Component\Form
 * @author Daniel West <daniel@silverback.is>
 */
class FormView
{
    /**
     * @Groups({"page"})
     * @var array
     */
    private $vars;

    /**
     * @Groups({"page"})
     * @var ArrayCollection
     */
    private $children;

    /**
     * @Groups({"page"})
     * @var bool
     */
    private $rendered;

    /**
     * @Groups({"page"})
     * @var bool
     */
    private $methodRendered;

    public function __construct(\Symfony\Component\Form\FormView $formViews, bool $children = true)
    {
        $varsToArray = ['choices', 'preferred_choices', 'errors', 'is_selected'];

        $outputVars = array_merge($varsToArray, [
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
        ]);
        foreach ($outputVars as $var) {
            if (isset($formViews->vars[$var])) {
                $this->vars[$var] = $formViews->vars[$var];

                if (in_array($var, $varsToArray)) {
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
        }

        if ($children) {
            $this->children = new ArrayCollection();
            foreach ($formViews as $formView) {
                $this->addChild($formView);
            }
        }
        $this->rendered = $formViews->isRendered();
        $this->methodRendered = $formViews->isMethodRendered();
    }

    public function addChild(\Symfony\Component\Form\FormView $formViews)
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
     * @return ArrayCollection
     */
    public function getChildren(): ArrayCollection
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
