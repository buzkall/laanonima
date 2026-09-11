{{--
    Sits in the topbar's right-hand cluster, immediately before the user menu.
    Rendered through the USER_MENU_BEFORE hook in both panel providers.
--}}
<x-filament::icon-button
    tag="a"
    :href="route('home')"
    icon="heroicon-o-globe-alt"
    color="gray"
    icon-size="lg"
    :label="__('panel.topbar.shop')"
    :tooltip="__('panel.topbar.shop')"
/>
