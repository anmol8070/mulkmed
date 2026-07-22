@extends('include.app')
@section('header')
    <script src="{{ asset('asset/script/bidding/bulkUploadBiddingServices.js') }}"></script>
@endsection

@section('content')
    <div class="card mt-3">
        <div class="card-header d-flex justify-content-between">
            <h4>{{ __('Bulk Upload Bidding Services') }}</h4>
            {{-- Download Format Button --}}
            <a href="{{ route('bidding.downloadBiddingServicesFormat') }}" class="btn btn-success">
                <i class="fa fa-download"></i> {{ __('Download Format') }}
            </a>
        </div>

        <form class="product-form" action="{{route('bidding.bulkUploadBiddingServices')}}" method="POST" enctype="multipart/form-data">
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
