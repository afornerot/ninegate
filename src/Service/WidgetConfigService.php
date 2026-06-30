<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

class WidgetConfigService
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Build a form for widget config based on Widget.config field definitions.
     *
     * Config format:
     * {
     *   "fieldName": {
     *     "type": "entity|choice|checkbox|text|integer",
     *     "label": "Label",
     *     ...type-specific options
     *   }
     * }
     */
    public function buildConfigForm(array $fieldDefinitions, array $data, string $name = 'widget_config'): ?FormInterface
    {
        if (empty($fieldDefinitions)) {
            return null;
        }

        // Resolve entity IDs to objects for EntityType fields
        foreach ($fieldDefinitions as $fieldName => $definition) {
            if (($definition['type'] ?? '') === 'entity' && isset($data[$fieldName]) && is_numeric($data[$fieldName])) {
                $class = $definition['class'];
                $data[$fieldName] = $this->em->getRepository($class)->find((int) $data[$fieldName]);
            }
        }

        $builder = $this->formFactory->createNamedBuilder($name, 'Symfony\Component\Form\Extension\Core\Type\FormType', $data, [
            'csrf_protection' => false,
        ]);

        foreach ($fieldDefinitions as $fieldName => $definition) {
            $type = $definition['type'] ?? 'text';
            $label = $definition['label'] ?? $fieldName;
            $required = $definition['required'] ?? false;

            match ($type) {
                'entity' => $builder->add($fieldName, EntityType::class, [
                    'label' => $label,
                    'class' => $definition['class'],
                    'required' => $required,
                    'placeholder' => $definition['placeholder'] ?? null,
                    'attr' => ['class' => 'form-select'],
                ]),
                'choice' => $builder->add($fieldName, ChoiceType::class, [
                    'label' => $label,
                    'choices' => $definition['choices'] ?? [],
                    'required' => $required,
                    'attr' => ['class' => 'form-select'],
                ]),
                'checkbox' => $builder->add($fieldName, CheckboxType::class, [
                    'label' => $label,
                    'required' => false,
                ]),
                'integer' => $builder->add($fieldName, IntegerType::class, [
                    'label' => $label,
                    'required' => $required,
                    'attr' => ['class' => 'form-control', 'min' => $definition['min'] ?? 0],
                ]),
                'textarea' => $builder->add($fieldName, TextareaType::class, [
                    'label' => $label,
                    'required' => $required,
                    'attr' => ['class' => 'form-control', 'rows' => $definition['rows'] ?? 3],
                ]),
                default => $builder->add($fieldName, TextType::class, [
                    'label' => $label,
                    'required' => $required,
                    'attr' => ['class' => 'form-control'],
                ]),
            };
        }

        return $builder->getForm();
    }

    /**
     * Extract default values from Widget.config field definitions.
     */
    public function getDefaults(array $fieldDefinitions): array
    {
        $defaults = [];
        foreach ($fieldDefinitions as $fieldName => $definition) {
            $defaults[$fieldName] = $definition['default'] ?? match ($definition['type'] ?? 'text') {
                'checkbox' => false,
                'integer' => 0,
                default => null,
            };
        }
        return $defaults;
    }
}
