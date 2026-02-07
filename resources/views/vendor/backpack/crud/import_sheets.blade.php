@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
        <h1 class="text-capitalize mb-0" bp-section="page-heading">Select Sheet</h1>
        <p class="ms-2 ml-2 mb-0" bp-section="page-subheading">This file has multiple sheets. Choose which one to import.</p>
    </section>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Step 2: Select a Sheet</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($sheetsInfo as $index => $sheet)
                            <div class="col-md-6 mb-3">
                                <div class="card border h-100">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <i class="la la-file-excel text-success"></i>
                                            {{ $sheet['name'] }}
                                        </h5>
                                        <p class="card-text text-muted small mb-2">
                                            <strong>{{ $sheet['row_count'] }}</strong> rows &bull;
                                            <strong>{{ $sheet['header_count'] }}</strong> columns
                                        </p>
                                        <p class="card-text small mb-3">
                                            <strong>Headers:</strong>
                                            {{ implode(', ', $sheet['sample_headers']) }}
                                            @if($sheet['header_count'] > 6)
                                                <span class="text-muted">... and {{ $sheet['header_count'] - 6 }} more</span>
                                            @endif
                                        </p>
                                        <form action="{{ route('import.excel-select-sheet') }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="sheet_index" value="{{ $index }}">
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                <i class="la la-arrow-right"></i> Use This Sheet
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body">
                    <a href="{{ url(config('backpack.base.route_prefix') . '/import/excel-upload') }}" class="btn btn-secondary">
                        <i class="la la-arrow-left"></i> Back - Upload Different File
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
