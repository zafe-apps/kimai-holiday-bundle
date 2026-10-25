<?php

namespace KimaiPlugin\HolidayBundle\Form;

use App\Form\Type\DatePickerType;
use App\Form\Type\TimePickerType;
use KimaiPlugin\HolidayBundle\Entity\LocationException;
use KimaiPlugin\HolidayBundle\Enum\LocationExceptionKind;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class LocationExceptionType extends AbstractType
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date', DatePickerType::class, [
                'label' => 'holiday.location.exception.date',
                'input' => 'datetime_immutable',
            ])
            ->add('kind', EnumType::class, [
                'label' => 'holiday.location.exception.kind',
                'class' => LocationExceptionKind::class,
                'choice_label' => static fn (LocationExceptionKind $kind): string => $kind->label(),
            ])
            ->add('opensAt', TimePickerType::class, [
                'label' => 'holiday.location.opens_at',
                'required' => false,
                'help' => 'holiday.location.exception.times_help',
                'setter' => static fn (LocationException $e, ?\DateTimeInterface $time) => $e->setOpensAt(self::timeOf($time)),
            ])
            ->add('closesAt', TimePickerType::class, [
                'label' => 'holiday.location.closes_at',
                'required' => false,
                'setter' => static fn (LocationException $e, ?\DateTimeInterface $time) => $e->setClosesAt(self::timeOf($time)),
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'holiday.location.exception.comment',
                'required' => false,
                'attr' => ['rows' => 2],
            ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $this->checkTimes($event);
        }, -10);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LocationException::class,
            'translation_domain' => 'messages',
        ]);
    }

    /** The picker hands out a DateTime, the entity wants the immutable time of day. */
    private static function timeOf(?\DateTimeInterface $time): ?\DateTimeImmutable
    {
        return $time === null ? null : (\DateTimeImmutable::createFromFormat('!H:i', $time->format('H:i')) ?: null);
    }

    private function checkTimes(FormEvent $event): void
    {
        $exception = $event->getData();
        $form = $event->getForm();
        if (!$exception instanceof LocationException) {
            return;
        }

        // A closed day has no hours.
        if ($exception->getKind() === LocationExceptionKind::CLOSED) {
            $exception->setOpensAt(null)->setClosesAt(null);

            return;
        }

        $opens = $exception->getOpensAt();
        $closes = $exception->getClosesAt();
        if (($opens === null) !== ($closes === null)) {
            $form->get($opens === null ? 'opensAt' : 'closesAt')->addError(
                new FormError($this->translator->trans('holiday.error.exception_times_incomplete', [], 'flashmessages'))
            );

            return;
        }
        if ($opens !== null && $closes !== null && $closes <= $opens) {
            $form->get('closesAt')->addError(new FormError($this->translator->trans('holiday.location.hours_order', [], 'messages')));
        }
    }
}
