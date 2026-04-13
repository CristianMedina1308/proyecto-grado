<?php

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class AppFunctionsTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    #[Group('AppSession')]
    #[Group('Authentication')]
    public function testAppSesionUsuarioCreatesValidSessionArray(): void
    {
        $session = appSesionUsuario([
            'id' => '12',
            'nombre' => 'Cristian',
            'email' => 'cristian@example.com',
            'rol' => 'admin',
        ]);

        $this->assertSame([
            'id' => 12,
            'nombre' => 'Cristian',
            'email' => 'cristian@example.com',
            'rol' => 'admin',
        ], $session);
    }

    #[Group('AppSession')]
    public function testAppSesionUsuarioDefaultsClienteRole(): void
    {
        $session = appSesionUsuario([
            'id' => 1,
            'nombre' => 'Cliente',
            'email' => 'cliente@example.com',
        ]);

        $this->assertSame('cliente', $session['rol']);
    }

    #[Group('AppSession')]
    public function testAppSesionUsuarioTrimsWhitespace(): void
    {
        $session = appSesionUsuario([
            'id' => 7,
            'nombre' => '  Juan Perez  ',
            'email' => '  juan@example.com  ',
            'rol' => '  vendedor  ',
        ]);

        $this->assertSame('Juan Perez', $session['nombre']);
        $this->assertSame('juan@example.com', $session['email']);
        $this->assertSame('vendedor', $session['rol']);
    }

    #[Group('AppSession')]
    #[Group('Authentication')]
    public function testAppLoginUsuarioCreatesSession(): void
    {
        appLoginUsuario([
            'id' => 5,
            'nombre' => 'Laura',
            'email' => 'laura@example.com',
            'rol' => 'cliente',
        ]);

        $this->assertArrayHasKey('usuario', $_SESSION);
        $this->assertSame(5, $_SESSION['usuario']['id']);
        $this->assertSame('Laura', $_SESSION['usuario']['nombre']);
    }

    #[Group('CsrfProtection')]
    #[Group('Security')]
    public function testAppCsrfTokenGeneratesValidToken(): void
    {
        $token = appCsrfToken('checkout');

        $this->assertSame(64, strlen($token));
        $this->assertSame(1, preg_match('/^[a-f0-9]{64}$/', $token));
    }

    #[Group('CsrfProtection')]
    public function testAppCsrfTokenReturnsSameTokenForSameKey(): void
    {
        $first = appCsrfToken('checkout');
        $second = appCsrfToken('checkout');

        $this->assertSame($first, $second);
    }

    #[Group('CsrfProtection')]
    #[Group('Security')]
    public function testAppValidarCsrfValidatesCorrectToken(): void
    {
        $token = appCsrfToken('checkout');

        $this->assertTrue(appValidarCsrf('checkout', $token));
    }

    #[Group('CsrfProtection')]
    #[Group('Security')]
    public function testAppValidarCsrfRegeneratesTokenAfterSuccess(): void
    {
        $token = appCsrfToken('checkout');

        appValidarCsrf('checkout', $token);

        $this->assertNotSame($token, $_SESSION['csrf_tokens']['checkout']);
    }

    #[Group('CsrfProtection')]
    #[Group('Security')]
    public function testAppValidarCsrfRejectsInvalidToken(): void
    {
        appCsrfToken('checkout');

        $this->assertFalse(appValidarCsrf('checkout', 'invalid-token'));
    }

    #[Group('CsrfProtection')]
    #[Group('Security')]
    public function testAppValidarCsrfRejectsNullToken(): void
    {
        appCsrfToken('checkout');

        $this->assertFalse(appValidarCsrf('checkout', null));
    }

    #[Group('FlashMessages')]
    public function testAppFlashAddsMessageToSession(): void
    {
        appFlash('success', 'Pedido creado', 'OK');

        $this->assertCount(1, $_SESSION['app_flashes']);
        $this->assertSame('success', $_SESSION['app_flashes'][0]['type']);
    }

    #[Group('FlashMessages')]
    public function testAppPullFlashesClearsMessages(): void
    {
        appFlash('info', 'Mensaje');

        $flashes = appPullFlashes();

        $this->assertCount(1, $flashes);
        $this->assertArrayNotHasKey('app_flashes', $_SESSION);
    }

    #[Group('FlashMessages')]
    public function testAppPullFlashesReturnsEmptyArrayWhenNoFlashes(): void
    {
        $this->assertSame([], appPullFlashes());
    }

    #[Group('FileHandling')]
    public function testAppDeleteProductImageFileIgnoresDefaultImage(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tauro-tests-default';
        if (!is_dir($dir)) {
            mkdir($dir);
        }

        $file = $dir . DIRECTORY_SEPARATOR . 'look-default.svg';
        file_put_contents($file, 'default');

        appDeleteProductImageFile($dir, 'look-default.svg');

        $this->assertFileExists($file);
        @unlink($file);
        @rmdir($dir);
    }

    #[Group('FileHandling')]
    public function testAppDeleteProductImageFileIgnoresEmptyName(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tauro-tests-empty';
        if (!is_dir($dir)) {
            mkdir($dir);
        }

        appDeleteProductImageFile($dir, '   ');

        $this->assertTrue(true);
        @rmdir($dir);
    }

    #[Group('Navigation')]
    public function testAppRedirectFunctionExists(): void
    {
        $this->assertTrue(function_exists('appRedirect'));
    }
}
