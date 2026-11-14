<?php

namespace KimaiPlugin\HolidayBundle\EventSubscriber\Actions;

use App\Entity\User;
use App\Event\PageActionsEvent;
use App\EventSubscriber\Actions\AbstractActionsSubscriber;
use KimaiPlugin\HolidayBundle\Controller\TimesheetChangeController;
use KimaiPlugin\HolidayBundle\Entity\TimesheetChange;
use KimaiPlugin\HolidayBundle\Service\TimesheetChangePermissions;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Row menu ("…") of the late timesheet changes on the open requests page.
 */
final class TimesheetChangeRowSubscriber extends AbstractActionsSubscriber
{
    public function __construct(
        AuthorizationCheckerInterface $auth,
        UrlGeneratorInterface $urlGenerator,
        private readonly TimesheetChangePermissions $permissions,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct($auth, $urlGenerator);
    }

    public static function getActionName(): string
    {
        return 'holiday_timesheet_change';
    }

    public function onActions(PageActionsEvent $event): void
    {
        $change = $event->getPayload()['change'] ?? null;
        $user = $event->getUser();
        if (!$change instanceof TimesheetChange || $change->getId() === null || !$user instanceof User) {
            return;
        }
        if (!$change->isOpen() || !$this->permissions->canDecide($user, $change)) {
            return;
        }

        $token = $this->csrfTokenManager->getToken(TimesheetChangeController::CSRF_ACTION)->getValue();

        $event->addAction('success', [
            'url' => '#',
            'title' => 'approve',
            'attr' => [
                'data-kpu-post' => $this->path('holiday_timesheet_change_approve'),
                'data-kpu-token' => $token,
                'data-kpu-ids' => (string) $change->getId(),
            ],
        ]);
        // a rejection rewrites the entry and cannot be undone with one click: ask first
        $event->addAction('rejected', [
            'url' => '#',
            'title' => 'reject',
            'attr' => [
                'data-kpu-question' => $this->translator->trans('holiday.change.reject_question'),
                'data-kpu-post' => $this->path('holiday_timesheet_change_reject'),
                'data-kpu-token' => $token,
                'data-kpu-ids' => (string) $change->getId(),
            ],
        ]);
    }
}
