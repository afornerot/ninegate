<?php

namespace App\Form;

use App\Entity\Group;
use App\Entity\Page;
use App\Entity\PageTemplate;
use App\Entity\User;
use App\Entity\UserGroup;
use App\Form\Type\Select2EntityType;
use App\Form\Type\Select2Type;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('submit', SubmitType::class, [
                'label' => 'Valider',
                'attr' => ['class' => 'btn btn-success no-print me-1'],
            ])

            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => ['class' => 'form-control'],
            ])

            ->add('pageOrder', TextType::class, [
                'label' => 'Ordre',
                'attr' => ['class' => 'form-control'],
            ])

            ->add('pageTemplate', EntityType::class, [
                'label' => 'Template',
                'class' => PageTemplate::class,
                'required' => true,
                'attr' => ['class' => 'pageTemplate-select d-none'],
            ])
        ;

        $mode = $options['mode'];
        $isAdmin = $options['isAdmin'] ?? false;
        $user = $options['user'] ?? null;

        if ($isAdmin) {
            if ('submit' === $mode) {
                $builder->add('pageType', ChoiceType::class, [
                    'label' => 'Type de page',
                    'attr' => ['class' => 'form-select'],
                    'choices' => [
                        'Page personnelle' => 'personal',
                        'Page de groupe/role' => 'group_role',
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

            $builder->add('groups', Select2EntityType::class, [
                'label' => 'Groupes',
                'class' => Group::class,
                'multiple' => true,
                'required' => false,
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
        } elseif ($user && in_array($mode, ['submit', 'update'])) {
            if ('submit' === $mode) {
                $builder->add('pageType', ChoiceType::class, [
                    'label' => 'Type de page',
                    'attr' => ['class' => 'form-select'],
                    'choices' => [
                        'Page personnelle' => 'personal',
                        'Page de groupe' => 'group',
                    ],
                    'expanded' => true,
                    'multiple' => false,
                    'mapped' => false,
                ]);
            }

            $masterGroups = $user->getUserGroups()->filter(fn ($ug) => UserGroup::ROLE_MASTER === $ug->getRole())->map(fn ($ug) => $ug->getGroup())->toArray();

            $builder->add('allUsers', CheckboxType::class, [
                'label' => 'Tout le monde',
                'required' => false,
                'mapped' => false,
                'attr' => ['class' => 'form-check-input'],
            ]);

            $builder->add('user', HiddenType::class, ['required' => false, 'mapped' => false]);

            $builder->add('groups', Select2EntityType::class, [
                'label' => 'Groupes (Master uniquement)',
                'class' => Group::class,
                'multiple' => true,
                'required' => false,
                'choices' => $masterGroups,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Page::class,
            'mode' => 'submit',
            'isAdmin' => false,
            'user' => null,
        ]);
    }
}
