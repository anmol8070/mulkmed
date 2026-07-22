@extends('include.app')
@section('header')
    <script src="{{ asset('asset/script/bulkUploadDoctors.js') }}"></script>
@endsection

@section('content')
    <div class="card mt-3">
        <div class="card-header">
            <h4>{{ __('Bulk Update Doctor Mobile Number') }}</h4>
            <a href="{{route('downloadBulkUpdateDoctorMobileFormat')}}" class="btn btn-success ml-2"><i class="fas fa-download"></i> {{ __('Download Format') }}</a>
        </div>

        <form class="product-form" action="{{route('bulkUpdateDoctorMobile')}}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="card mt-2 rest-part">
                <div class="card-body">
                    <div class="form-group">
                        <div class="row">
                            <!-- <div class="col-md-12 mb-3">
                                <label>Upload File (Excel with columns: id, country_code, mobile_number)</label>
                            </div> -->
                            <div class="col-md-4">
                                <input type="file" name="update_file" required>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary">{{ __('Submit')}}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection
