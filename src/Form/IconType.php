<?php

namespace App\Form;

use App\Entity\Icon;
use Bnine\FilesBundle\Form\Type\IconUploadType;
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
            ->add('route', IconUploadType::class, [
                'label' => 'Icône',
                'required' => true,
                'icon_empty_preview' => 'medias/icon/icon_pin.png',
                'icon_upload_url' => '/bninefiles/uploadmodal/icon/0?path=&crop',
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