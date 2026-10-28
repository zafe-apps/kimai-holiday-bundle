<?php

namespace KimaiPlugin\HolidayBundle\Controller;

use App\Controller\AbstractController;
use App\Entity\User;
use App\Utils\PageSetup;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use KimaiPlugin\HolidayBundle\Entity\ExtraShift;
use KimaiPlugin\HolidayBundle\Form\ExtraShiftType;
use KimaiPlugin\HolidayBundle\Service\ExtraShiftPermissions;
use KimaiPlugin\HolidayBundle\Service\ExtraShiftService;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: '/holiday/extra-shifts')]
#[IsGranted(new Expression('is_granted("extra_shift_own") or is_granted("extra_shift_other")'))]
class ExtraShiftController extends AbstractController
{
    use HolidayUiTrait;

    public const CSRF_ID = 'holiday_extra_shift';

    public function __construct(
        private readonly ExtraShiftService $service,
        private readonly ExtraShiftPermissions $permissions,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(path: '/create', name: 'holiday_extra_shift_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        /** @var User $current */
        $current = $this->getUser();
        $chooseUser = $this->permissions->canCreateForOthers();

        $data = ['user' => $current, 'date' => $this->prefillDate($request), 'comment' => null];
        $form = $this->createForm(ExtraShiftType::class, $data, [
            'action' => $this->generateUrl('holiday_extra_shift_create'),
            'method' => 'POST',
            'attr' => ['data-form-event' => AbsenceController::UPDATE_EVENT],
            'choose_user' => $chooseUser,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $chooseUser ? $form->get('user')->getData() : $current;
            $date = $form->get('date')->getData();
            if (!$user instanceof User || !$date instanceof \DateTimeImmutable) {
                throw $this->createNotFoundException();
            }
            if (!$this->permissions->canCreateFor($user)) {
                throw $this->createAccessDeniedException();
            }

            try {
                $this->service->create($user, $date, $form->get('comment')->getData(), $current);
                $this->flashSuccess('holiday.extra_shift.created');

                return $this->formSuccess($request, 'holiday_absence_calendar');
            } catch (\InvalidArgumentException $e) {
                $this->addFormError($form->get('date'), $this->errorKey($e));
            } catch (UniqueConstraintViolationException) {
                // lost the race against a second request for the same day
                $this->addFormError($form->get('date'), 'holiday.error.extra_shift_exists');
            }
        }

        $page = new PageSetup('holiday.extra_shift.create');

        return $this->render('@Holiday/extra_shift/form.html.twig', [
            'page_setup' => $page,
            'form' => $form->createView(),
            'title' => $this->translator->trans('holiday.extra_shift.create'),
            'back' => $this->generateUrl('holiday_absence_calendar'),
            'submit' => 'action.save',
        ]);
    }

    #[Route(path: '/{id}/delete', name: 'holiday_extra_shift_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, ExtraShift $shift): Response
    {
        if (!$this->isCsrfTokenValid(self::CSRF_ID, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }
        if (!$this->permissions->canDelete($shift)) {
            throw $this->createAccessDeniedException();
        }

        $this->service->delete($shift);

        return $this->actionResult($request, $this->translator->trans('holiday.extra_shift.deleted'), null, 'holiday_absence_calendar', []);
    }

    private function prefillDate(Request $request): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $request->query->get('date'));

        return $date !== false ? $date : new \DateTimeImmutable('today');
    }

    private function addFormError(\Symfony\Component\Form\FormInterface $field, ?string $key): void
    {
        $field->addError(new FormError($this->translator->trans($key ?? 'holiday.error.extra_shift_exists', [], 'flashmessages')));
    }
}
