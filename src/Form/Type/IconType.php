<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IconType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => ['class' => 'form-control icon-input'],
            'icon_endpoint' => 'icon',
            'icon_label' => 'Icon',
            'icon_empty_preview' => 'medias/icon/icon_pin.png',
            'icon_upload_url' => '/user/upload/crop01/icon?reportThumb=icon',
        ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['attr']['data-icon-endpoint'] = $options['icon_endpoint'];
        $view->vars['attr']['data-icon-label'] = $options['icon_label'];
        $view->vars['attr']['data-icon-empty-preview'] = $options['icon_empty_preview'];
        $view->vars['attr']['data-upload-url'] = $options['icon_upload_url'];
    }

    public function getParent(): string
    {
        return HiddenType::class;
    }
}
