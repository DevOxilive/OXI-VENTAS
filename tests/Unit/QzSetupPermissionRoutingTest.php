<?php

namespace Tests\Unit;

use App\Http\Controllers\QzTrayInstallerController;
use Illuminate\Routing\Route;
use Tests\TestCase;
use ZipArchive;

class QzSetupPermissionRoutingTest extends TestCase
{
    public function test_qz_installer_routes_require_the_setup_permission(): void
    {
        foreach (['printers.qz-setup.index', 'printers.qz-setup.download'] as $routeName) {
            $route = app('router')->getRoutes()->getByName($routeName);

            $this->assertInstanceOf(Route::class, $route);
            $this->assertContains('permission:systems.qz.setup', $route->gatherMiddleware());
        }
    }

    public function test_installer_package_code_never_reads_the_private_key(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/QzTrayInstallerController.php'));

        $this->assertStringContainsString("config('qz.certificate_path')", $controller);
        $this->assertStringNotContainsString("config('qz.private_key_path')", $controller);
    }

    public function test_installer_package_contains_only_the_public_qz_material(): void
    {
        config()->set('qz.certificate_path', storage_path('app/qz/qz-public.pem'));

        $response = app(QzTrayInstallerController::class)->download();
        $zipPath = $response->getFile()->getPathname();
        $zip = new ZipArchive();

        try {
            $this->assertTrue($zip->open($zipPath) === true);
            $this->assertNotFalse($zip->locateName('qz-public.pem'));
            $this->assertNotFalse($zip->locateName('install-qz-tray-trust.ps1'));
            $this->assertNotFalse($zip->locateName('INSTALAR-QZ.bat'));
            $this->assertFalse($zip->locateName('qz-private.pem'));
            $this->assertStringContainsString(
                '%SystemRoot%\\System32\\WindowsPowerShell\\v1.0\\powershell.exe',
                (string) $zip->getFromName('INSTALAR-QZ.bat')
            );
        } finally {
            $zip->close();
            @unlink($zipPath);
        }
    }
}
