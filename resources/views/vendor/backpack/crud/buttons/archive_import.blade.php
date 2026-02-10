@if ($crud->hasAccess('list'))
    <a href="{{ url($crud->route . '/' . $entry->getKey() . '/archive') }}"
       class="btn btn-sm btn-link"
       onclick="event.preventDefault(); if(confirm('Archive this import? Its transactions will be hidden from the active list.')) { document.getElementById('archive-form-{{ $entry->getKey() }}').submit(); }">
        <i class="la la-archive"></i> Archive
    </a>
    <form id="archive-form-{{ $entry->getKey() }}"
          action="{{ url($crud->route . '/' . $entry->getKey() . '/archive') }}"
          method="POST" style="display: none;">
        @csrf
    </form>
@endif
