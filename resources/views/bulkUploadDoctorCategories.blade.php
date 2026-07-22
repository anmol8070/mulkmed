@extends('include.app')
@section('header')
    <script src="{{ asset('asset/script/bulkUploadDoctorCategories.js') }}"></script>
@endsection

@section('content')
    <div class="card mt-3">
        <div class="card-header">
            <h4>{{ __('Bulk Upload Doctors By Speciality') }}</h4>
            <a href="{{ route('downloadDoctorCategoriesFormat') }}" class="btn btn-success">
                <i class="fa fa-download"></i> {{ __('Download Format') }}
            </a>
        </div>

        <form class="product-form" action="{{route('bulkUploadDoctorCategories')}}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="card mt-2 rest-part">
                <div class="card-body">
                    <div class="form-group">
                        <div class="row">
                            <div class="col-md-4">
                                <input type="file" name="customer_file">
                            </div>
                            <button type="submit" class="btn btn-primary">{{ __('Submit')}}</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection
