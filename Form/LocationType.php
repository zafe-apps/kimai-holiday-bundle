<?php

namespace KimaiPlugin\HolidayBundle\Form;

use KimaiPlugin\HolidayBundle\Entity\Location;
use KimaiPlugin\HolidayBundle\Entity\LocationOpeningHours;
use KimaiPlugin\HolidayBundle\Entity\PublicHolidayGroup;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

class LocationType extends AbstractType
{
    private const WEEKDAYS = 7;

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'holiday.location.name',
                'constraints' => [new NotBlank(), new Length(max: 100)],
            ])
            ->add('publicHolidayGroup', EntityType::class, [
                'label' => 'holiday.location.public_holiday_group',
                'help' => 'holiday.location.public_holiday_group_help',
                'class' => PublicHolidayGroup::class,
                'choice_label' => 'name',
                'choice_translation_domain' => false,
                'constraints' => [new NotBlank()],
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'holiday.location.active',
                'required' => false,
            ])
            ->add('openingHours', CollectionType::class, [
                'label' => 'holiday.location.opening_hours',
                'help' => 'holiday.location.opening_hours_help',
                'mapped' => false,
                'entry_type' => LocationOpeningHoursType::class,
                'allow_add' => false,
                'allow_delete' => false,
            ]);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event): void {
            $location = $event->getData();
            $event->getForm()->get('openingHours')->setData($this->rowsOf($location instanceof Location ? $location : null));
        });

        // Lower priority than Symfony's validation listener, so field errors are known here.
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $this->applyOpeningHours($event);
        }, -10);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Location::class,
            'translation_domain' => 'messages',
        ]);
    }

    /**
     * Seven rows Monday to Sunday, keys 0-6.
     *
     * @return list<array{opensAt: ?\DateTimeImmutable, closesAt: ?\DateTimeImmutable}>
     */
    private function rowsOf(?Location $location): array
    {
        $rows = [];
        foreach (range(1, self::WEEKDAYS) as $weekday) {
            $hours = $location?->getOpeningHoursFor($weekday);
            $rows[] = ['opensAt' => $hours?->getOpensAt(), 'closesAt' => $hours?->getClosesAt()];
        }

        return $rows;
    }

    private function applyOpeningHours(FormEvent $event): void
    {
        $form = $event->getForm();
        $location = $event->getData();
        if (!$location instanceof Location || !$form->isSubmitted()) {
            return;
        }

        $entities = [];
        foreach ($form->get('openingHours') as $index => $row) {
            $opens = $this->timeOf($row->get('opensAt')->getData());
            $closes = $this->timeOf($row->get('closesAt')->getData());

            // both empty = closed
            if ($opens === null && $closes === null) {
                continue;
            }
            if ($opens === null || $closes === null) {
                $this->fail($row, 'holiday.location.hours_incomplete');

                continue;
            }
            if ($closes <= $opens) {
                $this->fail($row, 'holiday.location.hours_order');

                continue;
            }

            $entities[] = (new LocationOpeningHours())
                ->setWeekday((int) $index + 1)
                ->setOpensAt($opens)
                ->setClosesAt($closes);
        }

        if ($form->get('openingHours')->getErrors(true)->count() === 0) {
            $location->setOpeningHours($entities);
        }
    }

    private function fail(FormInterface $row, string $key): void
    {
        $row->get('closesAt')->addError(new FormError($this->translator->trans($key, [], 'messages')));
    }

    /** Time of day without the date of the picker's DateTime. */
    private function timeOf(mixed $value): ?\DateTimeImmutable
    {
        if (!$value instanceof \DateTimeInterface) {
            return null;
        }

        return \DateTimeImmutable::createFromFormat('!H:i', $value->format('H:i')) ?: null;
    }
}
