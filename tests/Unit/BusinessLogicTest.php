<?php

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class BusinessLogicTest extends TestCase
{
    #[Group('CartCalculation')]
    #[Group('Business')]
    public function testCartSubtotalCalculation(): void
    {
        $this->assertSame(150.0, tauroCartItemSubtotal(50, 3));
    }

    #[Group('CartCalculation')]
    #[Group('Business')]
    public function testCartTotalWithMultipleItems(): void
    {
        $items = [
            ['price' => 50, 'quantity' => 2],
            ['price' => 75, 'quantity' => 1],
            ['price' => 25, 'quantity' => 4],
        ];

        $this->assertSame(275.0, tauroCartTotal($items));
    }

    #[Group('ShippingCost')]
    #[Group('CartCalculation')]
    public function testCartTotalWithShippingCost(): void
    {
        $items = [
            ['price' => 100, 'quantity' => 2],
        ];

        $this->assertSame(215.0, tauroCartTotal($items, 15));
    }

    #[Group('PriceFormatting')]
    #[Group('Business')]
    public function testPriceFormattingWithThousandsSeparator(): void
    {
        $this->assertSame('25.000', tauroFormatBusinessPrice(25000));
    }

    #[Group('PriceFormatting')]
    #[Group('Business')]
    public function testPriceFormattingWithDecimals(): void
    {
        $this->assertSame('99.99', tauroFormatBusinessPrice(99.99));
    }

    #[Group('OrderStatus')]
    #[Group('Business')]
    public function testValidOrderStatuses(): void
    {
        $this->assertSame(
            ['pendiente', 'pagado', 'preparando', 'enviado', 'entregado', 'cancelado'],
            estadosPedidoPermitidos()
        );
    }

    #[Group('OrderStatus')]
    #[Group('Business')]
    public function testOrderStateTransition(): void
    {
        $this->assertTrue(puedeTransicionarEstadoPedido('pendiente', 'pagado'));
        $this->assertTrue(puedeTransicionarEstadoPedido('pagado', 'preparando'));
        $this->assertFalse(puedeTransicionarEstadoPedido('entregado', 'pagado'));
    }

    #[Group('TaxCalculation')]
    #[Group('Business')]
    public function testTaxCalculation(): void
    {
        $this->assertSame(19.0, tauroCalculateTax(100));
    }

    #[Group('ShippingDays')]
    #[Group('Business')]
    public function testShippingDaysCalculation(): void
    {
        $this->assertTrue(tauroValidateDeliveryDays(2, 5));
        $this->assertFalse(tauroValidateDeliveryDays(5, 2));
    }

    #[Group('CartValidation')]
    #[Group('Business')]
    public function testCartQuantityValidation(): void
    {
        foreach ([1, 2, 5, 10, 50, 100] as $quantity) {
            $this->assertTrue(tauroValidateCartQuantity($quantity));
        }
    }

    #[Group('CartValidation')]
    #[Group('Business')]
    public function testRejectInvalidQuantity(): void
    {
        foreach ([0, -1, 101, 1000] as $quantity) {
            $this->assertFalse(tauroValidateCartQuantity($quantity));
        }
    }

    #[Group('Discounts')]
    #[Group('Business')]
    public function testSimpleQuantityDiscount(): void
    {
        $this->assertSame(30.0, tauroSimpleQuantityDiscount(300, 3));
        $this->assertSame(0.0, tauroSimpleQuantityDiscount(200, 2));
    }

    #[Group('ProductValidation')]
    #[Group('Business')]
    public function testValidClothingSizes(): void
    {
        foreach (['XS', 'S', 'M', 'L', 'XL', 'XXL'] as $size) {
            $this->assertTrue(tauroValidateClothingSize($size));
        }
    }

    #[Group('ProductValidation')]
    #[Group('Business')]
    public function testInvalidClothingSize(): void
    {
        foreach (['XXXL', 'MEDIANA', '42'] as $size) {
            $this->assertFalse(tauroValidateClothingSize($size));
        }
    }

    #[Group('ProductValidation')]
    #[Group('Business')]
    public function testTallaMustNotExceedMaxLength(): void
    {
        $this->assertFalse(tauroValidateClothingSize('ABCDEFGHIJK'));
    }

    #[Group('Business')]
    public function testOpcionesEstadoPedidoIncludeCurrentStatus(): void
    {
        $this->assertSame(['pagado', 'preparando', 'cancelado'], opcionesEstadoPedido('pagado'));
    }

    #[Group('Business')]
    public function testColumnaFechaEstadoPedidoReturnsExpectedColumn(): void
    {
        $this->assertSame('estado_enviado_at', columnaFechaEstadoPedido('enviado'));
    }

    #[Group('Business')]
    public function testTextoTituloPedidoFormatsNormalizedText(): void
    {
        $this->assertSame('Bogota Norte', textoTituloPedido('bogota-norte'));
    }

    #[Group('Business')]
    public function testTauroChatbotFormatPriceUsesColombianStyle(): void
    {
        $this->assertSame('$25.000', tauroChatbotFormatPrice(25000));
    }

    #[Group('Business')]
    public function testTauroChatbotExtractOrderIdFindsPedidoNumber(): void
    {
        $this->assertSame(1234, tauroChatbotExtractOrderId('Necesito revisar el pedido #1234'));
    }
}
