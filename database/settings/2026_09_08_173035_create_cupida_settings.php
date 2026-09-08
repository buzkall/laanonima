<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        /*
         | All four start empty, and empty is a working state for every one of
         | them: nothing is appended to the prompt, no balance is being watched,
         | and the warnings fall back to the shop's own address. A fresh checkout
         | needs nothing filled in before La Cupida works.
         */
        $this->migrator->add('cupida.extra_instructions');
        $this->migrator->add('cupida.credit_balance');
        $this->migrator->add('cupida.credit_topped_up_at');
        $this->migrator->add('cupida.admin_email');
    }
};
