@if ($crud->hasAccess('list'))
    <a href="{{ url($crud->route . '/' . $entry->getKey() . '/restore') }}"
       class="btn btn-sm btn-link text-success"
       onclick="event.preventDefault(); if(confirm('Restore this deleted import?')) { document.getElementById('restore-form-{{ $entry->getKey() }}').submit(); }">
        <i class="la la-undo"></i> Restore
    </a>
    <form id="restore-form-{{ $entry->getKey() }}"
          action="{{ url($crud->route . '/' . $entry->getKey() . '/restore') }}"
          method="POST" style="display: none;">
        @csrf
    </form>
@endif
