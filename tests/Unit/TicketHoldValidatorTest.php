<?php

namespace Tests\Unit;

use App\Validators\TicketHoldValidator;
use PHPUnit\Framework\TestCase;

class TicketHoldValidatorTest extends TestCase
{
    public function testValidateCreatePayloadNormalizesSeatIds(): void
    {
        $validator = new TicketHoldValidator();

        $result = $validator->validateCreatePayload([
            'showtime_id' => '22',
            'seat_ids' => ['5', 5, '7'],
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame(22, $result['data']['showtime_id']);
        $this->assertSame([5, 7], $result['data']['seat_ids']);
    }

    public function testValidateCreatePayloadRejectsInvalidSeatPayload(): void
    {
        $validator = new TicketHoldValidator();

        $result = $validator->validateCreatePayload([
            'showtime_id' => '10',
            'seat_ids' => 'A1',
        ]);

        $this->assertSame(['Seat IDs must be a non-empty array of positive integers.'], $result['errors']['seat_ids']);
    }

    public function testValidateCreatePayloadRejectsSeatLimitOverflow(): void
    {
        $validator = new TicketHoldValidator();

        $result = $validator->validateCreatePayload([
            'showtime_id' => 10,
            'seat_ids' => range(1, 11),
        ]);

        $this->assertSame(['A single hold can reserve up to 10 seats.'], $result['errors']['seat_ids']);
    }
}
