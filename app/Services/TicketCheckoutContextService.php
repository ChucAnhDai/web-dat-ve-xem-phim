<?php

namespace App\Services;

use App\Core\Database;
use App\Repositories\SeatRepository;
use App\Repositories\ShowtimeRepository;
use App\Repositories\TicketSeatHoldRepository;
use App\Services\Concerns\FormatsTicketData;
use PDO;

class TicketCheckoutContextService
{
    use FormatsTicketData;

    private PDO $db;
    private ShowtimeRepository $showtimes;
    private SeatRepository $seats;
    private TicketSeatHoldRepository $holds;

    public function __construct(
        ?PDO $db = null,
        ?ShowtimeRepository $showtimes = null,
        ?SeatRepository $seats = null,
        ?TicketSeatHoldRepository $holds = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->showtimes = $showtimes ?? new ShowtimeRepository($this->db);
        $this->seats = $seats ?? new SeatRepository($this->db);
        $this->holds = $holds ?? new TicketSeatHoldRepository($this->db);
    }

    public function buildContext(int $showtimeId, array $requestedSeatIds, string $sessionToken): array
    {
        $showtime = $this->showtimes->findPublicDetail($showtimeId);
        if ($showtime === null) {
            throw new TicketOrderDomainException(['showtime' => ['Showtime not found.']], 404);
        }

        $holdRows = $this->holds->listActiveRowsForSessionAndShowtime($showtimeId, $sessionToken);
        if ($holdRows === []) {
            throw new TicketOrderDomainException(['hold' => ['Seat hold is missing or expired.']], 409);
        }

        $heldSeatIds = array_map(static function (array $row): int {
            return (int) ($row['seat_id'] ?? 0);
        }, $holdRows);
        sort($heldSeatIds);

        $requested = array_values(array_filter(array_map('intval', $requestedSeatIds), static function (int $seatId): bool {
            return $seatId > 0;
        }));
        if ($requested === []) {
            $requested = $heldSeatIds;
        }
        sort($requested);

        if ($requested !== $heldSeatIds) {
            throw new TicketOrderDomainException(['seat_ids' => ['Held seats no longer match the checkout selection.']], 409);
        }

        $seatRows = $this->seats->findRoomSeatsByIds((int) ($showtime['room_id'] ?? 0), $requested);
        if (count($seatRows) !== count($requested)) {
            throw new TicketOrderDomainException(['seat_ids' => ['One or more seats are invalid for this showtime.']], 404);
        }

        $seatLabels = [];
        foreach ($seatRows as $seatRow) {
            $seatLabels[(int) ($seatRow['id'] ?? 0)] = $this->seatLabel($seatRow);
            if (($seatRow['status'] ?? 'available') !== 'available') {
                throw new TicketOrderDomainException([
                    'seat_ids' => ['One or more seats are no longer available.'],
                ], 409);
            }
        }

        $bookedSeatIds = $this->seats->findBookedSeatIdsForShowtime($showtimeId, $requested);
        if ($bookedSeatIds !== []) {
            $messages = array_map(function (int $seatId) use ($seatLabels): string {
                $label = $seatLabels[$seatId] ?? ('Seat #' . $seatId);

                return "Seat {$label} has already been booked.";
            }, $bookedSeatIds);

            throw new TicketOrderDomainException(['seat_ids' => $messages], 409);
        }

        $holdLookup = [];
        foreach ($holdRows as $holdRow) {
            $holdLookup[(int) ($holdRow['seat_id'] ?? 0)] = $holdRow['hold_expires_at'] ?? null;
        }

        $basePrice = isset($showtime['price']) ? (float) $showtime['price'] : 0.0;
        $subtotalPrice = round($basePrice * count($seatRows), 2);
        $surchargeTotal = 0.0;
        $mappedSeats = [];

        foreach ($seatRows as $seatRow) {
            $surchargeAmount = $this->seatSurcharge((string) ($seatRow['seat_type'] ?? 'normal'));
            $price = round($basePrice + $surchargeAmount, 2);
            $surchargeTotal += $surchargeAmount;
            $mappedSeats[] = [
                'id' => (int) ($seatRow['id'] ?? 0),
                'label' => $this->seatLabel($seatRow),
                'type' => $seatRow['seat_type'] ?? 'normal',
                'status' => $seatRow['status'] ?? 'available',
                'base_price' => $basePrice,
                'surcharge_amount' => $surchargeAmount,
                'price' => $price,
                'hold_expires_at' => $holdLookup[(int) ($seatRow['id'] ?? 0)] ?? null,
            ];
        }

        $holdExpiresAt = null;
        foreach ($mappedSeats as $seat) {
            $candidate = trim((string) ($seat['hold_expires_at'] ?? ''));
            if ($candidate === '') {
                continue;
            }
            if ($holdExpiresAt === null || strcmp($candidate, $holdExpiresAt) < 0) {
                $holdExpiresAt = $candidate;
            }
        }

        return [
            'showtime' => $showtime,
            'showtime_summary' => $this->mapShowtime($showtime),
            'seats' => $mappedSeats,
            'base_price' => $basePrice,
            'subtotal_price' => $subtotalPrice,
            'surcharge_total' => round($surchargeTotal, 2),
            'total_price' => round($subtotalPrice + $surchargeTotal, 2),
            'hold_expires_at' => $holdExpiresAt,
        ];
    }

    public function mapShowtime(array $row): array
    {
        $totalSeats = isset($row['total_seats']) ? (int) $row['total_seats'] : 0;
        $bookedSeats = isset($row['booked_seats']) ? (int) $row['booked_seats'] : 0;
        $heldSeats = isset($row['held_seats']) ? (int) $row['held_seats'] : 0;
        $availableSeats = max(0, $totalSeats - $bookedSeats - $heldSeats);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'movie_id' => (int) ($row['movie_id'] ?? 0),
            'movie_slug' => $row['movie_slug'] ?? null,
            'movie_title' => $row['movie_title'] ?? null,
            'poster_url' => $row['poster_url'] ?? null,
            'show_date' => $row['show_date'] ?? null,
            'start_time' => $row['start_time'] ?? null,
            'end_time' => $row['end_time'] ?? null,
            'price' => isset($row['price']) ? (float) $row['price'] : 0.0,
            'status' => $row['status'] ?? null,
            'presentation_type' => $row['presentation_type'] ?? null,
            'language_version' => $row['language_version'] ?? null,
            'cinema_name' => $row['cinema_name'] ?? null,
            'cinema_city' => $row['cinema_city'] ?? null,
            'room_name' => $row['room_name'] ?? null,
            'total_seats' => $totalSeats,
            'booked_seats' => $bookedSeats,
            'held_seats' => $heldSeats,
            'available_seats' => $availableSeats,
            'is_sold_out' => $totalSeats > 0 && $availableSeats <= 0,
        ];
    }

    public function seatSurcharge(string $seatType): float
    {
        $normalized = strtolower(trim($seatType));
        if ($normalized === 'vip') {
            return 15000.0;
        }
        if ($normalized === 'couple') {
            return 30000.0;
        }

        return 0.0;
    }
}
