@extends('include.app')
@section('header')
    <script src="{{ asset('asset/script/best_offers/bestOffersPlans.js') }}?v=1.5"></script>

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
            <h4>{{ __('Best Offer Plans') }}</h4>

            <a data-toggle="modal" data-target="#addBannerModal" href="" id="addButton"
                class="ml-auto btn btn-primary text-white">{{ __('Add Best Offer Plans') }}</a>
        </div>
        <div class="card-body">
            <ul class="nav nav-pills border-b mb-3  ml-0">

                <li role="presentation" class="nav-item"><a class="nav-link pointer active" href="#Section1"
                        aria-controls="home" role="tab" data-toggle="tab">{{ __('Best Offer Plans') }}<span
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
                                    <th>{{ __('Price') }}</th>
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
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>{{ __('Edit Plan') }}</h5>  

                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    <form action="" method="post" enctype="multipart/form-data" id="editBannerForm" autocomplete="off">
                        @csrf

                        <input type="hidden" name="id" id="editBannerId">

                        <div class="form-group">
                            <label> {{ __('Banner') }}</label>
                            <input id="editImage" type="file" name="image" accept="image/*" class="form-control" >
                            <div id="editPreviewContainer">
                            
                                <label class="mt-3"> {{ __('Image Preview') }}</label>
                                <img id="editPreviewImage" 
                                    src=""
                                    alt="Preview" 
                                style="width:120px; height:120px; display:block;border:1px solid #ccc; border-radius:6px;object-fit: cover;">
                            </div>
                        </div>

                        <div class="form-group">
                            <label> {{ __('Detail Image') }}</label>
                            <input id="editImageDetail" type="file" name="detail_image" accept="image/*" class="form-control" >
                            <div id="editPreviewContainer">
                            
                                <label class="mt-3"> {{ __('Image Preview') }}</label>
                                <img id="editPreviewDetailImage" 
                                    src=""
                                    alt="Preview" 
                                style="width:120px; height:120px; display:block;border:1px solid #ccc; border-radius:6px;object-fit: cover;">
                            </div>
                        </div>

                         <div class="form-group" id="editSectionContainer">
                            <label> {{ __('Title') }}</label>
                            <input type="text" id="editTitle" name="title" class="form-control" required/>
                        </div>

                        <div class="form-group" id="editSectionContainer">
                            <label> {{ __('Price Description') }}</label>
                            <input type="text" id="editPriceDescription" name="price_description" class="form-control" required/>
                        </div>

                         <div class="form-group" id="editSectionContainer">
                            <label> {{ __('Price') }}</label>
                            <input type="number" id="editPrice"  name="price" class="form-control" required/>
                        </div>

                        <div class="form-group" id="editSectionContainer">
                            <label> {{ __('Description') }}</label>
                            <input type="text" id="editDescription" name="description" class="form-control" required/>
                        </div>

                        <div class="form-group" id="editSectionContainer">
                            <label> {{ __('Benefits') }}</label>
                            <input type="text" id="editBenefits" name="benefits" class="form-control" required/>
                        </div>

                        {{-- <div class="form-group d-none" id="editSubsectionContainer">
                            <label> {{ __('Banner Redirection') }}</label>
                            <select id="editSubsection" name="redirection" class="form-control">
                                <option value>{{__('Select Redirection')}}</option>
                            </select>
                        </div>

                        <div class="form-group d-none" id="editDynamicDropdownContainer">
                            <label id="editDynamicDropdownLabel"> {{ __('Enter URL') }}</label>
                            <div id="editDynamicDropdown">
                            </div>
                        </div>

                        <div class="form-group d-none" id="editPageContainer">
                            <label> {{ __('Page') }}</label>
                            <select id="editPage" name="page" class="form-control">
                                <option value>{{__('Select Page')}}</option>                            
                            </select>
                        </div> --}}
                    <div class="form-group d-flex align-items-center">

                        <input 
                            id="submitBtn"
                            class="btn btn-primary mr-2" 
                            type="submit" 
                            value="{{ __('Submit') }}"
                        >

                        <!-- Loader (hidden initially) -->
                        <div id="formLoader" class="d-none">
                            <div class="spinner-border text-primary" role="status" style="width: 1.2rem; height: 1.2rem;"></div>
                        </div>

                    </div>


                    </form>
  
                </div>

            </div>
        </div>
    </div>
    {{-- Add Banner Modal --}}
    <div class="modal fade" id="addBannerModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>{{ __('Add Plan') }}</h5>

                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    <form action="" method="post" enctype="multipart/form-data" id="addBannerForm" autocomplete="off">
                        @csrf

                       <div class="form-group">
                            <label> {{ __('Banner') }}</label>
                            <input id="addImage" type="file" name="image" accept="image/*" class="form-control" required>
                            <div id="addPreviewContainer">
                            
                                <label class="mt-3"> {{ __('Image Preview') }}</label>
                                <img id="addPreviewImage" 
                                    src=""
                                    alt="Preview" 
                                style="width:120px; height:120px; display:block;border:1px solid #ccc; border-radius:6px;object-fit: cover;">
                            </div>
                        </div>

                        <div class="form-group">
                            <label> {{ __('Detail Image') }}</label>
                            <input id="addImageDetail" type="file" name="detail_image" accept="image/*" class="form-control" required>
                            <div id="addPreviewContainer">
                            
                                <label class="mt-3"> {{ __('Image Preview') }}</label>
                                <img id="addPreviewDetailImage" 
                                    src=""
                                    alt="Preview" 
                                style="width:120px; height:120px; display:block;border:1px solid #ccc; border-radius:6px;object-fit: cover;">
                            </div>
                        </div>

                         <div class="form-group" id="addSectionContainer">
                            <label> {{ __('Title') }}</label>
                            <input type="text" name="title" class="form-control" required/>
                        </div>

                        <div class="form-group" id="addSectionContainer">
                            <label> {{ __('Price Description') }}</label>
                            <input type="text" name="price_description" class="form-control" required/>
                        </div>

                         <div class="form-group" id="addSectionContainer">
                            <label> {{ __('Price') }}</label>
                            <input type="number" name="price" class="form-control" required/>
                        </div>

                        <div class="form-group" id="addSectionContainer">
                            <label> {{ __('Description') }}</label>
                            <input type="text" name="description" class="form-control" required/>
                        </div>

                        <div class="form-group" id="addSectionContainer">
                            <label> {{ __('Benefits') }}</label>
                            <input type="text" name="benefits" class="form-control" required/>
                        </div>

                        {{-- <div class="form-group d-none" id="addSubsectionContainer">
                            <label> {{ __('Banner Redirection') }}</label>
                            <select id="addSubsection" name="redirection" class="form-control">
                                <option value>{{__('Select Redirection')}}</option>
                            </select>
                        </div>

                        <div class="form-group d-none" id="addDynamicDropdownContainer">
                            <label id="addDynamicDropdownLabel"> {{ __('Select Option') }}</label>
                            <div id="addDynamicDropdown">
                                
                            </div>
                        </div>

                        <div class="form-group d-none" id="addPageContainer">
                            <label> {{ __('Page') }}</label>
                            <select id="addPage" name="page" class="form-control">
                                <option value>{{__('Select Page')}}</option>                            
                            </select>
                        </div> --}}
  
                         <div class="form-group d-flex align-items-center">

                        <input 
                            id="submitBtnAdd"
                            class="btn btn-primary mr-2" 
                            type="submit" 
                            value="{{ __('Submit') }}"
                        >

                        <!-- Loader (hidden initially) -->
                        <div id="formLoaderAdd" class="d-none">
                            <div class="spinner-border text-primary" role="status" style="width: 1.2rem; height: 1.2rem;"></div>
                        </div>

                    </div>

                    </form>

                </div>

            </div>
        </div>
    </div>
@endsection
