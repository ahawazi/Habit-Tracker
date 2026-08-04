@props([
    'labels' => true,
])

{{--
    Single, reusable appearance control. Wraps Flux's own $flux.appearance
    state (localStorage-backed, system-preference aware) so every place this
    is used — settings page, user menu — stays in sync without any
    component-specific script.
--}}
<flux:radio.group
    x-data
    x-model="$flux.appearance"
    variant="segmented"
    :aria-label="__('Appearance')"
    data-test="appearance-switcher"
    {{ $attributes }}
>
    <flux:radio value="light" icon="sun" :aria-label="__('Light')" data-test="appearance-light">
        @if ($labels)
            {{ __('Light') }}
        @endif
    </flux:radio>

    <flux:radio value="dark" icon="moon" :aria-label="__('Dark')" data-test="appearance-dark">
        @if ($labels)
            {{ __('Dark') }}
        @endif
    </flux:radio>

    <flux:radio value="system" icon="computer-desktop" :aria-label="__('System')" data-test="appearance-system">
        @if ($labels)
            {{ __('System') }}
        @endif
    </flux:radio>
</flux:radio.group>
