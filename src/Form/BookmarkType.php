<?php

namespace App\Form;

use App\Entity\Bookmark;
use App\Form\Type\ColorType;
use App\Form\Type\IconEntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BookmarkType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('summary', TextType::class, [
                'label' => 'Résumé',
                'attr' => ['class' => 'form-control'],
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['class' => 'form-control', 'rows' => 4],
                'required' => false,
            ])
            ->add('icon', IconEntityType::class, [
                'required' => true,
            ])
            ->add('bgcolor', ColorType::class, [
                'label' => 'Couleur de fond',
                'required' => false,
            ])
            ->add('color', ColorType::class, [
                'label' => 'Couleur du texte',
                'required' => false,
            ])
            ->add('url', UrlType::class, [
                'label' => 'Lien',
                'attr' => ['class' => 'form-control'],
                'required' => true,
            ])
            ->add('newTab', CheckboxType::class, [
                'label' => 'Ouvrir dans un nouvel onglet',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Bookmark::class,
        ]);
    }
}
