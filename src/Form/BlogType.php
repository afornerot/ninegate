<?php

namespace App\Form;

use App\Entity\Blog;
use App\Entity\Group;
use App\Entity\UserGroup;
use App\Form\Type\Select2EntityType;
use App\Form\Type\Select2Type;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BlogType extends AbstractType
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
        ;

        $isAdmin = $options['isAdmin'] ?? false;
        $user = $options['user'] ?? null;

        if ($isAdmin) {
            $builder->add('groups', Select2EntityType::class, [
                'label' => 'Groupes',
                'class' => Group::class,
                'multiple' => true,
                'required' => false,
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
            $masterGroups = $user->getUserGroups()
                ->filter(fn ($ug) => UserGroup::ROLE_MASTER === $ug->getRole())
                ->map(fn ($ug) => $ug->getGroup())
                ->toArray();

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
            'data_class' => Blog::class,
            'mode' => 'submit',
            'isAdmin' => false,
            'user' => null,
        ]);
    }
}
