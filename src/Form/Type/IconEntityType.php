<?php

namespace App\Form\Type;

use App\Entity\Icon;
use App\Repository\IconRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class IconEntityType extends AbstractType
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private IconRepository $iconRepository,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => ['class' => 'icon-entity-input'],
            'label' => 'Icône',
            'required' => false,
            'invalid_message' => 'Icône invalide.',
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            function (?Icon $icon): ?string {
                return $icon?->getId() !== null ? (string) $icon->getId() : null;
            },
            function (?string $id): ?Icon {
                if (empty($id)) {
                    return null;
                }
                return $this->iconRepository->find((int) $id);
            }
        ));
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        $icons = $this->iconRepository->createQueryBuilder('i')
            ->where('i.user IS NULL OR i.user = :user')
            ->setParameter('user', $user)
            ->orderBy('i.route', 'ASC')
            ->getQuery()
            ->getResult();

        $view->vars['icons'] = $icons;
    }

    public function getParent(): string
    {
        return HiddenType::class;
    }
}
