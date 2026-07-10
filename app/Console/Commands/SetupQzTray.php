<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class SetupQzTray extends Command
{
    protected $signature = 'qz:setup
        {--force : Regenera el certificado aunque ya exista}
        {--skip-allow : No registra el certificado en QZ Tray}
        {--skip-env : No guarda QZ_OPTS en variables de usuario}';

    protected $description = 'Genera y registra el certificado local para imprimir con QZ Tray sin avisos de Allow.';

    public function handle(): int
    {
        $certificatePath = config('qz.certificate_path');
        $privateKeyPath = config('qz.private_key_path');

        if (!is_string($certificatePath) || !is_string($privateKeyPath)) {
            $this->error('Las rutas QZ_CERTIFICATE_PATH/QZ_PRIVATE_KEY_PATH no son validas.');
            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($certificatePath));

        if ($this->option('force') || !File::exists($certificatePath) || !File::exists($privateKeyPath)) {
            if (!$this->generateCertificate($certificatePath, $privateKeyPath)) {
                return self::FAILURE;
            }
        } else {
            $this->line('Certificado QZ existente, no se regenera.');
        }

        if (!$this->option('skip-allow')) {
            $this->allowCertificate($certificatePath);
        }

        if (!$this->option('skip-env')) {
            $this->persistQzOptions($certificatePath);
        }

        $this->newLine();
        $this->info('QZ listo para esta computadora.');
        $this->line('Certificado publico: '.$certificatePath);
        $this->line('Llave privada local: '.$privateKeyPath);
        $this->warn('Reinicia QZ Tray y recarga el punto de venta antes de probar.');

        return self::SUCCESS;
    }

    private function generateCertificate(string $certificatePath, string $privateKeyPath): bool
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if (!$privateKey) {
            $this->error('No se pudo crear la llave privada con OpenSSL.');
            return false;
        }

        $subject = [
            'countryName' => 'MX',
            'stateOrProvinceName' => 'CDMX',
            'localityName' => 'Mexico',
            'organizationName' => 'OXI VENTAS',
            'organizationalUnitName' => 'POS',
            'commonName' => 'OXI VENTAS QZ Tray',
        ];

        $csr = openssl_csr_new($subject, $privateKey, ['digest_alg' => 'sha256']);

        if (!$csr) {
            $this->error('No se pudo crear la solicitud del certificado.');
            return false;
        }

        $certificate = openssl_csr_sign($csr, null, $privateKey, 3650, ['digest_alg' => 'sha256']);

        if (!$certificate) {
            $this->error('No se pudo firmar el certificado.');
            return false;
        }

        openssl_x509_export($certificate, $certificateContents);
        openssl_pkey_export($privateKey, $privateKeyContents, config('qz.private_key_passphrase') ?: null);

        File::put($certificatePath, $certificateContents);
        File::put($privateKeyPath, $privateKeyContents);

        $this->info('Certificado QZ generado.');

        return true;
    }

    private function allowCertificate(string $certificatePath): void
    {
        $javaPath = $this->qzJavaPath();
        $jarPath = 'C:\\Program Files\\QZ Tray\\qz-tray.jar';

        if (!File::exists($javaPath) || !File::exists($jarPath)) {
            $this->warn('No encontre QZ Tray en Program Files. Instala QZ Tray y vuelve a correr el comando.');
            return;
        }

        $process = new Process([
            $javaPath,
            '-jar',
            $jarPath,
            '--allow',
            $certificatePath,
        ]);

        $process->setTimeout(30);
        $process->run();

        if ($process->isSuccessful()) {
            $this->info('Certificado registrado en la lista permitida de QZ Tray.');
            return;
        }

        $this->warn('No se pudo registrar automaticamente en QZ Tray.');
        $this->line(trim($process->getErrorOutput() ?: $process->getOutput()));
    }

    private function persistQzOptions(string $certificatePath): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->warn('QZ_OPTS automatico solo esta implementado para Windows.');
            return;
        }

        $option = '-Dauthcert.override='.$certificatePath;
        $encodedOption = base64_encode($option);
        $process = new Process([
            'powershell',
            '-NoProfile',
            '-ExecutionPolicy',
            'Bypass',
            '-Command',
            '$value = [Text.Encoding]::UTF8.GetString([Convert]::FromBase64String("'.$encodedOption.'")); [Environment]::SetEnvironmentVariable("QZ_OPTS", $value, "User")',
        ]);

        $process->setTimeout(15);
        $process->run();

        if ($process->isSuccessful()) {
            $this->info('QZ_OPTS guardado para este usuario de Windows.');
            return;
        }

        $this->warn('No se pudo guardar QZ_OPTS automaticamente.');
        $this->line(trim($process->getErrorOutput() ?: $process->getOutput()));
    }

    private function qzJavaPath(): string
    {
        return 'C:\\Program Files\\QZ Tray\\runtime\\bin\\java.exe';
    }
}
