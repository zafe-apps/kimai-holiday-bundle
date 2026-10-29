<?php

namespace KimaiPlugin\HolidayBundle\Form;

use App\Form\Type\UserType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * Adds one user to a location. Data: ['user' => User].
 */
class LocationMemberType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('user', UserType::class, [
            'label' => 'holiday.location.member',
            'ignore_users' => $options['members'],
            'constraints' => [new NotNull()],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'messages',
            // users already in this location; other locations' members may be moved
            'members' => [],
        ]);
        $resolver->setAllowedTypes('members', 'array');
    }
}
