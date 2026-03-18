<?php

namespace App\Services\Concerns;

trait FormatsShopOrderData
{
    protected function formatShopOrderSummary(array $header): array
    {
        return [
            'id' => isset($header['id']) ? (int) $header['id'] : 0,
            'order_code' => $header['order_code'] ?? null,
            'user_id' => isset($header['user_id']) && $header['user_id'] !== null ? (int) $header['user_id'] : null,
            'contact_name' => $header['contact_name'] ?? null,
            'contact_email' => $header['contact_email'] ?? null,
            'contact_phone' => $header['contact_phone'] ?? null,
            'fulfillment_method' => $header['fulfillment_method'] ?? null,
            'shipping_address' => [
                'address_text' => $header['shipping_address_text'] ?? null,
                'city' => $header['shipping_city'] ?? null,
                'district' => $header['shipping_district'] ?? null,
            ],
            'item_count' => isset($header['item_count']) ? (int) $header['item_count'] : 0,
            'subtotal_price' => isset($header['subtotal_price']) ? (float) $header['subtotal_price'] : 0.0,
            'discount_amount' => isset($header['discount_amount']) ? (float) $header['discount_amount'] : 0.0,
            'fee_amount' => isset($header['fee_amount']) ? (float) $header['fee_amount'] : 0.0,
            'shipping_amount' => isset($header['shipping_amount']) ? (float) $header['shipping_amount'] : 0.0,
            'total_price' => isset($header['total_price']) ? (float) $header['total_price'] : 0.0,
            'currency' => $header['currency'] ?? 'VND',
            'status' => $header['status'] ?? null,
            'payment_due_at' => $header['payment_due_at'] ?? null,
            'confirmed_at' => $header['confirmed_at'] ?? null,
            'fulfilled_at' => $header['fulfilled_at'] ?? null,
            'cancelled_at' => $header['cancelled_at'] ?? null,
            'order_date' => $header['order_date'] ?? null,
            'updated_at' => $header['updated_at'] ?? null,
            'payment_method' => $header['payment_method'] ?? null,
            'payment_status' => $header['payment_status'] ?? null,
            'transaction_code' => $header['transaction_code'] ?? null,
            'redirect_url' => $header['checkout_url'] ?? null,
        ];
    }

    protected function formatShopOrderDetail(array $header, array $detailRows): array
    {
        $order = $this->formatShopOrderSummary($header);
        $order['items'] = array_map([$this, 'formatShopOrderItemRow'], $detailRows);

        return $order;
    }

    protected function formatShopOrderItemRow(array $row): array
    {
        return [
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'order_id' => isset($row['order_id']) ? (int) $row['order_id'] : 0,
            'product_id' => isset($row['product_id']) && $row['product_id'] !== null ? (int) $row['product_id'] : null,
            'product_slug' => $row['product_slug'] ?? null,
            'product_name' => $row['product_name_snapshot'] ?? null,
            'product_sku' => $row['product_sku_snapshot'] ?? null,
            'quantity' => isset($row['quantity']) ? (int) $row['quantity'] : 0,
            'unit_price' => isset($row['price']) ? (float) $row['price'] : 0.0,
            'discount_amount' => isset($row['discount_amount']) ? (float) $row['discount_amount'] : 0.0,
            'line_total' => isset($row['line_total']) ? (float) $row['line_total'] : 0.0,
            'currency' => $row['currency'] ?? 'VND',
            'primary_image_url' => $this->resolveShopAssetUrl($row['primary_image_url'] ?? null),
            'primary_image_alt' => $row['primary_image_alt'] ?? ($row['product_name_snapshot'] ?? 'Product'),
        ];
    }
}
