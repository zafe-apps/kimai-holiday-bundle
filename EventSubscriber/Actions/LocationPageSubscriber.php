<?php

namespace KimaiPlugin\HolidayBundle\EventSubscriber\Actions;

use App\Event\PageActionsEvent;
use App\EventSubscriber\Actions\AbstractActionsSubscriber;

/**
 * Page action of the location list: create.
 */
final class LocationPageSubscriber extends AbstractActionsSubscriber
{
    public static function getActionName(): string
    {
        return 'holiday_locations';
    }

    public function onActions(PageActionsEvent $event): void
    {
        if (!$this->isGranted('edit_location')) {
            return;
        }

        $event->addAction('create', [
            'url' => $this->path('holiday_location_create'),
            'class' => 'modal-ajax-form',
            'title' => 'holiday.location.create',
            'translation_domain' => 'messages',
        ]);
    }
}
