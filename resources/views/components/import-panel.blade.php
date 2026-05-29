@props(['readOnly' => false])

@unless ($readOnly)
    <details class="import-panel">
        <summary>Import CSV</summary>
        <div class="import-body">
            <textarea class="form-control" rows="5" wire:model="csvImport" placeholder="name,email&#10;Ada,ada@example.com"></textarea>
            <button class="btn btn-primary btn-sm" type="button" wire:click="importCsv" wire:confirm="Import CSV rows into this table?">Import rows</button>
        </div>
    </details>
@endunless
