<?php

namespace App\Form;

use App\Entity\Config;
use App\Form\Type\ColorType;
use App\Form\Type\FontType;
use App\Form\Type\IconType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $config = $builder->getData();
        $type = $config instanceof Config ? $config->getType() : Config::TYPE_STRING;

        $fieldType = match ($type) {
            Config::TYPE_BOOL => CheckboxType::class,
            Config::TYPE_INT => NumberType::class,
            Config::TYPE_FLOAT => NumberType::class,
            Config::TYPE_DATETIME => DateTimeType::class,
            Config::TYPE_TEXT => TextareaType::class,
            Config::TYPE_COLOR => ColorType::class,
            Config::TYPE_FONT => FontType::class,
            Config::TYPE_LOGO => IconType::class,
            default => TextType::class,
        };

        $fieldOptions = [
            'label' => 'Valeur',
            'required' => false,
            'data' => $config instanceof Config ? $config->getRawValue() : null,
        ];

        if (Config::TYPE_LOGO === $type) {
            $fieldOptions['icon_endpoint'] = 'logo';
            $fieldOptions['icon_label'] = 'Logo';
            $fieldOptions['icon_empty_preview'] = $config instanceof Config ? $config->getValue() : null;
            $fieldOptions['icon_upload_url'] = '/user/upload/crop01/logo?reportThumb=config_rawValue';
        }

        if (Config::TYPE_BOOL === $type) {
            $fieldOptions['label'] = 'Actif';
            $fieldOptions['attr'] = ['class' => 'form-check-input'];
        }

        if (Config::TYPE_DATETIME === $type) {
            $fieldOptions['input'] = 'datetime_immutable';
            $fieldOptions['required'] = false;
        }

        if (Config::TYPE_INT === $type || Config::TYPE_FLOAT === $type) {
            $fieldOptions['input'] = Config::TYPE_FLOAT === $type ? 'decimal' : 'integer';
        }

        if (Config::TYPE_LOGO === $type) {
            $fieldOptions['icon_empty_preview'] = $config->getValue();
            $fieldOptions['icon_upload_url'] = '/user/upload/crop01/logo?reportThumb=config_rawValue';
        }

        $builder
            ->add('submit', SubmitType::class, [
                'label' => 'Valider',
                'attr' => ['class' => 'btn btn-success no-print me-1'],
            ])
            ->add('rawValue', $fieldType, $fieldOptions + ['mapped' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Config::class,
        ]);
    }
}
