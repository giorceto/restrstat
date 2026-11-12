<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class UserCreateCommand extends Command
{
    protected $signature = 'user:create {email} {name?}';

    protected $description = 'Създава потребител или сменя паролата му (паролата се пита, не се пише в командата)';

    public function handle(): int
    {
        $email = mb_strtolower($this->argument('email'));
        $password = $this->secret('Парола (поне 10 знака)');
        if (mb_strlen((string) $password) < 10) {
            $this->error('Паролата е твърде кратка.');

            return self::FAILURE;
        }
        if ($password !== $this->secret('Още веднъж')) {
            $this->error('Паролите не съвпадат.');

            return self::FAILURE;
        }
        $user = User::firstOrNew(['email' => $email]);
        $user->name = $this->argument('name') ?? $user->name ?? $email;
        $user->password = $password;
        $user->save();
        $this->info(($user->wasRecentlyCreated ? 'Създаден: ' : 'Сменена парола: ').$email);

        return self::SUCCESS;
    }
}
