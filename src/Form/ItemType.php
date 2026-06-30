<?php

namespace App\Form;

use App\Entity\Group;
use App\Entity\Item;
use App\Entity\ItemCategory;
use App\Form\Type\ColorType;
use App\Form\Type\IconEntityType;
use App\Form\Type\Select2Type;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ItemType extends AbstractType
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
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Ordre',
                'attr' => ['class' => 'form-control', 'min' => 0],
                'required' => false,
            ])
            ->add('category', EntityType::class, [
                'label' => 'Catégorie',
                'class' => ItemCategory::class,
                'required' => true,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('allUsers', CheckboxType::class, [
                'label' => 'Tout le monde',
                'required' => false,
                'mapped' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('groups', EntityType::class, [
                'label' => 'Groupes',
                'class' => Group::class,
                'multiple' => true,
                'required' => false,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('roles', Select2Type::class, [
                'label' => 'Rôles',
                'multiple' => true,
                'choices' => [
                    'Admin' => 'ROLE_ADMIN',
                    'Master' => 'ROLE_MASTER',
                    'User' => 'ROLE_USER',
                    'Visitor' => 'ROLE_VISITOR',
                ],
                'required' => false,
                'placeholder' => 'Sélectionnez des rôles',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Item::class,
        ]);
    }
}
