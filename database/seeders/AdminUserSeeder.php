<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Crée le compte administrateur unique de l'application.
     *
     * Identifiants par défaut (à changer immédiatement après installation) :
     *   Email    : admin@boutique.local
     *   Password : password123
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@boutique.local'],
            [
                'name'     => 'Administrateur',
                'email'    => 'admin@boutique.local',
                'password' => Hash::make('password123'),
            ]
        );

        $this->command->info('✅ Compte administrateur créé :');
        $this->command->line('   Email    : admin@boutique.local');
        $this->command->line('   Password : password123');
        $this->command->warn('   ⚠️  Changez le mot de passe après la première connexion !');
    }
}
