<div>
    @foreach ($dimensions as $dimension => $tags)
        <div class="mb-4">
            <flux:label class="mb-2 block capitalize">{{ $dimension }}</flux:label>
            <div class="flex flex-wrap gap-2">
                @foreach ($tags as $tag)
                    <flux:button
                        type="button"
                        size="sm"
                        variant="{{ in_array($tag, $selected) ? 'primary' : 'ghost' }}"
                        wire:click="toggle('{{ $tag }}')"
                    >
                        {{ str_replace('_', ' ', $tag) }}
                    </flux:button>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
