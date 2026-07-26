<?php

namespace App\Form;

use App\Entity\Calendar;
use App\Entity\Group;
use App\Entity\User;
use App\Entity\UserGroup;
use App\Form\Type\ColorType;
use App\Form\Type\Select2Type;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CalendarType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isAdmin = $options['isAdmin'] ?? false;
        $user = $options['user'] ?? null;
        $mode = $options['mode'] ?? 'submit';

        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('color', ColorType::class, [
                'label' => 'Couleur',
                'required' => false,
            ])
            ->add('calendarOrder', IntegerType::class, [
                'label' => 'Ordre',
                'attr' => ['class' => 'form-control', 'min' => 0],
                'required' => false,
            ])
        ;

        if ($isAdmin) {
            if ('submit' === $mode) {
                $builder->add('calendarType', ChoiceType::class, [
                    'label' => 'Type de calendrier',
                    'attr' => ['class' => 'form-select'],
                    'choices' => [
                        'Calendrier personnel' => 'personal',
                        'Calendrier de groupe/role' => 'group_role',
                    ],
                    'expanded' => true,
                    'multiple' => false,
                    'mapped' => false,
                ]);
            }

            $builder->add('user', EntityType::class, [
                'label' => 'Utilisateur',
                'class' => User::class,
                'attr' => ['class' => 'form-select'],
                'required' => false,
            ]);

            $builder->add('groups', EntityType::class, [
                'label' => 'Groupes',
                'class' => Group::class,
                'multiple' => true,
                'required' => false,
                'attr' => ['class' => 'form-select'],
            ]);

            $builder->add('allUsers', CheckboxType::class, [
                'label' => 'Tout le monde',
                'required' => false,
                'mapped' => false,
                'attr' => ['class' => 'form-check-input'],
            ]);

            $builder->add('roles', Select2Type::class, [
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
            ]);
        } elseif ($user) {
            if ('submit' === $mode) {
                $builder->add('calendarType', ChoiceType::class, [
                    'label' => 'Type de calendrier',
                    'attr' => ['class' => 'form-select'],
                    'choices' => [
                        'Calendrier personnel' => 'personal',
                        'Calendrier de groupe' => 'group',
                    ],
                    'expanded' => true,
                    'multiple' => false,
                    'mapped' => false,
                ]);
            }

            $builder->add('user', HiddenType::class, ['required' => false, 'mapped' => false]);

            $masterGroups = $user->getUserGroups()->filter(fn ($ug) => UserGroup::ROLE_MASTER === $ug->getRole())->map(fn ($ug) => $ug->getGroup())->toArray();

            $builder->add('groups', EntityType::class, [
                'label' => 'Groupes (Master uniquement)',
                'class' => Group::class,
                'multiple' => true,
                'required' => false,
                'choices' => $masterGroups,
                'attr' => ['class' => 'form-select'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Calendar::class,
            'isAdmin' => false,
            'user' => null,
            'mode' => 'submit',
        ]);
    }
}
