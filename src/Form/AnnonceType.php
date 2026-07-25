<?php

namespace App\Form;

use App\Entity\Annonce;
use App\Entity\AnnonceCategory;
use App\Entity\Group;
use App\Form\Type\IconEntityType;
use App\Form\Type\Select2Type;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AnnonceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Contenu (Markdown)',
                'attr' => [
                    'class' => 'form-control easymde-textarea',
                    'rows' => 15,
                ],
                'required' => false,
            ])
            ->add('canBeHidden', CheckboxType::class, [
                'label' => 'Masquable par l\'utilisateur',
                'required' => false,
            ])
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Ordre',
                'attr' => ['class' => 'form-control', 'min' => 0],
                'required' => false,
            ])
            ->add('category', EntityType::class, [
                'label' => 'Catégorie',
                'class' => AnnonceCategory::class,
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
            'data_class' => Annonce::class,
        ]);
    }
}
