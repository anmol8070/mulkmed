@extends('include.app')
@section('header')
    <script>
        window.existingRolesMap = @json($allRoles->pluck('id', 'name'));
    </script>
    <script src="{{ asset('asset/script/adminRoleForm.js') }}?v=4"></script>
@endsection

@section('content')
    <style>
        .permission-accordion {
            border: 1px solid #e4e6fc;
            border-radius: 10px;
            margin-bottom: 12px;
            overflow: hidden;
            background: #fff;
        }

        .permission-accordion-header {
            background: #f8f9fe;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            user-select: none;
            border-bottom: 1px solid transparent;
        }

        .permission-accordion.open .permission-accordion-header {
            border-bottom-color: #e4e6fc;
        }

        .permission-accordion-header h6 {
            margin: 0;
            font-weight: 600;
            flex: 1;
        }

        .permission-accordion-header .chevron {
            color: #6777ef;
            transition: transform 0.2s ease;
            width: 16px;
            text-align: center;
        }

        .permission-accordion.open .permission-accordion-header .chevron {
            transform: rotate(90deg);
        }

        .permission-accordion-body {
            display: none;
            background: #fff;
        }

        .permission-accordion.open .permission-accordion-body {
            display: block;
        }

        .permission-child-row {
            padding: 10px 16px 10px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #f1f2f6;
        }

        .permission-child-row:last-child {
            border-bottom: none;
        }

        .permission-child-row span {
            font-size: 14px;
            color: #34395e;
        }

        .permission-toolbar {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
        }
    </style>

    <div class="card mt-3">
        <div class="card-header d-flex align-items-center">
            <h4 class="mb-0" id="rolePageTitle">{{ $role ? __('Edit Admin Role') : __('Add Admin Role') }}</h4>
            <a href="{{ route('adminManagement') }}" class="ml-auto btn btn-light">{{ __('Back') }}</a>
        </div>
        <div class="card-body">
            <form id="adminRoleForm" autocomplete="off">
                @csrf
                @if ($role)
                    <input type="hidden" name="id" id="roleId" value="{{ $role->id }}">
                    <input type="hidden" name="name" value="{{ $role->name }}">
                @endif

                <div class="form-group col-md-6 pl-0">
                    <label>{{ __('Role') }}</label>
                    @if ($role)
                        <select id="roleSelector" class="form-control">
                            <option value="new">{{ __('+ Create New Role') }}</option>
                            @foreach ($allRoles as $existingRole)
                                <option value="{{ $existingRole->id }}" {{ (int) $role->id === (int) $existingRole->id ? 'selected' : '' }}>
                                    {{ $existingRole->name }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">{{ __('Select a role to view and update its sidebar permissions.') }}</small>
                    @else
                        <input type="text" name="name" id="roleName" class="form-control"
                            placeholder="{{ __('Enter new role name') }}" list="existingRoleNames" required>
                        <datalist id="existingRoleNames">
                            @foreach ($allRoles as $existingRole)
                                <option value="{{ $existingRole->name }}"></option>
                            @endforeach
                        </datalist>
                        <small class="text-muted">
                            {{ __('Enter a new role name, or') }}
                            <a href="{{ route('adminRole.edit', $allRoles->first()->id ?? 1) }}">{{ __('select an existing role to edit') }}</a>
                        </small>
                    @endif
                </div>

                <div id="permissionsSection">
                @if (!$role || (int) $role->id !== 1)
                    <div class="form-group">
                        <label>{{ __('Sidebar Access') }}</label>
                        <p class="text-muted small mb-2">{{ __('Click each menu dropdown to expand and enable sub-options for this role.') }}</p>

                        <div class="permission-toolbar">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="expandAllGroups">{{ __('Expand All') }}</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="collapseAllGroups">{{ __('Collapse All') }}</button>
                        </div>

                        <div id="permissionAccordion">
                            @foreach ($permissionTree as $groupKey => $group)
                                @php
                                    $groupEnabled = \App\Helpers\Helpers::isPermissionGranted($groupKey, $selectedPermissions);
                                    $hasChildEnabled = collect($group['children'])->contains(
                                        fn ($label, $childKey) => \App\Helpers\Helpers::isPermissionGranted($childKey, $selectedPermissions)
                                    );
                                    $shouldOpen = $groupEnabled || $hasChildEnabled;
                                @endphp
                                <div class="permission-accordion {{ $shouldOpen ? 'open' : '' }}" data-group="{{ $groupKey }}">
                                    <div class="permission-accordion-header">
                                        <i class="fas fa-chevron-right chevron"></i>
                                        <h6>{{ __($group['label']) }}</h6>
                                        <label class="switch mb-0" onclick="event.stopPropagation();">
                                            <input type="checkbox" class="group-toggle" name="module_access[]"
                                                value="{{ $groupKey }}" {{ $groupEnabled ? 'checked' : '' }}>
                                            <span class="slider round"></span>
                                        </label>
                                    </div>
                                    <div class="permission-accordion-body">
                                        @foreach ($group['children'] as $childKey => $childLabel)
                                            <div class="permission-child-row">
                                                <span>{{ __($childLabel) }}</span>
                                                <label class="switch mb-0">
                                                    <input type="checkbox" class="child-toggle" data-group="{{ $groupKey }}"
                                                        name="module_access[]" value="{{ $childKey }}"
                                                        {{ \App\Helpers\Helpers::isPermissionGranted($childKey, $selectedPermissions) ? 'checked' : '' }}>
                                                    <span class="slider round"></span>
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="alert alert-info">{{ __('Main admin role has access to all sidebar sections.') }}</div>
                @endif
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">{{ __('Save Role') }}</button>
                    <a href="{{ route('adminManagement') }}" class="btn btn-light ml-2">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
@endsection
