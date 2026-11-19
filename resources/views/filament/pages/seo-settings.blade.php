<x-filament-panels::page>
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}

        <div class="mt-4 flex items-center gap-3">
            <x-filament::button type="submit" icon="heroicon-m-check">
                Save SEO Settings
            </x-filament::button>
        </div>
    </x-filament-panels::form>
</x-filament-panels::page>
