<?php

namespace App\Form;

use App\Form\Type\IconEntityType;
use App\Entity\PageWidget;
use App\Entity\Widget;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use App\Form\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PageWidgetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $mode = $options['mode'];

        $builder
            ->add('submit', SubmitType::class, [
                'label' => 'Valider',
                'attr' => ['class' => 'btn btn-success no-print me-1'],
            ])
        ;

        if ('submit' === $mode) {
            $builder->add('widget', EntityType::class, [
                'label' => 'Widget',
                'class' => Widget::class,
                'required' => false,
                'placeholder' => '-- Sélectionnez un widget --',
                'attr' => ['class' => 'form-select'],
            ]);
        }

        if ('update' === $mode) {
            $builder
                ->add('title', TextType::class, [
                    'label' => 'Titre',
                    'attr' => ['class' => 'form-control'],
                    'required' => false,
                ])

                ->add('titleBgColor', ColorType::class, [
                    'label' => 'Couleur de fond du titre',
                    'required' => false,
                ])

                ->add('titleFontColor', ColorType::class, [
                    'label' => 'Couleur de police du titre',
                    'required' => false,
                ])

                ->add('bodyBgColor', ColorType::class, [
                    'label' => 'Couleur de fond du corps',
                    'required' => false,
                ])

                ->add('bodyFontColor', ColorType::class, [
                    'label' => 'Couleur de police du corps',
                    'required' => false,
                ])

                ->add('withBorder', CheckboxType::class, [
                    'label' => 'Avec bordure',
                    'required' => false,
                ])

                ->add('withTitle', CheckboxType::class, [
                    'label' => 'Avec titre',
                    'required' => false,
                ])

                ->add('height', IntegerType::class, [
                    'label' => 'Hauteur (px)',
                    'attr' => ['class' => 'form-control', 'min' => 0],
                    'required' => false,
                ])

                ->add('icon', IconEntityType::class)
            ;
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PageWidget::class,
            'mode' => 'submit',
            'isAdmin' => false,
            'csrf_protection' => false,
        ]);
    }
}
