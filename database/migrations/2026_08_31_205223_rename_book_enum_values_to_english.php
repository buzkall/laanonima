<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /* BookAvailability, BookBinding and ContributorRole were backed by Spanish
       values; they are English now, so rows written before the rename follow.
       The ONIX mapping the enums carry is untouched — it never lived here. */
    private const array AVAILABILITY = [
        'disponible'    => 'available',
        'bajo_pedido'   => 'to_order',
        'agotado'       => 'out_of_stock',
        'descatalogado' => 'out_of_print',
        'no_publicado'  => 'not_yet_published',
    ];
    private const array BINDING = [
        'rustica'    => 'paperback',
        'tapa_dura'  => 'hardback',
        'bolsillo'   => 'pocket',
        'carton'     => 'board_book',
        'espiral'    => 'spiral',
        'audiolibro' => 'audiobook',
    ];

    /* Roles are not a column: they sit under `role` inside each object of the
       `contributors` jsonb array, so they are rewritten element by element. */
    private const array ROLE = [
        'autor'            => 'author',
        'traductor'        => 'translator',
        'ilustrador'       => 'illustrator',
        'editor_literario' => 'editor',
        'prologuista'      => 'foreword',
        'fotografo'        => 'photographer',
    ];

    public function up(): void
    {
        Schema::table('books', function(Blueprint $table): void {
            $table->string('availability')->default('available')->change();
        });

        $this->rename(self::AVAILABILITY, self::BINDING, self::ROLE);
    }

    public function down(): void
    {
        $this->rename(
            array_flip(self::AVAILABILITY),
            array_flip(self::BINDING),
            array_flip(self::ROLE),
        );

        Schema::table('books', function(Blueprint $table): void {
            $table->string('availability')->default('disponible')->change();
        });
    }

    /**
     * @param  array<string, string>  $availability
     * @param  array<string, string>  $binding
     * @param  array<string, string>  $roles
     */
    private function rename(array $availability, array $binding, array $roles): void
    {
        foreach ($availability as $from => $to) {
            DB::table('books')->where('availability', $from)->update(['availability' => $to]);
        }

        foreach ($binding as $from => $to) {
            DB::table('books')->where('binding', $from)->update(['binding' => $to]);
        }

        DB::table('books')
            ->whereNotNull('contributors')
            ->orderBy('id')
            ->each(function(object $book) use ($roles): void {
                $contributors = json_decode($book->contributors ?? '[]', true);

                if (! is_array($contributors)) {
                    return;
                }

                $renamed = array_map(
                    fn(array $contributor): array => [
                        ...$contributor,
                        'role' => $roles[$contributor['role'] ?? ''] ?? ($contributor['role'] ?? null),
                    ],
                    $contributors,
                );

                if ($renamed !== $contributors) {
                    DB::table('books')->where('id', $book->id)->update(['contributors' => json_encode($renamed)]);
                }
            });
    }
};
