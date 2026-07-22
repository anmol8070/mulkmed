@extends('include.app')
@section('header')
    <script src="{{ asset('asset/script/smo/bulkUploadwhySecondOpinionMatters.js?v=1.0') }}"></script>
@endsection

@section('content')
    <div class="card mt-3">
         <div class="card-header d-flex justify-content-between align-items-center">
            <h4>{{ __('Bulk Upload Why Second Opinion Matters') }}</h4>

            {{-- Download Format Button --}}
            <a href="{{ route('smo.downloadWhySecondOpinionMattersFormat') }}" class="btn btn-success">
                <i class="fa fa-download"></i> {{ __('Download Format') }}
            </a>
        </div>

        <form class="product-form" action="{{route('smo.bulkUploadWhySecondOpinionMatters')}}" method="POST" enctype="multipart/form-data">
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
