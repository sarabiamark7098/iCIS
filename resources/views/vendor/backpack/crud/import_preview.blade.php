@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
        <h1 class="text-capitalize mb-0" bp-section="page-heading">Map Columns</h1>
        <p class="ms-2 ml-2 mb-0" bp-section="page-subheading">Assign each file header to a database table &amp; column.</p>
    </section>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-12">
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <form action="{{ route('import.excel-process') }}" method="POST" id="mappingForm">
                @csrf
                <input type="hidden" name="sheet_index" value="{{ $sheetIndex }}">

                {{-- Mapping Table --}}
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0">
                            Map Headers to Database Columns
                        </h3>
                        <span class="badge bg-info">{{ $totalRows }} rows &bull; {{ count($headers) }} headers</span>
                    </div>
                    <div class="card-body">
                        @if($totalRows > $queueThreshold)
                            <div class="alert alert-warning">
                                <i class="la la-clock"></i>
                                <strong>Large file detected</strong> ({{ number_format($totalRows) }} rows).
                                This import will be processed in the background via the queue.
                            </div>
                        @endif

                        <table class="table table-bordered table-striped" id="mappingTable">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width: 4%">#</th>
                                    <th style="width: 18%">File Header</th>
                                    <th style="width: 18%">Sample Data</th>
                                    <th style="width: 20%">Target Table</th>
                                    <th style="width: 20%">Target Column</th>
                                    <th style="width: 6%">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($headers as $index => $header)
                                    <tr>
                                        <td class="text-center text-muted">{{ $index + 1 }}</td>
                                        <td>
                                            <strong>{{ $header }}</strong>
                                            <input type="hidden" name="headers[{{ $index }}]" value="{{ $header }}">
                                        </td>
                                        <td class="text-muted small">
                                            @php
                                                $samples = [];
                                                foreach (array_slice($sampleRows, 0, 3) as $row) {
                                                    $val = $row[$index] ?? '';
                                                    if ($val !== '' && $val !== null) {
                                                        $samples[] = \Illuminate\Support\Str::limit((string) $val, 25);
                                                    }
                                                }
                                            @endphp
                                            @if(!empty($samples))
                                                {{ implode(', ', $samples) }}
                                            @else
                                                <em>empty</em>
                                            @endif
                                        </td>
                                        <td>
                                            <select name="mapping_table[{{ $index }}]"
                                                    class="form-control form-control-sm table-select"
                                                    data-index="{{ $index }}">
                                                <option value="">-- Skip --</option>
                                                @foreach(array_keys($tableColumns) as $table)
                                                    <option value="{{ $table }}">{{ ucfirst(str_replace('_', ' ', $table)) }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <select name="mapping_column[{{ $index }}]"
                                                    class="form-control form-control-sm column-select"
                                                    data-index="{{ $index }}" disabled>
                                                <option value="">-- Select column --</option>
                                            </select>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary status-badge" data-index="{{ $index }}">Skip</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div class="alert alert-info mt-3" id="mappingSummary">
                            <i class="la la-info-circle"></i>
                            Assign each header to a table and column. You can map headers to different tables in the same import.
                        </div>
                    </div>
                </div>

                {{-- Sample Data Preview --}}
                @if(!empty($sampleRows))
                    <div class="card mt-3">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="la la-table"></i> Sample Data (first {{ count($sampleRows) }} rows)
                            </h3>
                        </div>
                        <div class="card-body table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr>
                                        @foreach($headers as $header)
                                            <th>{{ $header }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($sampleRows as $row)
                                        <tr>
                                            @foreach($headers as $index => $header)
                                                <td>{{ $row[$index] ?? '' }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Action Buttons --}}
                <div class="card mt-3">
                    <div class="card-body d-flex justify-content-between">
                        <a href="{{ url(config('backpack.base.route_prefix') . '/import/excel-upload') }}" class="btn btn-secondary">
                            <i class="la la-arrow-left"></i> Back
                        </a>

                        <button type="submit" class="btn btn-success" id="confirmBtn" disabled
                                onclick="return confirm('Are you sure you want to import? This cannot be undone.')">
                            <i class="la la-check"></i> Confirm &amp; Import
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('after_scripts')
<script>
    const tableColumns = @json($tableColumns);
    const suggestedMappings = @json($suggestedMappings);

    const tableSelects = document.querySelectorAll('.table-select');
    const columnSelects = document.querySelectorAll('.column-select');
    const confirmBtn = document.getElementById('confirmBtn');
    const summaryEl = document.getElementById('mappingSummary');

    // ---- Initialise: apply suggested mappings ----
    document.addEventListener('DOMContentLoaded', function () {
        Object.keys(suggestedMappings).forEach(function (idx) {
            const suggestion = suggestedMappings[idx];
            const tSelect = document.querySelector('.table-select[data-index="' + idx + '"]');
            if (tSelect && suggestion.table) {
                tSelect.value = suggestion.table;
                populateColumnSelect(idx, suggestion.table, suggestion.column);
            }
        });
        updateSummary();
    });

    // ---- Table select change ----
    tableSelects.forEach(function (tSelect) {
        tSelect.addEventListener('change', function () {
            const idx = this.dataset.index;
            const table = this.value;
            populateColumnSelect(idx, table, null);
            updateSummary();
        });
    });

    // ---- Column select change ----
    columnSelects.forEach(function (cSelect) {
        cSelect.addEventListener('change', function () {
            updateBadge(this.dataset.index);
            updateSummary();
        });
    });

    function populateColumnSelect(idx, table, preselect) {
        const cSelect = document.querySelector('.column-select[data-index="' + idx + '"]');
        cSelect.innerHTML = '<option value="">-- Select column --</option>';

        if (!table) {
            cSelect.disabled = true;
            cSelect.value = '';
            updateBadge(idx);
            return;
        }

        const cols = tableColumns[table] || [];
        cols.forEach(function (col) {
            const opt = document.createElement('option');
            opt.value = col;
            opt.textContent = col.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            cSelect.appendChild(opt);
        });

        cSelect.disabled = false;

        if (preselect) {
            cSelect.value = preselect;
        }

        updateBadge(idx);
    }

    function updateBadge(idx) {
        const tSelect = document.querySelector('.table-select[data-index="' + idx + '"]');
        const cSelect = document.querySelector('.column-select[data-index="' + idx + '"]');
        const badge = document.querySelector('.status-badge[data-index="' + idx + '"]');

        if (tSelect.value && cSelect.value) {
            badge.textContent = ucfirst(tSelect.value.replace(/_/g, ' '));
            badge.className = 'badge status-badge ' + tableColor(tSelect.value);
        } else if (tSelect.value && !cSelect.value) {
            badge.textContent = 'Pick col';
            badge.className = 'badge bg-warning text-dark status-badge';
        } else {
            badge.textContent = 'Skip';
            badge.className = 'badge bg-secondary status-badge';
        }
        badge.dataset.index = idx;
    }

    function tableColor(table) {
        switch (table) {
            case 'beneficiaries': return 'bg-primary';
            case 'profiles': return 'bg-success';
            case 'transactions': return 'bg-info';
            default: return 'bg-dark';
        }
    }

    function ucfirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    function checkDuplicates() {
        // Duplicates = same table+column mapped twice
        const combos = {};
        let hasDup = false;

        document.querySelectorAll('.table-select').forEach(function (tSelect) {
            const idx = tSelect.dataset.index;
            const cSelect = document.querySelector('.column-select[data-index="' + idx + '"]');
            const badge = document.querySelector('.status-badge[data-index="' + idx + '"]');

            if (tSelect.value && cSelect.value) {
                const key = tSelect.value + '.' + cSelect.value;
                if (combos[key] !== undefined) {
                    hasDup = true;
                    badge.textContent = 'Duplicate!';
                    badge.className = 'badge bg-danger status-badge';
                    badge.dataset.index = idx;

                    const otherBadge = document.querySelector('.status-badge[data-index="' + combos[key] + '"]');
                    otherBadge.textContent = 'Duplicate!';
                    otherBadge.className = 'badge bg-danger status-badge';
                    otherBadge.dataset.index = combos[key];
                } else {
                    combos[key] = idx;
                }
            }
        });

        return hasDup;
    }

    function updateSummary() {
        // First reset all badges
        document.querySelectorAll('.table-select').forEach(function (tSelect) {
            updateBadge(tSelect.dataset.index);
        });

        const hasDup = checkDuplicates();

        let mapped = 0;
        let tables = {};

        document.querySelectorAll('.table-select').forEach(function (tSelect) {
            const idx = tSelect.dataset.index;
            const cSelect = document.querySelector('.column-select[data-index="' + idx + '"]');
            if (tSelect.value && cSelect.value) {
                mapped++;
                tables[tSelect.value] = (tables[tSelect.value] || 0) + 1;
            }
        });

        if (hasDup) {
            summaryEl.innerHTML = '<i class="la la-exclamation-triangle"></i> <strong>Duplicate mapping detected!</strong> The same table + column cannot be assigned twice.';
            summaryEl.className = 'alert alert-danger mt-3';
            confirmBtn.disabled = true;
        } else if (mapped === 0) {
            summaryEl.innerHTML = '<i class="la la-info-circle"></i> Assign at least one header to a table and column to enable import.';
            summaryEl.className = 'alert alert-info mt-3';
            confirmBtn.disabled = true;
        } else {
            let parts = [];
            Object.keys(tables).forEach(function (t) {
                parts.push('<strong>' + tables[t] + '</strong> to ' + t.replace(/_/g, ' '));
            });
            summaryEl.innerHTML = '<i class="la la-check-circle"></i> ' + parts.join(', ') + '. <strong>' + mapped + '</strong> headers mapped total.';
            summaryEl.className = 'alert alert-success mt-3';
            confirmBtn.disabled = false;
        }
    }
</script>
@endpush
