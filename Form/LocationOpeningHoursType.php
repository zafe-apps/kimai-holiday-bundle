<?php

namespace KimaiPlugin\HolidayBundle\Form;

use App\Form\Type\TimePickerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * One weekday row: data is ['opensAt' => ?\DateTimeInterface, 'closesAt' => ?\DateTimeInterface].
 * Both empty means closed; the rows are checked and mapped to the entity by LocationType.
 */
class LocationOpeningHoursType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('opensAt', TimePickerType::class, ['label' => 'holiday.location.opens_at', 'required' => false])
            ->add('closesAt', TimePickerType::class, ['label' => 'holiday.location.closes_at', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'messages',
            'label' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'holiday_opening_hours';
    }
}
