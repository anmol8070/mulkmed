@extends('include.app')
@section('header')
    <script src="{{ asset('asset/script/partner_network.js') }}?v=1.2"></script>
    

@endsection

@section('content')
    <style>
        #Section2 table.dataTable td {
            white-space: normal !important;
        }

        .w-30 {
            width: 30% !important;
        }
        .modelpartners{
                    max-width: 1000px !important;
        }
    </style>
    <div class="card mt-3">
        <div class="card-header">
            <h4>{{ __('Partners Network') }}</h4>

            <a data-toggle="modal" data-target="#addBannerModal" href="" id="addButton"
                class="ml-auto btn btn-primary text-white">{{ __('Add Partner') }}</a>
        </div>
        <div class="card-body">
            <ul class="nav nav-pills border-b mb-3  ml-0">

                <li role="presentation" class="nav-item"><a class="nav-link pointer active" href="#Section1"
                        aria-controls="home" role="tab" data-toggle="tab">{{ __('Partners Network') }}<span
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
                                    <th>{{ __('Image') }}</th>
                                    <th>{{ __('Title') }}</th>
                                    <th>{{ __('Headline') }}</th>
                                    <th>{{ __('Hospital_name') }}</th>
                                    <th>{{ __('Address') }}</th>
                                    <th>{{ __('Website_link') }}</th>
                                    <th>{{ __('Action') }}</th>
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

    {{-- Edit Banner Modal --}}
    <div class="modal fade" id="editBannerModel" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modelpartners" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>{{ __('Edit Banner') }}</h5>

                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                <form action="" method="post" enctype="multipart/form-data" id="editBannerForm" autocomplete="off">
                    @csrf

                    <input type="hidden" name="id" value="" id="editPartnerId">

                    <div class="form-group">
                        <label>{{ __('Image') }}</label>
                        <input id="editImage" type="file" name="image" accept="image/*" class="form-control">
                        <div id="EditpreviewContainer">
                        
                            <label class="mt-3"> {{ __('Image Preview') }}</label>
                            <img id="editpreviewImage" 
                                src=""
                                alt="Preview" 
                            style="width:120px; height:100px; display:block;border:1px solid #ccc; border-radius:6px;">
                        </div>
                    </div>

                    <div class="form-group" id="editSectionContainer">
                        <label>{{ __('Title') }}</label>
                       <Input type="text" value="" name="title" id="editTitle" class="form-control"></Input>
                    </div>

                    <div class="form-group" id="editSectionContainer">
                        <label>{{ __('Headline') }}</label>
                       <Input type="text" value="" name="headline" id="editHeadline" class="form-control"></Input>
                    </div>

                    <div class="form-group" id="editSectionContainer">
                        <label>{{ __('Hospital_name') }}</label>
                       <Input type="text" value="" name="hospital_name" id="editHospitalName" class="form-control"></Input>
                    </div>

                    <div class="form-group" id="editSectionContainer">
                        <label>{{ __('Address') }}</label>
                       <Input type="text" value="" name="address" id="editAddress" class="form-control"></Input>
                    </div>

                    <div class="form-group" id="editSectionContainer">
                        <label>{{ __('Website_link') }}</label>
                       <Input type="text" value="" name="website_link" id="editWebsiteLink" class="form-control"></Input>
                    </div>

                    <div class="form-group">
                        <label>{{ __('Content') }}</label>
                        <textarea id="summernote_banner_edit" class="summernote-simple" name="data"></textarea>
                    </div>

                    <div class="form-group">
                        <input class="btn btn-primary mr-1" type="submit" value="{{ __('Submit') }}">
                    </div>
                </form>
            </div>

            </div>
        </div>
    </div>
    {{-- Add Banner Modal --}}
   <div class="modal fade" id="addBannerModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
    aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modelpartners" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>{{ __('Add Parntner') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <form action="" method="post" enctype="multipart/form-data" id="addBannerForm" autocomplete="off">
                        @csrf

                        <div class="form-group">
                            <label>{{ __('Image') }}</label>
                            <input id="addImage" type="file" name="image" accept="image/*" class="form-control" required>
                            <div id="addPreviewContainer">
                            
                                <label class="mt-3"> {{ __('Image Preview') }}</label>
                                <img id="addPreviewImage" 
                                    src=""
                                    alt="Preview" 
                                style="width:120px; height:100px; display:block;border:1px solid #ccc; border-radius:6px;">
                            </div>
                        </div>

                        <div class="form-group" id="addSectionContainer">
                            <label>{{ __('Title') }}</label>
                        <Input type="text" value="" name="title" id="" class="form-control"></Input>
                        </div>

                        <div class="form-group" id="addSectionContainer">
                            <label>{{ __('headline') }}</label>
                        <Input type="text" value="" name="headline" id="" class="form-control"></Input>
                        </div>

                        <div class="form-group" id="addSectionContainer">
                            <label>{{ __('hospital_name') }}</label>
                        <Input type="text" value="" name="hospital_name" id="" class="form-control"></Input>
                        </div>

                        <div class="form-group" id="addSectionContainer">
                            <label>{{ __('address') }}</label>
                        <Input type="text" value="" name="address" id="" class="form-control"></Input>
                        </div>

                        <div class="form-group" id="addSectionContainer">
                            <label>{{ __('website_link') }}</label>
                        <Input type="text" value="" name="website_link" id="" class="form-control"></Input>
                        </div>

                        <div class="form-group">
                            <label>{{ __('Content') }}</label>
                            <textarea id="summernote_banner_add" class="summernote-simple" name="data"></textarea>
                        </div>

                        <div class="form-group">
                            <input class="btn btn-primary mr-1" type="submit" value="{{ __('Submit') }}">
                        </div>
                    </form>
                </div>
            </div>
        </div>
</div>

</div>

        </div>
    </div>
@endsection
