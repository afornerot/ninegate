<?php

namespace App\Form;

use App\Entity\User;
use App\Form\Type\IconType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Regex;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        ->add('submit', SubmitType::class, [
            'label' => 'Valider',
            'attr' => ['class' => 'btn btn-success no-print me-1'],
        ])

        ->add('username', TextType::class, [
            'label' => 'Login',
            'disabled' => ('submit' != $options['mode']),
        ])

        ->add('email', EmailType::class, [
            'label' => 'Email',
        ])

        ->add('firstname', TextType::class, [
            'label' => 'Prénom',
            'required' => false,
            'attr' => ['class' => 'form-control'],
        ])

        ->add('lastname', TextType::class, [
            'label' => 'Nom',
            'required' => false,
            'attr' => ['class' => 'form-control'],
        ])

        ->add('pseudo', TextType::class, [
            'label' => 'Pseudo',
            'required' => false,
            'attr' => ['class' => 'form-control'],
        ])

        ->add('avatar', IconType::class, [
            'label' => false,
            'required' => false,
            'icon_endpoint' => 'avatar',
            'icon_label' => 'Avatar',
            'icon_upload_url' => '/user/upload/crop01/logo?reportThumb=user_avatar',
        ]);

        if ('profil' != $options['mode']) {
            $builder
            ->add('roles', ChoiceType::class, [
                'choices' => ['ROLE_ADMIN' => 'ROLE_ADMIN', 'ROLE_MASTER' => 'ROLE_MASTER', 'ROLE_USER' => 'ROLE_USER'],
                'multiple' => true,
                'expanded' => true,
            ]);
        }

        if ('SQL' === $options['appModeAuth']) {
            $builder
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'required' => ('submit' == $options['mode'] ? true : false),
                'options' => ['always_empty' => true],
                'first_options' => ['label' => 'Mot de Passe', 'attr' => ['class' => 'form-control', 'style' => 'margin-bottom:15px', 'autocomplete' => 'new-password']],
                'second_options' => ['label' => 'Confirmer Mot de Passe', 'attr' => ['class' => 'form-control', 'style' => 'margin-bottom:15px']],
                'constraints' => [
                    new Regex('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\W).{8,}$/', 'Le mot de passe doit contenir au moins 8 caractères, une lettre majuscule, une lettre minuscule et un caractère spécial.'),
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => static::class,
            'mode' => 'submit',
            'appModeAuth' => 'SQL',
        ]);
    }
}
