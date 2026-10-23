<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use KimaiPlugin\HolidayBundle\Entity\PublicHoliday;
use KimaiPlugin\HolidayBundle\Entity\PublicHolidayGroup;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayGroupRepository;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayRepository;
use KimaiPlugin\HolidayBundle\Service\HolidayIcsCatalog;
use KimaiPlugin\HolidayBundle\Service\HolidayImporter;
use KimaiPlugin\HolidayBundle\Service\IcsHolidayParser;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HolidayImporterTest extends TestCase
{
    private const URL = 'https://example.com/at.ics';

    /** @var list<PublicHoliday> */
    private array $saved = [];

    public function testExcludedNameIsSkipped(): void
    {
        $group = $this->group(['Heiliger Abend']);

        $count = $this->importer()->import($group, 2026, null, self::URL);

        $this->assertSame(['Tag der Arbeit'], $this->savedNames());
        $this->assertSame(1, $count);
    }

    public function testExclusionIsCaseInsensitive(): void
    {
        $group = $this->group(['heiliger abend ']);

        $this->importer()->import($group, 2026, null, self::URL);

        $this->assertSame(['Tag der Arbeit'], $this->savedNames());
    }

    public function testUnknownNameIsImported(): void
    {
        $group = $this->group(['Muttertag']);

        $this->importer()->import($group, 2026, null, self::URL);

        $this->assertEqualsCanonicalizing(['Heiliger Abend', 'Tag der Arbeit'], $this->savedNames());
    }

    public function testCatalogDefaultsFillEmptyGroupList(): void
    {
        $group = $this->group([]);

        $this->importer()->import($group, 2026, 'at-de');

        $this->assertContains('Muttertag', $group->getExcludedNames());
        $this->assertContains('Heiligabend', $group->getExcludedNames());
        $this->assertNotContains('Weihnachten', $group->getExcludedNames());
    }

    public function testExistingGroupListIsKept(): void
    {
        $group = $this->group(['Nur das']);

        $this->importer()->import($group, 2026, 'at-de');

        $this->assertSame(['Nur das'], $group->getExcludedNames());
    }

    public function testSyncUsesGroupList(): void
    {
        $group = $this->group(['Heiliger Abend']);
        $group->setIcsUrl(self::URL);
        $group->setIcsFromYear(2026);

        $this->importer()->sync($group);

        $this->assertSame(['Tag der Arbeit'], $this->savedNames());
    }

    /**
     * @param list<string> $excluded
     */
    private function group(array $excluded): PublicHolidayGroup
    {
        $group = new PublicHolidayGroup();
        $group->setName('AT');
        $group->setExcludedNames($excluded);

        return $group;
    }

    /**
     * @return list<string>
     */
    private function savedNames(): array
    {
        return array_map(static fn (PublicHoliday $h): string => $h->getName(), $this->saved);
    }

    private function importer(): HolidayImporter
    {
        $ics = "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nDTSTART;VALUE=DATE:20261224\r\nSUMMARY:Heiliger Abend\r\nEND:VEVENT\r\n"
            . "BEGIN:VEVENT\r\nDTSTART;VALUE=DATE:20260501\r\nSUMMARY:Tag der Arbeit\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

        $holidays = $this->createMock(PublicHolidayRepository::class);
        $holidays->method('findOneByGroupAndDate')->willReturn(null);
        $holidays->method('save')->willReturnCallback(function (PublicHoliday $h): void {
            $this->saved[] = $h;
        });

        return new HolidayImporter(
            $holidays,
            $this->createMock(PublicHolidayGroupRepository::class),
            new MockHttpClient(static fn () => new MockResponse($ics, ['http_code' => 200])),
            new NullLogger(),
            new HolidayIcsCatalog(),
            new IcsHolidayParser(),
        );
    }
}
