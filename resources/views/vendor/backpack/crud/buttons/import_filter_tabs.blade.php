@php
    $currentFilter = request()->get('show', 'active');
    $baseUrl = url($crud->route);
@endphp

<div class="mb-2">
    <div class="btn-group" role="group">
        <a href="{{ $baseUrl }}?show=active"
           class="btn btn-sm {{ $currentFilter === 'active' ? 'btn-primary' : 'btn-outline-primary' }}">
            <i class="la la-list"></i> Active
        </a>
        <a href="{{ $baseUrl }}?show=archived"
           class="btn btn-sm {{ $currentFilter === 'archived' ? 'btn-warning' : 'btn-outline-warning' }}">
            <i class="la la-archive"></i> Archived
        </a>
        <a href="{{ $baseUrl }}?show=trashed"
           class="btn btn-sm {{ $currentFilter === 'trashed' ? 'btn-danger' : 'btn-outline-danger' }}">
            <i class="la la-trash"></i> Deleted
        </a>
    </div>
</div>
