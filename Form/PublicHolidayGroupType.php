<?php

namespace KimaiPlugin\HolidayBundle\Form;

use KimaiPlugin\HolidayBundle\Entity\PublicHolidayGroup;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PublicHolidayGroupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'holiday.public_holiday.group_name'])
            ->add('country', TextType::class, [
                'label' => 'holiday.public_holiday.country',
                'required' => false,
                'help' => 'holiday.public_holiday.country_help',
            ])
            ->add('region', TextType::class, [
                'label' => 'holiday.public_holiday.region',
                'required' => false,
                'help' => 'holiday.public_holiday.region_help',
            ])
            ->add('excludedNames', TextareaType::class, [
                'label' => 'holiday.public_holiday.excluded_names',
                'required' => false,
                'help' => 'holiday.public_holiday.excluded_names_help',
                'attr' => ['rows' => 6],
            ]);

        // One name per line in the form, a list on the entity.
        $builder->get('excludedNames')->addModelTransformer(new CallbackTransformer(
            static fn (?array $names): string => implode("\n", $names ?? []),
            static fn (?string $text): array => preg_split('/\R/', (string) $text, -1, PREG_SPLIT_NO_EMPTY) ?: [],
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PublicHolidayGroup::class,
            'translation_domain' => 'messages',
        ]);
    }
}
