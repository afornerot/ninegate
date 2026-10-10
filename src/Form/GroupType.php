<?php

namespace App\Form;

use App\Entity\Group;
use Bnine\FilesBundle\Form\Type\IconUploadType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GroupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $group = $options['data'] ?? null;
        $readOnly = $group instanceof Group && $group->isAnnuaire();

        $builder
            ->add('submit', SubmitType::class, [
                'label' => 'Valider',
                'attr' => ['class' => 'btn btn-success no-print me-1'],
            ]);

        if ($options['isAdmin'] ?? false) {
            $builder->add('type', ChoiceType::class, [
                'label' => 'Type',
                'attr' => ['class' => 'form-select'],
                'disabled' => $readOnly,
                'choices' => [
                    'Organisation' => Group::TYPE_ORGANISATION,
                    'Groupe de Travail' => Group::TYPE_WORK_GROUP,
                ],
            ]);
        }

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom',
                'disabled' => $readOnly,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['class' => 'form-control', 'rows' => 3],
                'required' => false,
            ])
            ->add('logo', IconUploadType::class, [
                'label' => false,
                'required' => false,
                'icon_endpoint' => 'logo',
                'icon_label' => 'Logo',
                'icon_domain' => 'logo',
                'icon_entity_id' => 0,
                'crop' => true,
            ])
            ->add('isOpen', CheckboxType::class, [
                'label' => 'Groupe ouvert',
                'attr' => ['class' => 'form-check-input'],
                'required' => false,
                'disabled' => $readOnly,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Group::class,
            'mode' => 'submit',
            'isAdmin' => false,
        ]);
    }
}
