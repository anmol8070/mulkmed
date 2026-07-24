@extends('include.app')
@section('header')
    <script src="{{ asset('asset/script/longevityPlans.js') }}?v=1.0"></script>
@endsection

@section('content')
    <style>
        .dynamic-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .dynamic-row .form-control {
            flex: 1;
        }
    </style>

    <div class="card mt-3">
        <div class="card-header">
            <h4>{{ __('Longevity Plans') }}</h4>
            <a data-toggle="modal" data-target="#addPlanModal" href=""
                class="ml-auto btn btn-primary text-white">{{ __('Add Longevity Plan') }}</a>
        </div>
        <div class="card-body">
            <div class="table-responsive col-12">
                <table class="table table-striped w-100" id="longevityPlansTable">
                    <thead>
                        <tr>
                            <th>{{ __('Image') }}</th>
                            <th>{{ __('Title') }}</th>
                            <th>{{ __('Subtitle') }}</th>
                            <th>{{ __('Price') }}</th>
                            <th>{{ __('Expiry in Days') }}</th>
                            <th>{{ __("What's Included") }}</th>
                            <th>{{ __('Benefits') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Display Order') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Add Modal --}}
    <div class="modal fade" id="addPlanModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>{{ __('Add Longevity Plan') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="" method="post" enctype="multipart/form-data" id="addPlanForm" autocomplete="off">
                        @csrf

                        <div class="form-group">
                            <label>{{ __('Plan Image') }}</label>
                            <input id="addImage" type="file" name="image" accept="image/*" class="form-control">
                            <div class="mt-3">
                                <img id="addPreviewImage" src="http://placehold.jp/320x180.png" alt="Preview"
                                    style="width:320px;max-width:100%;height:180px;display:block;border:1px solid #ccc;border-radius:6px;object-fit:cover;">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>{{ __('Title') }}</label>
                            <input type="text" name="title" class="form-control" required
                                placeholder="e.g. Mulk Wellness Hub: Dubai (UAE)">
                        </div>

                        <div class="form-group">
                            <label>{{ __('Price') }}</label>
                            <input type="number" step="0.01" name="price" class="form-control" required
                                placeholder="0.00">
                        </div>

                        <div class="form-group">
                            <label>{{ __('Plan Expiry in Days (Leave blank for no expiry)') }}</label>
                            <input type="number" name="plan_expiry_days" class="form-control"
                                placeholder="e.g. 365">
                        </div>

                        <div class="form-group">
                            <label>{{ __('Subtitle') }}</label>
                            <input type="text" name="subtitle" class="form-control"
                                placeholder="e.g. Desert Longevity Journey">
                        </div>

                        <div class="form-group">
                            <label>{{ __('About This Plan') }}</label>
                            <textarea name="description" class="form-control" rows="4"
                                placeholder="A personalized anti-aging retreat..."></textarea>
                        </div>

                        <div class="form-group">
                            <label>{{ __("What's Included") }}</label>
                            <div id="addIncludedContainer"></div>
                            <button type="button" class="btn btn-outline-primary btn-sm mt-2 add-dynamic-btn"
                                data-target="#addIncludedContainer" data-placeholder="e.g. Wellness Activities">
                                + {{ __('Add Item') }}
                            </button>
                        </div>

                        <div class="form-group">
                            <label>{{ __('Benefits You\'ll Gain') }}</label>
                            <div id="addBenefitsContainer"></div>
                            <button type="button" class="btn btn-outline-primary btn-sm mt-2 add-dynamic-btn"
                                data-target="#addBenefitsContainer" data-placeholder="e.g. Improved energy and vitality">
                                + {{ __('Add Benefit') }}
                            </button>
                        </div>

                        <div class="form-group">
                            <label>{{ __('Status') }}</label>
                            <select name="status" class="form-control" required>
                                <option value="1">{{ __('Active') }}</option>
                                <option value="0">{{ __('Inactive') }}</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>{{ __('Display Order') }}</label>
                            <input type="number" min="0" name="display_order" class="form-control" value="0">
                        </div>

                        <div class="form-group d-flex align-items-center">
                            <input id="addSubmitBtn" class="btn btn-primary mr-2" type="submit" value="{{ __('Submit') }}">
                            <div id="addFormLoader" class="d-none">
                                <div class="spinner-border text-primary" role="status" style="width: 1.2rem; height: 1.2rem;"></div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit Modal --}}
    <div class="modal fade" id="editPlanModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>{{ __('Edit Longevity Plan') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="" method="post" enctype="multipart/form-data" id="editPlanForm" autocomplete="off">
                        @csrf
                        <input type="hidden" name="id" id="editPlanId">

                        <div class="form-group">
                            <label>{{ __('Plan Image') }}</label>
                            <input id="editImage" type="file" name="image" accept="image/*" class="form-control">
                            <div class="mt-3">
                                <img id="editPreviewImage" src="http://placehold.jp/320x180.png" alt="Preview"
                                    style="width:320px;max-width:100%;height:180px;display:block;border:1px solid #ccc;border-radius:6px;object-fit:cover;">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>{{ __('Title') }}</label>
                            <input type="text" id="editTitle" name="title" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>{{ __('Price') }}</label>
                            <input type="number" step="0.01" id="editPrice" name="price" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>{{ __('Plan Expiry in Days (Leave blank for no expiry)') }}</label>
                            <input type="number" id="editPlanExpiryDays" name="plan_expiry_days" class="form-control"
                                placeholder="e.g. 365">
                        </div>

                        <div class="form-group">
                            <label>{{ __('Subtitle') }}</label>
                            <input type="text" id="editSubtitle" name="subtitle" class="form-control">
                        </div>

                        <div class="form-group">
                            <label>{{ __('About This Plan') }}</label>
                            <textarea id="editDescription" name="description" class="form-control" rows="4"></textarea>
                        </div>

                        <div class="form-group">
                            <label>{{ __("What's Included") }}</label>
                            <div id="editIncludedContainer"></div>
                            <button type="button" class="btn btn-outline-primary btn-sm mt-2 add-dynamic-btn"
                                data-target="#editIncludedContainer" data-placeholder="e.g. Wellness Activities">
                                + {{ __('Add Item') }}
                            </button>
                        </div>

                        <div class="form-group">
                            <label>{{ __('Benefits You\'ll Gain') }}</label>
                            <div id="editBenefitsContainer"></div>
                            <button type="button" class="btn btn-outline-primary btn-sm mt-2 add-dynamic-btn"
                                data-target="#editBenefitsContainer" data-placeholder="e.g. Improved energy and vitality">
                                + {{ __('Add Benefit') }}
                            </button>
                        </div>

                        <div class="form-group">
                            <label>{{ __('Status') }}</label>
                            <select id="editStatus" name="status" class="form-control" required>
                                <option value="1">{{ __('Active') }}</option>
                                <option value="0">{{ __('Inactive') }}</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>{{ __('Display Order') }}</label>
                            <input type="number" min="0" id="editDisplayOrder" name="display_order" class="form-control" value="0">
                        </div>

                        <div class="form-group d-flex align-items-center">
                            <input id="editSubmitBtn" class="btn btn-primary mr-2" type="submit" value="{{ __('Submit') }}">
                            <div id="editFormLoader" class="d-none">
                                <div class="spinner-border text-primary" role="status" style="width: 1.2rem; height: 1.2rem;"></div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
