<?php

namespace App\Form;

use App\Entity\Group;
use App\Entity\Tache;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TacheType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Description (Markdown)',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 6,
                ],
                'required' => false,
            ])
            ->add('dueDate', DateTimeType::class, [
                'label' => 'Date de rendu',
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control', 'type' => 'datetime-local'],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'À faire' => 'todo',
                    'En cours' => 'in_progress',
                    'Terminé' => 'done',
                ],
                'required' => true,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('group', EntityType::class, [
                'label' => 'Groupe',
                'class' => Group::class,
                'required' => false,
                'placeholder' => 'Aucun groupe',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('assignedUser', EntityType::class, [
                'label' => 'Assigné à',
                'class' => User::class,
                'required' => false,
                'placeholder' => 'Non assigné',
                'attr' => ['class' => 'form-select'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Tache::class,
        ]);
    }
}
