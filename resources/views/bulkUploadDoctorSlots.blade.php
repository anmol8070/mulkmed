@extends('include.app')

@section('header')
    <script src="{{ asset('asset/script/bulkUploadDoctorSlots.js') }}"></script>
@endsection

@section('content')
    <div class="card mt-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4>{{ __('Bulk Upload Doctors Slots') }}</h4>

            {{-- ✅ Download Format Button --}}
            <a href="{{ route('downloadDoctorSlotFormat') }}" class="btn btn-success">
                <i class="fa fa-download"></i> {{ __('Download Format') }}
            </a>
        </div>

        <form class="product-form" action="{{ route('bulkUploadDoctorSlots') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="card mt-2 rest-part">
                <div class="card-body">
                    <div class="form-group">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <input type="file" name="file" class="form-control" required>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary">{{ __('Submit') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection
