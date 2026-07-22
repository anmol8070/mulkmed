@extends('include.app')
@section('header')
    <script src="{{ asset('asset/script/best_offers/userPlans.js') }}?v=1.2"></script>

@endsection

@section('content')
    <style>
        #Section2 table.dataTable td {
            white-space: normal !important;
        }

        .w-30 {
            width: 30% !important;
        }
    </style>
    <div class="card mt-3">

        <div class="card-header">
            <h4>{{ __('Plan Purchased By Users') }}</h4>
        </div>
        
        <div class="card-body">
            <ul class="nav nav-pills border-b mb-3  ml-0">

                <li role="presentation" class="nav-item"><a class="nav-link pointer active" href="#Section1"
                        aria-controls="home" role="tab" data-toggle="tab">{{ __('Plan Purchased By Users') }}<span
                            class="badge badge-transparent "></span></a>
                </li>

                
            </ul>

            <div class="tab-content tabs" id="home">
                {{-- Section 1 --}}
                <div role="tabpanel" class="row tab-pane active" id="Section1">
                    <div class="table-responsive col-12">
                        <table class="table table-striped w-100" id="bannerTable">
                            <thead>
                                <tr>
                                    <th>{{ __('Offer Name') }}</th>
                                    <th>{{ __('Price') }}</th>
                                    <th>{{ __('Purchased At') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
               
            </div>

        </div>
    </div>
    
@endsection
