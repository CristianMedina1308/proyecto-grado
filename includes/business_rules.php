<?php

function tauroCartItemSubtotal(float $price, int $quantity): float
{
    return round($price * $quantity, 2);
}

function tauroCartTotal(array $items, float $shippingCost = 0.0): float
{
    $total = 0.0;

    foreach ($items as $item) {
        $price = (float) ($item['price'] ?? 0);
        $quantity = (int) ($item['quantity'] ?? 0);
        $total += tauroCartItemSubtotal($price, $quantity);
    }

    return round($total + $shippingCost, 2);
}

function tauroFormatBusinessPrice(float $price): string
{
    if (fmod($price, 1.0) === 0.0) {
        return number_format($price, 0, ',', '.');
    }

    return number_format($price, 2, '.', '');
}

function tauroCalculateTax(float $amount, float $rate = 0.19): float
{
    return round($amount * $rate, 2);
}

function tauroValidateDeliveryDays(int $minDays, int $maxDays): bool
{
    return $minDays > 0 && $maxDays >= $minDays;
}

function tauroValidateCartQuantity(int $quantity): bool
{
    return $quantity >= 1 && $quantity <= 100;
}

function tauroSimpleQuantityDiscount(float $subtotal, int $quantity): float
{
    if ($quantity >= 3) {
        return round($subtotal * 0.10, 2);
    }

    return 0.0;
}

function tauroValidateClothingSize(?string $size): bool
{
    $size = strtoupper(trim((string) $size));

    if ($size === '' || strlen($size) > 10) {
        return false;
    }

    return in_array($size, ['XS', 'S', 'M', 'L', 'XL', 'XXL'], true);
}
