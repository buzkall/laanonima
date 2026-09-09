<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        /*
         | All three start empty, and empty is a working state for every one of
         | them: nothing is appended to the prompt and no balance is being
         | watched. A fresh checkout needs nothing filled in before La Cupida
         | works.
         |
         | Where the credit warnings go is deliberately not here: it is
         | `site.admin_email`, out of the environment.
         */
        $this->migrator->add('cupida.extra_instructions');
        $this->migrator->add('cupida.credit_balance');
        $this->migrator->add('cupida.credit_topped_up_at');
    }
};
