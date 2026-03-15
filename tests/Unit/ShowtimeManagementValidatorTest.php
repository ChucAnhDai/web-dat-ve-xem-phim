<?php

namespace Tests\Unit;

use App\Validators\ShowtimeManagementValidator;
use PHPUnit\Framework\TestCase;

class ShowtimeManagementValidatorTest extends TestCase
{
    public function testValidatePayloadRejectsInvalidIdsDateTimePriceAndStatus(): void
    {
        $validator = new ShowtimeManagementValidator();

        $result = $validator->validatePayload([
            'movie_id' => 'abc',
            'room_id' => '-2',
            'show_date' => '2026-02-30',
            'start_time' => '24:61',
            'price' => '-10',
            'status' => 'invalid',
        ]);

        $this->assertArrayHasKey('movie_id', $result['errors']);
        $this->assertArrayHasKey('room_id', $result['errors']);
        $this->assertArrayHasKey('show_date', $result['errors']);
        $this->assertArrayHasKey('start_time', $result['errors']);
        $this->assertArrayHasKey('price', $result['errors']);
        $this->assertArrayHasKey('status', $result['errors']);
    }

    public function testValidatePayloadNormalizesTimeAndOptionalEnums(): void
    {
        $validator = new ShowtimeManagementValidator();

        $result = $validator->validatePayload([
            'movie_id' => '4',
            'room_id' => '8',
            'show_date' => '2026-03-20',
            'start_time' => '19:15',
            'price' => '95000.50',
            'status' => 'PUBLISHED',
            'presentation_type' => 'IMAX',
            'language_version' => 'DUBBED',
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame(4, $result['data']['movie_id']);
        $this->assertSame(8, $result['data']['room_id']);
        $this->assertSame('2026-03-20', $result['data']['show_date']);
        $this->assertSame('19:15:00', $result['data']['start_time']);
        $this->assertSame(95000.5, $result['data']['price']);
        $this->assertSame('published', $result['data']['status']);
        $this->assertSame('imax', $result['data']['presentation_type']);
        $this->assertSame('dubbed', $result['data']['language_version']);
    }

    public function testNormalizePublicFiltersDropsInvalidValuesAndClampsPagination(): void
    {
        $validator = new ShowtimeManagementValidator();

        $result = $validator->normalizePublicFilters([
            'page' => '0',
            'per_page' => '999',
            'movie_id' => '9',
            'cinema_id' => 'bad',
            'city' => ' Ha Noi ',
            'show_date' => '2026-03-21',
        ]);

        $this->assertSame(1, $result['page']);
        $this->assertSame(100, $result['per_page']);
        $this->assertSame(9, $result['movie_id']);
        $this->assertNull($result['cinema_id']);
        $this->assertSame('Ha Noi', $result['city']);
        $this->assertSame('2026-03-21', $result['show_date']);
    }

    public function testNormalizeAdminFiltersDefaultsToActiveScopeAndSupportsArchivedView(): void
    {
        $validator = new ShowtimeManagementValidator();

        $defaultFilters = $validator->normalizeAdminFilters([
            'status' => '',
        ]);
        $archivedFilters = $validator->normalizeAdminFilters([
            'scope' => 'archived',
            'status' => 'published',
        ]);

        $this->assertSame('active', $defaultFilters['scope']);
        $this->assertNull($defaultFilters['status']);

        $this->assertSame('archived', $archivedFilters['scope']);
        $this->assertNull($archivedFilters['status']);
    }
}
