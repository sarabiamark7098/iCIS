@if ($crud->hasAccess('list'))
    <a href="{{ url($crud->route . '/' . $entry->getKey() . '/unarchive') }}"
       class="btn btn-sm btn-link"
       onclick="event.preventDefault(); if(confirm('Restore this import from archive?')) { document.getElementById('unarchive-form-{{ $entry->getKey() }}').submit(); }">
        <i class="la la-undo"></i> Unarchive
    </a>
    <form id="unarchive-form-{{ $entry->getKey() }}"
          action="{{ url($crud->route . '/' . $entry->getKey() . '/unarchive') }}"
          method="POST" style="display: none;">
        @csrf
    </form>
@endif
