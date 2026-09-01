<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    /**
     * Readers first, then whoever runs the shop.
     *
     * The order is not the one the enum declares: clients are the list anybody
     * opens this page to look at, and administrators are the handful of
     * accounts that keep the shop running. Filament opens the first tab, so
     * this is also what the page shows before anyone clicks anything.
     *
     * @return array<int, UserRole>
     */
    private const array TAB_ORDER = [UserRole::Client, UserRole::Admin];

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * One tab per role, so every account is under exactly one of them and none
     * is out of reach. The counts are what the shop actually wants at a glance:
     * how many readers there are.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return collect(self::TAB_ORDER)
            ->mapWithKeys(fn(UserRole $role): array => [
                $role->value => Tab::make(__("user.tabs.{$role->value}"))
                    ->icon($role->getIcon())
                    ->badge(User::query()->hasRole($role)->count())
                    ->badgeColor($role->getColor())
                    ->modifyQueryUsing(fn(Builder $query): Builder => $this->onlyRole($query, $role)),
            ])
            ->all();
    }

    /**
     * The page's own query narrowed to one role.
     *
     * The tab starts from the query Filament hands over rather than a fresh
     * one, so whatever the resource already applied stays applied. It goes
     * through here because the closure Filament calls cannot carry the `User`
     * generic that the `hasRole` scope needs to resolve.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private function onlyRole(Builder $query, UserRole $role): Builder
    {
        return $query->hasRole($role);
    }
}
