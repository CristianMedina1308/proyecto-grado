<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class ValidationTest extends TestCase
{
    #[DataProvider('validEmailsProvider')]
    #[Group('EmailValidation')]
    #[Group('Validation')]
    public function testDataProviderValidEmails(string $email): void
    {
        $this->assertNotFalse(filter_var($email, FILTER_VALIDATE_EMAIL));
    }

    #[DataProvider('invalidEmailsProvider')]
    #[Group('EmailValidation')]
    #[Group('Validation')]
    public function testDataProviderInvalidEmails(string $email): void
    {
        $this->assertFalse(filter_var($email, FILTER_VALIDATE_EMAIL));
    }

    #[Group('EmailValidation')]
    #[Group('Validation')]
    public function testValidEmailAddresses(): void
    {
        $emails = [
            'usuario@example.com',
            'juan.perez@empresa.co',
            'soporte+tag@dominio.org',
            'ventas@tauro.store',
        ];

        foreach ($emails as $email) {
            $this->assertNotFalse(filter_var($email, FILTER_VALIDATE_EMAIL));
        }
    }

    #[Group('EmailValidation')]
    #[Group('Validation')]
    public function testInvalidEmailAddresses(): void
    {
        $emails = [
            'usuario@',
            '@example.com',
            'usuario sin arroba',
            'usuario@@example.com',
            'usuario@.com',
            'usuario@example',
            ' usuario@example.com ',
        ];

        foreach ($emails as $email) {
            $this->assertFalse(filter_var($email, FILTER_VALIDATE_EMAIL));
        }
    }

    #[Group('XSSProtection')]
    #[Group('Security')]
    public function testHtmlspecialcharsEscapesXSS(): void
    {
        $input = '<script>alert("xss")</script>';
        $escaped = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

        $this->assertStringContainsString('&lt;script&gt;', $escaped);
        $this->assertStringNotContainsString('<script>', $escaped);
    }

    #[Group('XSSProtection')]
    #[Group('Security')]
    public function testHtmlspecialcharsPreservesText(): void
    {
        $input = 'Texto seguro';

        $this->assertSame('Texto seguro', htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
    }

    #[Group('NumericValidation')]
    #[Group('Validation')]
    public function testValidatePositiveInteger(): void
    {
        $this->assertSame(1, filter_var('1', FILTER_VALIDATE_INT));
        $this->assertSame(123, filter_var('123', FILTER_VALIDATE_INT));
        $this->assertSame(999, filter_var('999', FILTER_VALIDATE_INT));
    }

    #[Group('NumericValidation')]
    #[Group('Validation')]
    public function testRejectNegativeInteger(): void
    {
        $result = filter_var('-5', FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        $this->assertFalse($result);
    }

    #[Group('NumericValidation')]
    #[Group('Validation')]
    public function testValidateFloat(): void
    {
        $this->assertSame(99.99, filter_var('99.99', FILTER_VALIDATE_FLOAT));
    }

    #[Group('StringValidation')]
    #[Group('Validation')]
    public function testTrimRemovesWhitespace(): void
    {
        $this->assertSame('Tauro Store', trim('  Tauro Store  '));
    }

    #[Group('URLValidation')]
    #[Group('Validation')]
    public function testValidateURLFormat(): void
    {
        $this->assertNotFalse(filter_var('https://taurostore.com/producto.php?id=1', FILTER_VALIDATE_URL));
    }

    #[Group('RangeValidation')]
    #[Group('Validation')]
    public function testValidateIntegerRange(): void
    {
        $valid = filter_var('50', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        $invalid = filter_var('101', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);

        $this->assertSame(50, $valid);
        $this->assertFalse($invalid);
    }

    #[Group('CartValidation')]
    #[Group('Validation')]
    public function testValidateCartQuantity(): void
    {
        $this->assertTrue(tauroValidateCartQuantity(1));
        $this->assertTrue(tauroValidateCartQuantity(50));
        $this->assertTrue(tauroValidateCartQuantity(100));
    }

    #[Group('CartValidation')]
    #[Group('Validation')]
    public function testRejectZeroQuantity(): void
    {
        $this->assertFalse(tauroValidateCartQuantity(0));
        $this->assertFalse(tauroValidateCartQuantity(-1));
    }

    #[Group('PathTraversal')]
    #[Group('Security')]
    public function testBasenamePreventsPathTraversal(): void
    {
        $this->assertSame('passwd', basename('../../../etc/passwd'));
    }

    #[Group('PathTraversal')]
    public function testBasenameWithNormalFilenames(): void
    {
        $filename = 'prod-20260413-abc123.jpg';

        $this->assertSame($filename, basename($filename));
    }

    public static function validEmailsProvider(): array
    {
        return [
            ['usuario@example.com'],
            ['juan.perez@empresa.co'],
            ['soporte+tag@dominio.org'],
            ['ventas@tauro.store'],
        ];
    }

    public static function invalidEmailsProvider(): array
    {
        return [
            ['usuario@'],
            ['@example.com'],
            ['usuario sin arroba'],
            ['usuario@@example.com'],
            ['usuario@.com'],
            ['usuario@example'],
            [' usuario@example.com '],
        ];
    }
}
