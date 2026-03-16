<?php

namespace App\Services\Concerns;

trait FormatsTicketData
{
    protected function formatOrderSummary(array $header, array $detailRows): array
    {
        $first = $detailRows[0] ?? [];
        $seatLabels = array_values(array_unique(array_map([$this, 'seatLabel'], $detailRows)));

        return [
            'id' => (int) ($header['id'] ?? 0),
            'order_code' => $header['order_code'] ?? null,
            'user_id' => isset($header['user_id']) ? (int) $header['user_id'] : null,
            'contact_name' => $header['contact_name'] ?? null,
            'contact_email' => $header['contact_email'] ?? null,
            'contact_phone' => $header['contact_phone'] ?? null,
            'fulfillment_method' => $header['fulfillment_method'] ?? null,
            'seat_count' => (int) ($header['seat_count'] ?? count($seatLabels)),
            'seats' => $seatLabels,
            'subtotal_price' => isset($header['subtotal_price']) ? (float) $header['subtotal_price'] : 0.0,
            'discount_amount' => isset($header['discount_amount']) ? (float) $header['discount_amount'] : 0.0,
            'fee_amount' => isset($header['fee_amount']) ? (float) $header['fee_amount'] : 0.0,
            'total_price' => isset($header['total_price']) ? (float) $header['total_price'] : 0.0,
            'currency' => $header['currency'] ?? 'VND',
            'status' => $header['status'] ?? null,
            'hold_expires_at' => $header['hold_expires_at'] ?? null,
            'paid_at' => $header['paid_at'] ?? null,
            'order_date' => $header['order_date'] ?? null,
            'updated_at' => $header['updated_at'] ?? null,
            'payment_method' => $header['payment_method'] ?? null,
            'payment_status' => $header['payment_status'] ?? null,
            'transaction_code' => $header['transaction_code'] ?? null,
            'movie_id' => isset($first['movie_id']) ? (int) $first['movie_id'] : null,
            'movie_slug' => $first['movie_slug'] ?? null,
            'movie_title' => $first['movie_title'] ?? null,
            'poster_url' => $first['poster_url'] ?? null,
            'cinema_id' => isset($first['cinema_id']) ? (int) $first['cinema_id'] : null,
            'cinema_name' => $first['cinema_name'] ?? null,
            'cinema_city' => $first['cinema_city'] ?? null,
            'room_id' => isset($first['room_id']) ? (int) $first['room_id'] : null,
            'room_name' => $first['room_name'] ?? null,
            'showtime_id' => isset($first['showtime_id']) ? (int) $first['showtime_id'] : null,
            'show_date' => $first['show_date'] ?? null,
            'start_time' => $first['start_time'] ?? null,
            'end_time' => $first['end_time'] ?? null,
            'presentation_type' => $first['presentation_type'] ?? null,
            'language_version' => $first['language_version'] ?? null,
        ];
    }

    protected function formatOrderDetail(array $header, array $detailRows): array
    {
        $order = $this->formatOrderSummary($header, $detailRows);
        $order['tickets'] = array_map([$this, 'formatTicketRow'], $detailRows);

        return $order;
    }

    protected function formatTicketRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'order_id' => (int) ($row['order_id'] ?? 0),
            'showtime_id' => (int) ($row['showtime_id'] ?? 0),
            'seat_id' => (int) ($row['seat_id'] ?? 0),
            'ticket_code' => $row['ticket_code'] ?? null,
            'order_code' => $row['order_code'] ?? null,
            'status' => $row['ticket_status'] ?? $row['status'] ?? null,
            'base_price' => isset($row['base_price']) ? (float) $row['base_price'] : 0.0,
            'surcharge_amount' => isset($row['surcharge_amount']) ? (float) $row['surcharge_amount'] : 0.0,
            'discount_amount' => isset($row['discount_amount']) ? (float) $row['discount_amount'] : 0.0,
            'price' => isset($row['price']) ? (float) $row['price'] : 0.0,
            'qr_payload' => $row['qr_payload'] ?? null,
            'scanned_at' => $row['scanned_at'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'show_date' => $row['show_date'] ?? null,
            'start_time' => $row['start_time'] ?? null,
            'end_time' => $row['end_time'] ?? null,
            'presentation_type' => $row['presentation_type'] ?? null,
            'language_version' => $row['language_version'] ?? null,
            'movie_title' => $row['movie_title'] ?? null,
            'poster_url' => $row['poster_url'] ?? null,
            'cinema_name' => $row['cinema_name'] ?? null,
            'cinema_city' => $row['cinema_city'] ?? null,
            'room_name' => $row['room_name'] ?? null,
            'seat_label' => $this->seatLabel($row),
            'seat_type' => $row['seat_type'] ?? null,
            'contact_name' => $row['contact_name'] ?? null,
            'contact_email' => $row['contact_email'] ?? null,
            'contact_phone' => $row['contact_phone'] ?? null,
            'fulfillment_method' => $row['fulfillment_method'] ?? null,
            'order_status' => $row['order_status'] ?? null,
            'order_date' => $row['order_date'] ?? null,
            'payment_method' => $row['payment_method'] ?? null,
            'payment_status' => $row['payment_status'] ?? null,
            'transaction_code' => $row['transaction_code'] ?? null,
        ];
    }

    protected function formatPaymentSnapshotFromHeader(array $header): array
    {
        return [
            'payment_method' => $header['payment_method'] ?? null,
            'payment_status' => $header['payment_status'] ?? null,
            'transaction_code' => $header['transaction_code'] ?? null,
            'paid_at' => $header['paid_at'] ?? null,
        ];
    }

    protected function formatHoldRow(array $row): array
    {
        $customerRef = trim((string) ($row['user_email'] ?? ''));
        if ($customerRef === '') {
            $customerRef = trim((string) ($row['user_phone'] ?? ''));
        }
        if ($customerRef === '') {
            $customerRef = trim((string) ($row['user_name'] ?? ''));
        }
        if ($customerRef === '') {
            $customerRef = substr((string) ($row['session_token'] ?? ''), 0, 12);
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'showtime_id' => (int) ($row['showtime_id'] ?? 0),
            'seat_id' => (int) ($row['seat_id'] ?? 0),
            'user_id' => isset($row['user_id']) ? (int) $row['user_id'] : null,
            'session_token' => $row['session_token'] ?? null,
            'hold_expires_at' => $row['hold_expires_at'] ?? null,
            'customer_ref' => $customerRef,
            'customer_name' => $row['user_name'] ?? null,
            'movie_title' => $row['movie_title'] ?? null,
            'cinema_name' => $row['cinema_name'] ?? null,
            'room_name' => $row['room_name'] ?? null,
            'show_date' => $row['show_date'] ?? null,
            'start_time' => $row['start_time'] ?? null,
            'seat_label' => $this->seatLabel($row),
        ];
    }

    protected function paginationMeta(array $page): array
    {
        $totalPages = (int) ceil(($page['total'] ?: 0) / max(1, $page['per_page']));

        return [
            'total' => (int) ($page['total'] ?? 0),
            'page' => (int) ($page['page'] ?? 1),
            'per_page' => (int) ($page['per_page'] ?? 20),
            'total_pages' => max(1, $totalPages),
        ];
    }

    protected function seatLabel(array $row): string
    {
        return strtoupper(trim((string) ($row['seat_row'] ?? ''))) . (int) ($row['seat_number'] ?? 0);
    }
}
