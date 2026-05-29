@props(['records'])

<div class="pagination-shell">
    <div class="pagination-controls">
        @if ($records['page'] > 1)
            <button class="btn btn-outline-secondary" type="button" wire:click="goToPage(1)">First</button>
            <button class="btn btn-outline-secondary" type="button" wire:click="goToPage({{ $records['page'] - 1 }})">Previous</button>
        @else
            <span class="btn btn-outline-secondary disabled">First</span>
            <span class="btn btn-outline-secondary disabled">Previous</span>
        @endif
    </div>

    <form class="page-jump" wire:submit.prevent="goToPageInput">
        <span>Showing {{ $records['from'] }}-{{ $records['to'] }} of {{ $records['total'] }}</span>
        <label>
            <span>Page</span>
            <input class="form-control form-control-sm" type="number" min="1" max="{{ $records['last_page'] }}" wire:model="pageJump" placeholder="{{ $records['page'] }}" />
        </label>
        <button class="btn btn-outline-secondary btn-sm" type="submit">Go</button>
    </form>

    <div class="pagination-controls">
        @if ($records['page'] < $records['last_page'])
            <button class="btn btn-outline-secondary" type="button" wire:click="goToPage({{ $records['page'] + 1 }})">Next</button>
            <button class="btn btn-outline-secondary" type="button" wire:click="goToPage({{ $records['last_page'] }})">Last</button>
        @else
            <span class="btn btn-outline-secondary disabled">Next</span>
            <span class="btn btn-outline-secondary disabled">Last</span>
        @endif
    </div>
</div>
