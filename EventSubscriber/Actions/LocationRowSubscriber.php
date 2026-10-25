<?php

namespace KimaiPlugin\HolidayBundle\EventSubscriber\Actions;

use App\Event\PageActionsEvent;
use App\EventSubscriber\Actions\AbstractActionsSubscriber;
use KimaiPlugin\HolidayBundle\Entity\Location;

/**
 * Row menu of the location table: edit and delete.
 */
final class LocationRowSubscriber extends AbstractActionsSubscriber
{
    public static function getActionName(): string
    {
        return 'holiday_location';
    }

    public function onActions(PageActionsEvent $event): void
    {
        $location = $event->getPayload()['location'] ?? null;
        if (!$location instanceof Location || $location->getId() === null || !$this->isGranted('edit_location')) {
            return;
        }

        $event->addAction('edit', [
            'url' => $this->path('holiday_location_edit', ['id' => $location->getId()]),
            'class' => 'modal-ajax-form',
            'title' => 'edit',
        ]);
        $event->addAction('trash', [
            'url' => $this->path('holiday_location_delete', ['id' => $location->getId()]),
            'class' => 'modal-ajax-form text-red',
            'title' => 'trash',
        ]);
    }
}
