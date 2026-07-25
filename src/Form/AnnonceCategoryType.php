<?php

namespace App\Form;

use App\Entity\AnnonceCategory;
use App\Form\Type\ColorType;
use App\Form\Type\IconEntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AnnonceCategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('color', ColorType::class, [
                'label' => 'Couleur du badge',
                'required' => false,
            ])
            ->add('bgColor', ColorType::class, [
                'label' => 'Couleur de fond',
                'required' => false,
            ])
            ->add('textColor', ColorType::class, [
                'label' => 'Couleur du texte',
                'required' => false,
            ])
            ->add('icon', IconEntityType::class, [
                'required' => false,
            ])
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Ordre',
                'attr' => ['class' => 'form-control', 'min' => 0],
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AnnonceCategory::class,
        ]);
    }
}
