<?php

namespace KimaiPlugin\HolidayBundle\Controller;

use App\Controller\AbstractController;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Utils\PageSetup;
use KimaiPlugin\HolidayBundle\Entity\Location;
use KimaiPlugin\HolidayBundle\Entity\LocationException;
use KimaiPlugin\HolidayBundle\Form\LocationExceptionType;
use KimaiPlugin\HolidayBundle\Form\LocationMemberType;
use KimaiPlugin\HolidayBundle\Form\LocationType;
use KimaiPlugin\HolidayBundle\Repository\LocationExceptionRepository;
use KimaiPlugin\HolidayBundle\Repository\LocationRepository;
use KimaiPlugin\HolidayBundle\Repository\LocationUserRepository;
use KimaiPlugin\HolidayBundle\Service\LocationPermissions;
use KimaiPlugin\HolidayBundle\Service\OpeningHoursSummary;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: '/holiday/locations')]
#[IsGranted('view_location')]
class LocationController extends AbstractController
{
    use HolidayUiTrait;

    public const CSRF_ID = 'holiday_location';

    public function __construct(
        private readonly LocationRepository $locations,
        private readonly LocationExceptionRepository $exceptions,
        private readonly LocationUserRepository $members,
        private readonly LocationPermissions $permissions,
        private readonly OpeningHoursSummary $summary,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(path: '', name: 'holiday_locations', methods: ['GET'])]
    public function index(): Response
    {
        $locations = $this->locations->findAllOrdered();
        $today = new \DateTimeImmutable('today');

        $dayNames = [];
        foreach (range(1, 7) as $weekday) {
            $dayNames[$weekday] = $this->translator->trans('holiday.weekday.short.' . $weekday);
        }

        $rows = [];
        foreach ($locations as $location) {
            $upcoming = array_values(array_filter(
                $location->getExceptions()->toArray(),
                static fn (LocationException $e): bool => $e->getDate() !== null && $e->getDate() >= $today
            ));
            usort($upcoming, static fn (LocationException $a, LocationException $b): int => $a->getDate() <=> $b->getDate());

            $rows[] = [
                'location' => $location,
                'hours' => $this->summary->format($location, $dayNames),
                'members' => $this->members->findUsersOf($location),
                'exceptions' => $upcoming,
            ];
        }

        $page = new PageSetup('holiday.page.locations');
        $page->setActionName('holiday_locations');
        $page->setActionPayload(['can_edit' => $this->permissions->canEdit()]);
        $page->setHelp($this->helpUrl('locations'));

        return $this->render('@Holiday/location/index.html.twig', [
            'page_setup' => $page,
            'rows' => $rows,
            'can_edit' => $this->permissions->canEdit(),
            'csrf_id' => self::CSRF_ID,
        ]);
    }

    #[Route(path: '/create', name: 'holiday_location_create', methods: ['GET', 'POST'])]
    #[IsGranted('edit_location')]
    public function create(Request $request): Response
    {
        $location = new Location();
        $form = $this->modalForm(LocationType::class, $location, $this->generateUrl('holiday_location_create'));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->locations->save($location);

            return $this->formSuccess($request, 'holiday_locations');
        }

        return $this->renderModal($form, 'holiday.location.create');
    }

    #[Route(path: '/{id}/edit', name: 'holiday_location_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('edit_location')]
    public function edit(Request $request, Location $location): Response
    {
        $form = $this->modalForm(LocationType::class, $location, $this->generateUrl('holiday_location_edit', ['id' => $location->getId()]));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->locations->save($location);

            return $this->formSuccess($request, 'holiday_locations');
        }

        return $this->renderModal($form, 'holiday.location.edit', $location->getName());
    }

    #[Route(path: '/{id}/delete', name: 'holiday_location_delete', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('edit_location')]
    public function delete(Request $request, Location $location): Response
    {
        if ($request->isMethod('POST')) {
            $this->assertCsrf($request);
            // Hours, exceptions and members go with it (CASCADE); extra shifts and absences stay.
            $this->locations->remove($location);
            $this->flashSuccess('action.delete.success');

            return $this->formSuccess($request, 'holiday_locations');
        }

        $form = $this->container->get('form.factory')->createNamed('', FormType::class, null, [
            'action' => $this->generateUrl('holiday_location_delete', ['id' => $location->getId()]),
            'method' => 'POST',
            'csrf_field_name' => '_token',
            'csrf_token_id' => self::CSRF_ID,
            'attr' => ['data-form-event' => AbsenceController::UPDATE_EVENT],
        ]);

        $page = new PageSetup('holiday.menu.locations');

        return $this->render('@Holiday/public_holiday/delete.html.twig', [
            'page_setup' => $page,
            'form' => $form->createView(),
            'subject' => $location,
            'message' => 'holiday.location.delete_message',
            'back' => $this->generateUrl('holiday_locations'),
        ]);
    }

    #[Route(path: '/{id}/exception/create', name: 'holiday_location_exception_create', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('edit_location')]
    public function createException(Request $request, Location $location): Response
    {
        $exception = new LocationException();
        $exception->setDate(new \DateTimeImmutable('today'));
        $form = $this->modalForm(LocationExceptionType::class, $exception, $this->generateUrl('holiday_location_exception_create', ['id' => $location->getId()]));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $date = $exception->getDate();
            if ($date !== null && $this->exceptions->findOneOn($location, $date) !== null) {
                $form->get('date')->addError(new FormError($this->translator->trans('holiday.error.exception_exists', [], 'flashmessages')));
            } else {
                $location->addException($exception);

                try {
                    $this->exceptions->save($exception);
                } catch (UniqueConstraintViolationException) {
                    // lost the race against a second request for the same date
                    $form->get('date')->addError(new FormError($this->translator->trans('holiday.error.exception_exists', [], 'flashmessages')));

                    return $this->renderModal($form, 'holiday.location.exception.create', $location->getName());
                }

                return $this->formSuccess($request, 'holiday_locations');
            }
        }

        return $this->renderModal($form, 'holiday.location.exception.create', $location->getName());
    }

    #[Route(path: '/exception/{id}/delete', name: 'holiday_location_exception_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('edit_location')]
    public function deleteException(Request $request, LocationException $exception): Response
    {
        $this->assertCsrf($request);
        $this->exceptions->remove($exception);

        return $this->actionResult($request, $this->translator->trans('action.delete.success', [], 'flashmessages'), null, 'holiday_locations', []);
    }

    #[Route(path: '/{id}/member/add', name: 'holiday_location_member_add', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('edit_location')]
    public function addMember(Request $request, Location $location): Response
    {
        $form = $this->modalForm(LocationMemberType::class, null, $this->generateUrl('holiday_location_member_add', ['id' => $location->getId()]), [
            'members' => $this->members->findUsersOf($location),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->get('user')->getData();
            if ($user instanceof User) {
                $this->members->assign($user, $location);
            }

            return $this->formSuccess($request, 'holiday_locations');
        }

        return $this->renderModal($form, 'holiday.location.member_add', $location->getName());
    }

    #[Route(path: '/member/{userId}/remove', name: 'holiday_location_member_remove', methods: ['POST'], requirements: ['userId' => '\d+'])]
    #[IsGranted('edit_location')]
    public function removeMember(Request $request, int $userId, UserRepository $users): Response
    {
        $this->assertCsrf($request);
        $user = $users->find($userId);
        if ($user instanceof User) {
            $this->members->unassign($user);
        }

        return $this->actionResult($request, $this->translator->trans('holiday.location.member_removed'), null, 'holiday_locations', []);
    }

    private function modalForm(string $type, mixed $data, string $action, array $options = []): FormInterface
    {
        return $this->createForm($type, $data, array_merge([
            'action' => $action,
            'method' => 'POST',
            'attr' => ['data-form-event' => AbsenceController::UPDATE_EVENT],
        ], $options));
    }

    private function renderModal(FormInterface $form, string $title, ?string $context = null): Response
    {
        $page = new PageSetup($title);
        $page->setHelp($this->helpUrl('locations'));
        $label = $this->translator->trans($title);

        return $this->render('@Holiday/location/form.html.twig', [
            'page_setup' => $page,
            'form' => $form->createView(),
            'title' => $context !== null ? $label . ' · ' . $context : $label,
            'back' => $this->generateUrl('holiday_locations'),
            'submit' => 'action.save',
        ]);
    }

    private function assertCsrf(Request $request): void
    {
        if (!$this->isCsrfTokenValid(self::CSRF_ID, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }
    }
}
