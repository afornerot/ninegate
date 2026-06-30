<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Select2Type extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => [
                'class' => 'form-select select2',
                'data-placeholder' => 'Sélectionnez...',
                'data-allow-clear' => 'true',
            ],
        ]);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        
        if (isset($options['placeholder'])) {
            $view->vars['attr']['data-placeholder'] = $options['placeholder'];
        }
    }
}