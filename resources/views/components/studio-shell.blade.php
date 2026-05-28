@props(['tables' => [], 'activeTable' => null])

<div class="studio-shell">
    <x-sqlite-manager::table-explorer :tables="$tables" :active-table="$activeTable" />
    <section class="workspace">{{ $slot }}</section>
</div>
