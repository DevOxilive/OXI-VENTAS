<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\SystemAuditService;
use Illuminate\Console\Command;

class GrantSuperAdministrator extends Command
{
    protected $signature = 'system:grant-super-administrator
                            {user : ID o correo electrónico del usuario}
                            {--confirm : Confirma el cambio de rol}';

    protected $description = 'Designa el primer Super Administrador de forma explícita y auditable.';

    public function handle(SystemAuditService $audit): int
    {
        if (!$this->option('confirm')) {
            $this->error('Debes incluir la opción --confirm para modificar el rol.');

            return self::FAILURE;
        }

        $identifier = (string) $this->argument('user');
        $user = User::query()
            ->where('id', $identifier)
            ->orWhere('email', $identifier)
            ->first();

        if (!$user) {
            $this->error('No se encontró el usuario indicado.');

            return self::FAILURE;
        }

        $role = Role::query()->where('name', 'Super Administrador')->first();

        if (!$role) {
            $this->error('El rol Super Administrador no existe. Ejecuta las migraciones primero.');

            return self::FAILURE;
        }

        $user->update(['role_id' => $role->id]);
        $audit->record('system-administration', 'grant_super_administrator', 'success', null, [
            'record_type' => User::class,
            'record_id' => $user->id,
            'record_label' => $user->email,
            'observations' => 'Asignación inicial realizada mediante comando de consola.',
        ]);

        $this->info("{$user->email} ahora tiene el rol Super Administrador.");

        return self::SUCCESS;
    }
}
