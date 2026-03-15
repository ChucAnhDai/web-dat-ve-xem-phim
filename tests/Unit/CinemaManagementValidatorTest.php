<?php

namespace Tests\Unit;

use App\Validators\CinemaManagementValidator;
use PHPUnit\Framework\TestCase;

class CinemaManagementValidatorTest extends TestCase
{
    public function testValidateCinemaPayloadRejectsInvalidPhoneCoordinatesAndStatus(): void
    {
        $validator = new CinemaManagementValidator();

        $result = $validator->validateCinemaPayload([
            'name' => 'Demo Cinema',
            'city' => 'HCM',
            'address' => '123 Demo Street',
            'status' => 'invalid-status',
            'support_phone' => 'abc',
            'opening_time' => '25:00',
            'latitude' => '100.5',
            'longitude' => '190.1',
        ]);

        $this->assertArrayHasKey('status', $result['errors']);
        $this->assertArrayHasKey('support_phone', $result['errors']);
        $this->assertArrayHasKey('opening_time', $result['errors']);
        $this->assertArrayHasKey('latitude', $result['errors']);
        $this->assertArrayHasKey('longitude', $result['errors']);
    }

    public function testValidateRoomPayloadNormalizesEnumsAndNumbers(): void
    {
        $validator = new CinemaManagementValidator();

        $result = $validator->validateRoomPayload([
            'cinema_id' => '5',
            'room_name' => ' Hall 4 ',
            'room_type' => 'IMAX',
            'screen_label' => ' Screen 4 ',
            'projection_type' => 'LASER',
            'sound_profile' => 'DOLBY_ATMOS',
            'cleaning_buffer_minutes' => '20',
            'status' => 'ACTIVE',
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame(5, $result['data']['cinema_id']);
        $this->assertSame('Hall 4', $result['data']['room_name']);
        $this->assertSame('imax', $result['data']['room_type']);
        $this->assertSame('laser', $result['data']['projection_type']);
        $this->assertSame('dolby_atmos', $result['data']['sound_profile']);
        $this->assertSame(20, $result['data']['cleaning_buffer_minutes']);
        $this->assertSame('active', $result['data']['status']);
    }

    public function testValidateSeatLayoutPayloadRejectsDuplicatesAndSortsRows(): void
    {
        $validator = new CinemaManagementValidator();

        $invalid = $validator->validateSeatLayoutPayload([
            'seats' => [
                ['seat_row' => 'A', 'seat_number' => '1', 'seat_type' => 'normal', 'status' => 'available'],
                ['seat_row' => 'A', 'seat_number' => '1', 'seat_type' => 'vip', 'status' => 'maintenance'],
            ],
        ]);

        $this->assertArrayHasKey('seats.1', $invalid['errors']);

        $sorted = $validator->validateSeatLayoutPayload([
            'seats' => [
                ['seat_row' => 'B', 'seat_number' => '3', 'seat_type' => 'vip', 'status' => 'maintenance'],
                ['seat_row' => 'A', 'seat_number' => '1', 'seat_type' => 'normal', 'status' => 'available'],
            ],
        ]);

        $this->assertSame([], $sorted['errors']);
        $this->assertSame('A', $sorted['data']['seats'][0]['seat_row']);
        $this->assertSame(1, $sorted['data']['seats'][0]['seat_number']);
        $this->assertSame('B', $sorted['data']['seats'][1]['seat_row']);
    }

    public function testNormalizeCinemaFiltersDefaultsToActiveScopeAndMovesArchivedStatusIntoArchiveView(): void
    {
        $validator = new CinemaManagementValidator();

        $defaultFilters = $validator->normalizeCinemaFilters([
            'page' => '0',
            'per_page' => '250',
            'status' => '',
        ]);
        $archivedFilters = $validator->normalizeCinemaFilters([
            'status' => 'archived',
        ]);

        $this->assertSame('active', $defaultFilters['scope']);
        $this->assertNull($defaultFilters['status']);
        $this->assertSame(1, $defaultFilters['page']);
        $this->assertSame(100, $defaultFilters['per_page']);

        $this->assertSame('archived', $archivedFilters['scope']);
        $this->assertSame('archived', $archivedFilters['status']);
    }

    public function testNormalizeRoomFiltersClearsArchivedStatusInActiveScope(): void
    {
        $validator = new CinemaManagementValidator();

        $filters = $validator->normalizeRoomFilters([
            'scope' => 'active',
            'status' => 'archived',
            'room_type' => 'IMAX',
        ]);

        $this->assertSame('active', $filters['scope']);
        $this->assertNull($filters['status']);
        $this->assertSame('imax', $filters['room_type']);
    }
}
