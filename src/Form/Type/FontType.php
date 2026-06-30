<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FontType extends AbstractType
{
    private const FONTS = [
        'ABeeZee',
        'Acme',
        'AlfaSlabOne',
        'Anton',
        'Baloo',
        'CarterOne',
        'Chewy',
        'Courgette',
        'FredokaOne',
        'Gothic',
        'Grandstander',
        'Hack',
        'Justanotherhand',
        'Lato',
        'LexendDeca',
        'LuckiestGuy',
        'Marianne',
        'Overpass',
        'PassionOne',
        'Peacesans',
        'Redressed',
        'Righteous',
        'Roboto',
        'RubikMonoOne',
        'SigmarOne',
        'Signika',
        'Space Mono',
        'Snickles',
        'Teko',
        'Viga',
    ];

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => array_combine(self::FONTS, self::FONTS),
            'attr' => ['class' => 'form-select font-select'],
        ]);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }
}