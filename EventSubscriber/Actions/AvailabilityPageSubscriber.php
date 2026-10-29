<?php

namespace KimaiPlugin\HolidayBundle\EventSubscriber\Actions;

use App\Event\PageActionsEvent;
use App\EventSubscriber\Actions\AbstractActionsSubscriber;

/**
 * Page action of the availability view: enter an extra shift.
 */
final class AvailabilityPageSubscriber extends AbstractActionsSubscriber
{
    public static function getActionName(): string
    {
        return 'holiday_availability';
    }

    public function onActions(PageActionsEvent $event): void
    {
        $payload = $event->getPayload();
        if (!($payload['can_create'] ?? false)) {
            return;
        }

        $event->addAction('create', [
            'url' => $this->path('holiday_extra_shift_create', ['date' => $payload['date'] ?? null]),
            'class' => 'modal-ajax-form',
            'title' => 'holiday.extra_shift.create',
            'translation_domain' => 'messages',
        ]);
    }
}
