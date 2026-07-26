<?php

namespace App\Form;

use App\Entity\Calendar;
use App\Entity\CalendarEvent;
use App\Form\Type\ColorType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CalendarEventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description (Markdown)',
                'attr' => [
                    'class' => 'form-control easymde-textarea',
                    'rows' => 10,
                ],
                'required' => false,
            ])
            ->add('startDate', DateTimeType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control', 'type' => 'datetime-local'],
            ])
            ->add('endDate', DateTimeType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'form-control', 'type' => 'datetime-local'],
            ])
            ->add('allDay', CheckboxType::class, [
                'label' => 'Toute la journée',
                'required' => false,
            ])
            ->add('color', ColorType::class, [
                'label' => 'Couleur',
                'required' => false,
            ])
            ->add('calendar', EntityType::class, [
                'label' => 'Calendrier',
                'class' => Calendar::class,
                'required' => true,
                'attr' => ['class' => 'form-select'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CalendarEvent::class,
        ]);
    }
}
