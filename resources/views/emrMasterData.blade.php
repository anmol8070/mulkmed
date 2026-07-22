@extends('include.app')

@section('content')
    <div class="card mt-3" data-page-toast="1">
        <div class="card-header">
            <h4>{{ __('EMR Master Data') }}</h4>
        </div>
        <div class="card-body">
            <h6 class="mb-2">{{ __('Add Diagnosis Type') }}</h6>
            <form method="POST" action="{{ route('addEmrMasterData') }}" class="mb-4">
                @csrf
                <input type="hidden" name="category" value="diagnosis_type">
                <div class="form-row">
                    <div class="form-group col-md-10">
                        <label>{{ __('Diagnosis Type') }}</label>
                        <input type="text" class="form-control" name="name" placeholder="e.g. Principal" required>
                    </div>
                    <div class="form-group col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-block">{{ __('Add') }}</button>
                    </div>
                </div>
            </form>

            <h6 class="mb-2">{{ __('Add Diagnosis') }}</h6>
            <form method="POST" action="{{ route('addEmrMasterData') }}" class="mb-4">
                @csrf
                <input type="hidden" name="category" value="diagnosis">
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label>{{ __('Diagnosis Type') }}</label>
                        <select class="form-control" name="diagnosis_type" required>
                            <option value="">{{ __('Select Diagnosis Type') }}</option>
                            @foreach ($diagnosisTypes as $type)
                                <option value="{{ $type }}">{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-7">
                        <label>{{ __('Diagnosis') }}</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="form-group col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-block">{{ __('Add') }}</button>
                    </div>
                </div>
            </form>

            <h6 class="mb-2">{{ __('Add Lab Order') }}</h6>
            <form method="POST" action="{{ route('addEmrMasterData') }}" class="mb-4">
                @csrf
                <input type="hidden" name="category" value="lab_order">
                <div class="form-row">
                    <div class="form-group col-md-10">
                        <label>{{ __('Lab Order') }}</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="form-group col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-block">{{ __('Add') }}</button>
                    </div>
                </div>
            </form>

            <h6 class="mb-2">{{ __('Add Radiology Order') }}</h6>
            <form method="POST" action="{{ route('addEmrMasterData') }}" class="mb-4">
                @csrf
                <input type="hidden" name="category" value="radiology_order">
                <div class="form-row">
                    <div class="form-group col-md-10">
                        <label>{{ __('Radiology Order') }}</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="form-group col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-block">{{ __('Add') }}</button>
                    </div>
                </div>
            </form>

            <h6 class="mb-2">{{ __('Add Drug') }}</h6>
            <form method="POST" action="{{ route('addEmrMasterData') }}" class="mb-4">
                @csrf
                <input type="hidden" name="category" value="drug">
                <div class="form-row">
                    <div class="form-group col-md-10">
                        <label>{{ __('Drug') }}</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="form-group col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-block">{{ __('Add') }}</button>
                    </div>
                </div>
            </form>

            <div class="mb-3 d-flex align-items-center">
                <button type="button" id="toggle-bulk-upload" class="btn btn-info btn-sm">
                    {{ __('Bulk Upload') }}
                </button>
                <span class="ml-3 text-muted" style="text-decoration: underline;">
                    {{ __('Click here to upload bulk data') }}
                </span>
            </div>

            <div id="bulk-upload-section" style="display:none;">
                <div class="mb-3">
                    <button type="button" class="btn btn-primary btn-sm mr-2 bulk-upload-btn active" data-target="bulk-diagnosis-type-wrap">
                        {{ __('Diagnosis Types') }}
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm mr-2 bulk-upload-btn" data-target="bulk-diagnosis-wrap">
                        {{ __('Diagnosis') }}
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm mr-2 bulk-upload-btn" data-target="bulk-lab-wrap">
                        {{ __('Lab Orders') }}
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm mr-2 bulk-upload-btn" data-target="bulk-radiology-wrap">
                        {{ __('Radiology Orders') }}
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm bulk-upload-btn" data-target="bulk-drug-wrap">
                        {{ __('Drugs') }}
                    </button>
                </div>

                <div id="bulk-diagnosis-type-wrap" class="bulk-upload-wrap">
                    <form method="POST" action="{{ route('bulkUploadEmrMasterData') }}" enctype="multipart/form-data" class="mb-4">
                        @csrf
                        <input type="hidden" name="category" value="diagnosis_type">
                        <label>{{ __('Bulk Upload Diagnosis Types (Excel)') }}</label>
                        <div class="form-row align-items-center">
                            <div class="form-group col-md-8 mb-0">
                                <input type="file" class="form-control" name="customer_file" accept=".xlsx,.xls,.csv" required>
                            </div>
                            <div class="form-group col-md-4 mb-0">
                                <button type="submit" class="btn btn-outline-primary btn-block">{{ __('Upload Excel') }}</button>
                            </div>
                        </div>
                        <small>
                            <a href="{{ route('downloadEmrMasterTemplate', ['category' => 'diagnosis_type']) }}">{{ __('Download Dummy Excel') }}</a>
                        </small>
                    </form>
                </div>

                <div id="bulk-diagnosis-wrap" class="bulk-upload-wrap" style="display:none;">
                    <form method="POST" action="{{ route('bulkUploadEmrMasterData') }}" enctype="multipart/form-data" class="mb-4">
                        @csrf
                        <input type="hidden" name="category" value="diagnosis">
                        <label>{{ __('Bulk Upload Diagnosis (Excel)') }}</label>
                        <div class="form-row align-items-center">
                            <div class="form-group col-md-8 mb-0">
                                <input type="file" class="form-control" name="customer_file" accept=".xlsx,.xls,.csv" required>
                            </div>
                            <div class="form-group col-md-4 mb-0">
                                <button type="submit" class="btn btn-outline-primary btn-block">{{ __('Upload Excel') }}</button>
                            </div>
                        </div>
                        <small>
                            {{ __('Required columns: name, diagnosis_type') }}.
                            <a href="{{ route('downloadEmrMasterTemplate', ['category' => 'diagnosis']) }}">{{ __('Download Dummy Excel') }}</a>
                        </small>
                    </form>
                </div>

                <div id="bulk-lab-wrap" class="bulk-upload-wrap" style="display:none;">
                    <form method="POST" action="{{ route('bulkUploadEmrMasterData') }}" enctype="multipart/form-data" class="mb-4">
                        @csrf
                        <input type="hidden" name="category" value="lab_order">
                        <label>{{ __('Bulk Upload Lab Orders (Excel)') }}</label>
                        <div class="form-row align-items-center">
                            <div class="form-group col-md-8 mb-0">
                                <input type="file" class="form-control" name="customer_file" accept=".xlsx,.xls,.csv" required>
                            </div>
                            <div class="form-group col-md-4 mb-0">
                                <button type="submit" class="btn btn-outline-primary btn-block">{{ __('Upload Excel') }}</button>
                            </div>
                        </div>
                        <small>
                            <a href="{{ route('downloadLabOrderDummyExcel') }}">{{ __('Download Lab Order Dummy Excel') }}</a>
                        </small>
                    </form>
                </div>

                <div id="bulk-radiology-wrap" class="bulk-upload-wrap" style="display:none;">
                    <form method="POST" action="{{ route('bulkUploadEmrMasterData') }}" enctype="multipart/form-data" class="mb-4">
                        @csrf
                        <input type="hidden" name="category" value="radiology_order">
                        <label>{{ __('Bulk Upload Radiology Orders (Excel)') }}</label>
                        <div class="form-row align-items-center">
                            <div class="form-group col-md-8 mb-0">
                                <input type="file" class="form-control" name="customer_file" accept=".xlsx,.xls,.csv" required>
                            </div>
                            <div class="form-group col-md-4 mb-0">
                                <button type="submit" class="btn btn-outline-primary btn-block">{{ __('Upload Excel') }}</button>
                            </div>
                        </div>
                        <small>
                            <a href="{{ route('downloadEmrMasterTemplate', ['category' => 'radiology_order']) }}">{{ __('Download Dummy Excel') }}</a>
                        </small>
                    </form>
                </div>

                <div id="bulk-drug-wrap" class="bulk-upload-wrap" style="display:none;">
                    <form method="POST" action="{{ route('bulkUploadEmrMasterData') }}" enctype="multipart/form-data" class="mb-4">
                        @csrf
                        <input type="hidden" name="category" value="drug">
                        <label>{{ __('Bulk Upload Drugs (Excel)') }}</label>
                        <div class="form-row align-items-center">
                            <div class="form-group col-md-8 mb-0">
                                <input type="file" class="form-control" name="customer_file" accept=".xlsx,.xls,.csv" required>
                            </div>
                            <div class="form-group col-md-4 mb-0">
                                <button type="submit" class="btn btn-outline-primary btn-block">{{ __('Upload Excel') }}</button>
                            </div>
                        </div>
                        <small>
                            <a href="{{ route('downloadEmrMasterTemplate', ['category' => 'drug']) }}">{{ __('Download Dummy Excel') }}</a>
                        </small>
                    </form>
                </div>
            </div>

            <hr>

            <div class="mb-3">
                <button type="button" class="btn btn-primary btn-sm mr-2 master-list-btn active" data-target="diagnosis-type-list-wrap">
                    {{ __('Diagnosis Types List') }}
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm mr-2 master-list-btn" data-target="diagnosis-list-wrap">
                    {{ __('Diagnosis List') }}
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm mr-2 master-list-btn" data-target="lab-list-wrap">
                    {{ __('Lab Orders List') }}
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm master-list-btn" data-target="radiology-list-wrap">
                    {{ __('Radiology Orders List') }}
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm master-list-btn" data-target="drug-list-wrap">
                    {{ __('Drugs List') }}
                </button>
            </div>

            <div id="diagnosis-type-list-wrap" class="master-list-wrap">
            <h6>{{ __('Diagnosis Types List') }}</h6>
            <div class="table-responsive mb-3">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>{{ __('Diagnosis Type') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($diagnosisTypeRows as $item)
                            <tr>
                                <td>{{ $item->name }}</td>
                                <td>
                                    <form method="POST" action="{{ route('deleteEmrMasterData', $item->id) }}" class="emr-delete-form">
                                        @csrf
                                        <button type="submit" class="btn btn-danger btn-sm">{{ __('Delete') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2">{{ __('No data found') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            </div>

            <div id="diagnosis-list-wrap" class="master-list-wrap" style="display:none;">
            <h6>{{ __('Diagnosis List') }}</h6>
            <div class="table-responsive mb-3">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Diagnosis') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($diagnosis as $item)
                            <tr>
                                <td>{{ $item->diagnosis_type }}</td>
                                <td>{{ $item->name }}</td>
                                <td>
                                    <form method="POST" action="{{ route('deleteEmrMasterData', $item->id) }}" class="emr-delete-form">
                                        @csrf
                                        <button type="submit" class="btn btn-danger btn-sm">{{ __('Delete') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3">{{ __('No data found') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            </div>

            <div id="lab-list-wrap" class="master-list-wrap" style="display:none;">
            <h6>{{ __('Lab Orders List') }}</h6>
            <div class="table-responsive mb-3">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>{{ __('Lab Order') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($labOrders as $item)
                            <tr>
                                <td>{{ $item->name }}</td>
                                <td>
                                    <form method="POST" action="{{ route('deleteEmrMasterData', $item->id) }}" class="emr-delete-form">
                                        @csrf
                                        <button type="submit" class="btn btn-danger btn-sm">{{ __('Delete') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2">{{ __('No data found') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            </div>

            <div id="radiology-list-wrap" class="master-list-wrap" style="display:none;">
            <h6>{{ __('Radiology Orders List') }}</h6>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>{{ __('Radiology Order') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($radiologyOrders as $item)
                            <tr>
                                <td>{{ $item->name }}</td>
                                <td>
                                    <form method="POST" action="{{ route('deleteEmrMasterData', $item->id) }}" class="emr-delete-form">
                                        @csrf
                                        <button type="submit" class="btn btn-danger btn-sm">{{ __('Delete') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2">{{ __('No data found') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            </div>

            <div id="drug-list-wrap" class="master-list-wrap" style="display:none;">
            <h6>{{ __('Drugs List') }}</h6>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>{{ __('Drug') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($drugs as $item)
                            <tr>
                                <td>{{ $item->name }}</td>
                                <td>
                                    <form method="POST" action="{{ route('deleteEmrMasterData', $item->id) }}" class="emr-delete-form">
                                        @csrf
                                        <button type="submit" class="btn btn-danger btn-sm">{{ __('Delete') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2">{{ __('No data found') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            </div>
        </div>
    </div>

    <script>
        (function() {
            const buttons = document.querySelectorAll('.master-list-btn');
            const sections = document.querySelectorAll('.master-list-wrap');
            const bulkButtons = document.querySelectorAll('.bulk-upload-btn');
            const bulkSections = document.querySelectorAll('.bulk-upload-wrap');
            const bulkToggleBtn = document.getElementById('toggle-bulk-upload');
            const bulkUploadSection = document.getElementById('bulk-upload-section');

            @if(session('success'))
                if (typeof iziToast !== 'undefined') {
                    iziToast.success({
                        title: 'Success',
                        message: @json(session('success')),
                        position: 'topRight'
                    });
                }
            @endif
            @if(session('error'))
                if (typeof iziToast !== 'undefined') {
                    iziToast.error({
                        title: 'Error',
                        message: @json(session('error')),
                        position: 'topRight'
                    });
                }
            @endif
            @if($errors->any())
                if (typeof iziToast !== 'undefined') {
                    iziToast.error({
                        title: 'Error',
                        message: @json($errors->first()),
                        position: 'topRight'
                    });
                }
            @endif

            buttons.forEach((btn) => {
                btn.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');

                    sections.forEach((section) => {
                        section.style.display = section.id === targetId ? 'block' : 'none';
                    });

                    buttons.forEach((b) => {
                        b.classList.remove('btn-primary', 'active');
                        b.classList.add('btn-outline-primary');
                    });

                    this.classList.remove('btn-outline-primary');
                    this.classList.add('btn-primary', 'active');
                });
            });

            if (bulkToggleBtn && bulkUploadSection) {
                bulkToggleBtn.addEventListener('click', function() {
                    const isHidden = bulkUploadSection.style.display === 'none';
                    bulkUploadSection.style.display = isHidden ? 'block' : 'none';
                });

                @if(session('success') || session('error') || $errors->any())
                    bulkUploadSection.style.display = 'block';
                @endif
            }

            bulkButtons.forEach((btn) => {
                btn.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');

                    bulkSections.forEach((section) => {
                        section.style.display = section.id === targetId ? 'block' : 'none';
                    });

                    bulkButtons.forEach((b) => {
                        b.classList.remove('btn-primary', 'active');
                        b.classList.add('btn-outline-primary');
                    });

                    this.classList.remove('btn-outline-primary');
                    this.classList.add('btn-primary', 'active');
                });
            });

            document.querySelectorAll('.emr-delete-form').forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var submitForm = function() {
                        form.submit();
                    };

                    if (typeof swal === 'function') {
                        swal({
                            title: (typeof strings !== 'undefined' && strings.doYouReallyWantToContinue)
                                ? strings.doYouReallyWantToContinue
                                : 'Do you really want to continue?',
                            text: 'This item will be deleted.',
                            icon: 'warning',
                            buttons: true,
                            dangerMode: true,
                        }).then(function(isConfirm) {
                            if (isConfirm) {
                                submitForm();
                            }
                        });
                    } else if (window.confirm('Do you really want to continue? This item will be deleted.')) {
                        submitForm();
                    }
                });
            });
        })();
    </script>
@endsection
