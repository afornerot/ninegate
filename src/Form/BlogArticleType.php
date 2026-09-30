<?php

namespace App\Form;

use App\Entity\BlogArticle;
use Bnine\FilesBundle\Form\Type\SelectFileType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BlogArticleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('image', SelectFileType::class, [
                'label' => 'Image à la une',
                'mode' => 'single',
                'access' => 'write',
                'layout' => 'inline',
                'domain' => 'blog',
                'entity_id' => $options['blogId'],
                'required' => false,
                'show_name' => true,
                'max_height' => 300,
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Contenu (Markdown)',
                'attr' => [
                    'class' => 'form-control easymde-textarea',
                    'rows' => 15,
                ],
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BlogArticle::class,
            'articleId' => 0,
            'blogId' => 0,
        ]);
    }
}
