<?php

namespace KimaiPlugin\HolidayBundle\Controller;

use App\Controller\AbstractController;
use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\TimesheetChange;
use KimaiPlugin\HolidayBundle\Repository\TimesheetChangeRepository;
use KimaiPlugin\HolidayBundle\Service\TimesheetChangePermissions;
use KimaiPlugin\HolidayBundle\Service\TimesheetChangeService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Late timesheet changes: approve or reject, one or several at once.
 */
#[Route(path: '/holiday/timesheet-changes')]
#[IsGranted(TimesheetChangeService::PERMISSION)]
class TimesheetChangeController extends AbstractController
{
    use HolidayUiTrait;

    public const CSRF_ACTION = 'holiday_timesheet_change_action';
    private const LIST_ROUTE = 'holiday_absence_requests';
    private const MAX_SELECTION = 500;

    public function __construct(
        private readonly TimesheetChangeRepository $repository,
        private readonly TimesheetChangeService $service,
        private readonly TimesheetChangePermissions $permissions,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(path: '/approve', name: 'holiday_timesheet_change_approve', methods: ['POST'])]
    public function approve(Request $request): Response
    {
        return $this->decide($request, true);
    }

    #[Route(path: '/reject', name: 'holiday_timesheet_change_reject', methods: ['POST'])]
    public function reject(Request $request): Response
    {
        return $this->decide($request, false);
    }

    private function decide(Request $request, bool $approve): Response
    {
        if (!$this->isCsrfTokenValid(self::CSRF_ACTION, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        /** @var User $current */
        $current = $this->getUser();
        $changes = $this->loadSelected($request);

        // every selected change is checked before the first one is decided
        foreach ($changes as $change) {
            if (!$this->permissions->canDecide($current, $change)) {
                throw $this->createAccessDeniedException();
            }
        }

        $done = 0;
        $skipped = 0;
        $firstError = null;
        foreach ($changes as $change) {
            try {
                $approve ? $this->service->approve($change, $current) : $this->service->reject($change, $current);
                ++$done;
            } catch (\InvalidArgumentException|\RuntimeException $e) {
                $firstError ??= $this->errorKey($e);
                ++$skipped;
            }
        }

        if ($done === 0) {
            $message = $this->translator->trans($firstError ?? 'holiday.error.change_not_open', [], 'flashmessages');

            return $this->actionResult($request, $message, null, self::LIST_ROUTE, [], 422);
        }

        $message = $this->translator->trans($approve ? 'holiday.change.bulk.approved' : 'holiday.change.bulk.rejected', ['%count%' => $done]);
        if ($skipped > 0) {
            $message .= ' ' . $this->translator->trans('holiday.change.bulk.skipped', [
                '%count%' => $skipped,
                '%reason%' => $firstError === null ? '' : $this->translator->trans($firstError, [], 'flashmessages'),
            ]);
        }

        return $this->actionResult($request, $message, null, self::LIST_ROUTE, []);
    }

    /**
     * @return list<TimesheetChange>
     */
    private function loadSelected(Request $request): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $request->request->all('ids')),
            static fn (int $id): bool => $id > 0
        )));
        if ($ids === [] || \count($ids) > self::MAX_SELECTION) {
            throw $this->createNotFoundException('No timesheet changes selected');
        }

        $changes = $this->repository->findBy(['id' => $ids], ['day' => 'ASC']);
        if (\count($changes) !== \count($ids)) {
            throw $this->createNotFoundException('Timesheet change not found');
        }

        return array_values($changes);
    }
}
