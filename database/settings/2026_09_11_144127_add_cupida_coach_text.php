<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        /*
         | Unlike the three before it, this one starts filled in. Empty is a
         | working state -- the deck shows no strip and the card still moves --
         | but it is the bookseller's choice to make, not the default a fresh
         | checkout ships with. From here on the text is theirs, edited from
         | the "Prompt IA" action, which is why it is a literal and not a
         | lang key.
         */
        $this->migrator->add(
            'cupida.coach_text',
            "A la derecha, «me gusta»:\nme lo quedo.\nA la izquierda, «paso»:\nesto no lo quiero.\nDescartar también es elegir.",
        );
    }
};
