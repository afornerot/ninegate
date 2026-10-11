<?php

namespace App\Form;

use App\Entity\Cron;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Cron>
 */
class CronType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('submit', SubmitType::class, [
                'label' => 'Valider',
                'attr' => ['class' => 'btn btn-success'],
            ])

            ->add('command', TextType::class, [
                'label' => 'Commande',
                'disabled' => true,
            ])

            ->add('jsonargument', TextType::class, [
                'label' => 'Argument Commande au format json',
                'disabled' => true,
            ])

            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => Cron::STATUTS,
            ])

            ->add('repeatcall', IntegerType::class, [
                'label' => "Nombre d'éxécution en cas d'echec. Si zéro on le répete à l'infini même si OK",
            ])

            ->add('repeatinterval', IntegerType::class, [
                'label' => 'Interval en seconde entre deux éxécution',
            ])

            ->add('nextexecdate', DateTimeType::class, [
                'label' => 'Prochaine exécution',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Cron::class,
            'mode' => 'update',
        ]);
    }
}
