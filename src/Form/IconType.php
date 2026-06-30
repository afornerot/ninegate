<?php

namespace App\Form;

use App\Entity\Icon;
use App\Form\Type\IconType as IconInputType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class IconType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('route', IconInputType::class, [
                'label' => 'Icône',
                'required' => true,
                'icon_empty_preview' => 'medias/icon/icon_pin.png',
                'icon_upload_url' => '/user/upload/crop01/icon?reportThumb=icon_route',
            ])
            ->add('tags', TextType::class, [
                'label' => 'Tags',
                'attr' => ['class' => 'form-control'],
                'required' => false,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Valider',
                'attr' => ['class' => 'btn btn-success'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Icon::class,
        ]);
    }
}