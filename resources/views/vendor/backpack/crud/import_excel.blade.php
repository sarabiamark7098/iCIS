@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
        <h1 class="text-capitalize mb-0" bp-section="page-heading">Import Excel</h1>
        <p class="ms-2 ml-2 mb-0" bp-section="page-subheading">Upload an Excel file to import data.</p>
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
                    <h3 class="card-title">Step 1: Upload Excel File</h3>
                </div>
                <div class="card-body">
                    <form action="{{ route('import.excel-preview') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label for="file" class="form-label fw-bold">Excel File</label>
                            <input type="file" name="file" id="file" class="form-control" accept=".xlsx,.xls,.csv" required>
                            <small class="form-text text-muted">Accepted formats: .xlsx, .xls, .csv (max 50MB). The first row of each sheet must contain column headers. Multi-sheet files are supported.</small>
                        </div>

                        @if($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="d-flex justify-content-between">
                            <a href="{{ url($crud->route) }}" class="btn btn-secondary">
                                <i class="la la-arrow-left"></i> Back
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="la la-upload"></i> Upload &amp; Read Headers
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">How It Works</h3>
                </div>
                <div class="card-body">
                    <ol>
                        <li><strong>Upload your file</strong> &mdash; The first row of each sheet must be column headers.</li>
                        <li><strong>Map the headers</strong> &mdash; Assign each header to a table and column. You can map to multiple tables at once (beneficiaries, profiles, transactions).</li>
                        <li><strong>Confirm &amp; Import</strong> &mdash; Review your mapping and insert the data. Large files (1000+ rows) are processed in the background.</li>
                    </ol>
                    <div class="alert alert-info mb-0 mt-2">
                        <i class="la la-lightbulb"></i>
                        <strong>Name logic:</strong> If a row has names mapped to both <em>beneficiaries</em> and <em>profiles</em>,
                        two separate records are created and linked. If only one name is present, it is saved as a beneficiary with relationship set to "Self".
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
