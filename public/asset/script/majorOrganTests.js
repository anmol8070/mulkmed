$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".majorOrganTestsSideA").addClass("activeLi");

    const placeholderIcon = "http://placehold.jp/120x120.png";

    function createBiomarkerRow(value = "") {
        return `
            <div class="biomarker-row">
                <input type="text" class="form-control biomarker-input" placeholder="e.g. ALT" value="${$("<div>").text(value).html()}" required>
                <button type="button" class="btn btn-outline-danger btn-sm remove-biomarker-btn">Remove</button>
            </div>
        `;
    }

    function resetBiomarkerContainer(containerSelector, values = [""]) {
        const $container = $(containerSelector);
        $container.empty();
        values.forEach((value) => {
            $container.append(createBiomarkerRow(value));
        });
    }

    function collectBiomarkers(containerSelector) {
        const biomarkers = [];
        $(containerSelector).find(".biomarker-input").each(function () {
            const value = $(this).val().trim();
            if (value) {
                biomarkers.push(value);
            }
        });
        return biomarkers;
    }

    function appendBiomarkersToFormData(formData, containerSelector) {
        const biomarkers = collectBiomarkers(containerSelector);
        biomarkers.forEach((biomarker, index) => {
            formData.append(`biomarkers[${index}]`, biomarker);
        });
        return biomarkers.length > 0;
    }

    function buildOrganTestFormData(formSelector, biomarkerContainerSelector) {
        const formData = new FormData();
        const $form = $(formSelector);

        $form.find(":input[name]").each(function () {
            const $input = $(this);
            const name = $input.attr("name");

            if (!name || name === "biomarkers[]") {
                return;
            }

            if ($input.attr("type") === "file") {
                const files = $input[0].files;
                if (files.length > 0) {
                    formData.append(name, files[0]);
                }
            } else {
                formData.append(name, $input.val());
            }
        });

        formData.append("_token", $('meta[name="csrf-token"]').attr("content"));

        const hasBiomarkers = appendBiomarkersToFormData(formData, biomarkerContainerSelector);
        return { formData, hasBiomarkers };
    }

    function attachImagePreview(inputId, previewId) {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);

        if (!input || !preview) {
            return;
        }

        input.addEventListener("change", function () {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => (preview.src = e.target.result);
                reader.readAsDataURL(file);
            } else {
                preview.src = placeholderIcon;
            }
        });
    }

    function renderFrontendPreview(tests) {
        const $container = $("#frontendPreviewContainer");
        $container.empty();

        if (!tests.length) {
            $container.html('<p class="text-muted mb-0">No active organ tests found.</p>');
            return;
        }

        tests.forEach((test) => {
            const iconHtml = test.icon
                ? `<img src="${test.icon}" class="organ-test-preview-icon" alt="">`
                : `<div class="organ-test-preview-icon"></div>`;

            const biomarkerItems = (test.biomarkers || [])
                .map((item) => `<li>${$("<div>").text(item).html()}</li>`)
                .join("");

            const card = `
                <div class="organ-test-preview-card">
                    <div class="organ-test-preview-header preview-toggle" data-target="preview-body-${test.id}">
                        <div class="organ-test-preview-title">
                            ${iconHtml}
                            <span>${$("<div>").text(test.name).html()}</span>
                        </div>
                        <div class="organ-test-preview-meta">
                            <div class="organ-test-preview-count">${test.biomarker_count} Biomarkers</div>
                        </div>
                    </div>
                    <div class="organ-test-preview-body" id="preview-body-${test.id}">
                        <ul>${biomarkerItems}</ul>
                    </div>
                </div>
            `;

            $container.append(card);
        });
    }

    function loadFrontendPreview() {
        $.getJSON(`${domainUrl}majorOrganTests/preview`)
            .done(function (response) {
                if (response.status) {
                    renderFrontendPreview(response.data || []);
                }
            })
            .fail(function (error) {
                console.log(error);
            });
    }

    $("#organTestsTable").dataTable({
        dom: "Bfrtip",
        buttons: ["copy", "csv", "excel", "pdf", "print"],
        processing: true,
        serverSide: true,
        serverMethod: "POST",
        aaSorting: [[4, "asc"]],
        columnDefs: [
            {
                orderable: false,
                targets: [0, 2, 5],
            },
        ],
        ajax: {
            url: `${domainUrl}majorOrganTests/fetch`,
            error: (error) => {
                console.log(error);
            },
        },
    });

    resetBiomarkerContainer("#addBiomarkersContainer");
    resetBiomarkerContainer("#editBiomarkersContainer");

    attachImagePreview("addIcon", "addPreviewIcon");
    attachImagePreview("editIcon", "editPreviewIcon");

    $('a[data-target="#addOrganTestModal"]').on("click", function () {
        $("#addOrganTestForm")[0].reset();
        $("#addPreviewIcon").attr("src", placeholderIcon);
        resetBiomarkerContainer("#addBiomarkersContainer");
    });

    $(document).on("click", ".add-biomarker-btn", function () {
        const target = $(this).data("target");
        $(target).append(createBiomarkerRow());
    });

    $(document).on("click", ".remove-biomarker-btn", function () {
        const $container = $(this).closest('[id$="BiomarkersContainer"]');
        if ($container.find(".biomarker-row").length <= 1) {
            $(this).closest(".biomarker-row").find(".biomarker-input").val("");
            return;
        }
        $(this).closest(".biomarker-row").remove();
    });

    $(document).on("click", ".toggle-biomarkers", function (event) {
        event.preventDefault();
        const target = $(this).data("target");
        $(`#${target}`).toggleClass("d-none");
    });

    $(document).on("click", ".preview-toggle", function () {
        const target = $(this).data("target");
        $(`#${target}`).toggleClass("show");
    });

    function toggleAddButton(show) {
        const $btn = $('a[data-target="#addOrganTestModal"]');
        if (show) {
            $btn.removeClass("d-none");
        } else {
            $btn.addClass("d-none");
        }
    }

    $('a[data-toggle="tab"]').on("shown.bs.tab", function (e) {
        const target = $(e.target).attr("href");
        toggleAddButton(target === "#SectionManage");
        $("#addPackageBtn").toggleClass("d-none", target !== "#SectionPackage");
    });

    $('a[href="#SectionPreview"]').on("shown.bs.tab", function () {
        loadFrontendPreview();
    });

    let packagesById = {};

    function setPackageTests(testIds) {
        const selected = (testIds || []).map(String);
        $("#packageTestsList input[name='test_ids[]']").each(function () {
            this.checked = selected.includes(this.value);
        });
    }

    function loadPackageTestOptions() {
        return $.getJSON(`${domainUrl}majorOrganTests/preview`).done(function (response) {
            const $list = $("#packageTestsList").empty();
            (response.data || []).forEach(function (test) {
                $list.append(`<div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="packageTest${test.id}" name="test_ids[]" value="${test.id}">
                    <label class="custom-control-label" for="packageTest${test.id}">${escapeHtml(test.name)}</label>
                </div>`);
            });
            if (!response.data || !response.data.length) {
                $list.append('<span class="text-muted">No active organ tests</span>');
            }
        });
    }

    $("#packageTestsSelectAll").on("click", function (event) {
        event.preventDefault();
        $("#packageTestsList input[name='test_ids[]']").prop("checked", true);
    });

    $("#packageTestsClear").on("click", function (event) {
        event.preventDefault();
        $("#packageTestsList input[name='test_ids[]']").prop("checked", false);
    });

    function fillPackageForm(data) {
        $("#packageImage").val("");
        setPackageTests(data ? data.test_ids : []);

        if (!data) {
            $("#packageId").val("");
            $("#packageTitle").val("");
            $("#packageBadge").val("");
            $("#packageDescription").val("");
            $("#packagePrice").val("");
            $("#packageStatus").val("1");
            $("#packagePreviewImage").attr("src", placeholderIcon);
            $("#packageFormTitle").text("Add Package");
            return;
        }

        $("#packageId").val(data.id || "");
        $("#packageTitle").val(data.title || "");
        $("#packageBadge").val(data.badge || "");
        $("#packageDescription").val(data.description || "");
        $("#packagePrice").val(data.price || "");
        $("#packageStatus").val(String(data.status ?? 1));
        $("#packagePreviewImage").attr("src", data.image || placeholderIcon);
        $("#packageFormTitle").text("Edit Package");
    }

    function escapeHtml(value) {
        return $("<div>").text(value ?? "").html();
    }

    function renderPackages(packages) {
        packagesById = {};
        const $tbody = $("#packagesTable tbody").empty();

        if (!packages.length) {
            $tbody.append('<tr><td colspan="7" class="text-center text-muted">No packages yet</td></tr>');
            return;
        }

        packages.forEach(function (item) {
            packagesById[item.id] = item;
            const image = `<img src="${escapeHtml(item.image || placeholderIcon)}" width="50" height="50" style="object-fit:cover;border-radius:6px;">`;
            const status = item.status == 1
                ? '<span class="badge badge-success">Active</span>'
                : '<span class="badge badge-secondary">Inactive</span>';
            const actions = `<a href="#" class="mr-2 btn btn-primary text-white edit-package" rel="${item.id}">Edit</a>`
                + `<a href="#" class="mr-2 btn btn-danger text-white delete-package" rel="${item.id}">Delete</a>`;

            const tests = item.tests || [];
            const testsLabel = item.includes_all_tests
                ? `All active tests (${tests.length})`
                : `${tests.length} test${tests.length === 1 ? "" : "s"}`;
            const testsCol = `<a href="#" class="toggle-biomarkers font-weight-bold" data-target="package-tests-${item.id}">${testsLabel}</a>`
                + `<ul id="package-tests-${item.id}" class="biomarker-list d-none list-unstyled mb-0 mt-2 pl-3">`
                + tests.map((test) => `<li>${escapeHtml(test.name)}</li>`).join("")
                + "</ul>";

            $tbody.append(`<tr>
                <td>${image}</td>
                <td>${escapeHtml(item.title)}</td>
                <td>${escapeHtml(item.badge)}</td>
                <td>${escapeHtml(item.price)}</td>
                <td>${testsCol}</td>
                <td>${status}</td>
                <td>${actions}</td>
            </tr>`);
        });
    }

    function loadPackages() {
        $.getJSON(`${domainUrl}majorOrganTests/package/list`)
            .done(function (response) {
                if (response.status) {
                    renderPackages(response.data || []);
                }
            })
            .fail(function (error) {
                console.log(error);
            });
    }

    $('a[href="#SectionPackage"]').on("shown.bs.tab", function () {
        loadPackageTestOptions();
        loadPackages();
    });

    $("#addPackageBtn").on("click", function (event) {
        event.preventDefault();
        fillPackageForm(null);
        $("#packageModal").modal("show");
    });

    $("#packagesTable").on("click", ".edit-package", function (event) {
        event.preventDefault();
        fillPackageForm(packagesById[$(this).attr("rel")] || null);
        $("#packageModal").modal("show");
    });

    $("#packagesTable").on("click", ".delete-package", function (event) {
        event.preventDefault();
        const id = $(this).attr("rel");

        swal({
            title: strings.doYouReallyWantToContinue,
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((isConfirm) => {
            if (!isConfirm) {
                return;
            }

            if (user_type != "1") {
                iziToast.error({
                    title: strings.error,
                    message: strings.youAreTester,
                    position: "topRight",
                });
                return;
            }

            $.getJSON(`${domainUrl}majorOrganTests/package/delete/${id}`).done(function () {
                if ($("#packageId").val() == id) {
                    fillPackageForm(null);
                }
                loadPackages();
                iziToast.success({
                    title: strings.success,
                    message: strings.operationSuccessful,
                    position: "topRight",
                });
            });
        });
    });

    attachImagePreview("packageImage", "packagePreviewImage");

    $("#packageForm").on("submit", function (event) {
        event.preventDefault();

        if (!this.checkValidity()) {
            this.reportValidity();
            return;
        }

        if (user_type != "1") {
            iziToast.error({
                title: strings.error,
                message: strings.youAreTester,
                position: "topRight",
            });
            return;
        }

        const formData = new FormData(this);
        formData.set("_token", $('meta[name="csrf-token"]').attr("content"));

        const $btn = $("#packageSubmitBtn");
        $btn.prop("disabled", true).val("Please wait...");
        $("#packageFormLoader").removeClass("d-none");

        $.ajax({
            url: `${domainUrl}majorOrganTests/package`,
            type: "POST",
            data: formData,
            dataType: "json",
            contentType: false,
            cache: false,
            processData: false,
            success: function () {
                $("#packageFormLoader").addClass("d-none");
                $btn.prop("disabled", false).val("Save Package");
                $("#packageModal").modal("hide");
                fillPackageForm(null);
                loadPackages();
                iziToast.success({
                    title: strings.success,
                    message: strings.operationSuccessful,
                    position: "topRight",
                });
            },
            error: (error) => {
                $("#packageFormLoader").addClass("d-none");
                $btn.prop("disabled", false).val("Save Package");
                console.log(JSON.stringify(error));
            },
        });
    });

    $("#organTestsTable").on("click", ".delete", function (event) {
        event.preventDefault();

        swal({
            title: strings.doYouReallyWantToContinue,
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((isConfirm) => {
            if (!isConfirm) {
                return;
            }

            if (user_type != "1") {
                iziToast.error({
                    title: strings.error,
                    message: strings.youAreTester,
                    position: "topRight",
                });
                return;
            }

            const id = $(this).attr("rel");
            const url = `${domainUrl}majorOrganTests/delete/${id}`;

            $.getJSON(url).done(function () {
                $("#organTestsTable").DataTable().ajax.reload(null, false);
                iziToast.success({
                    title: strings.success,
                    message: strings.operationSuccessful,
                    position: "topRight",
                });
            });
        });
    });

    $("#addOrganTestForm").on("submit", function (event) {
        event.preventDefault();

        if (!this.checkValidity()) {
            this.reportValidity();
            return;
        }

        if (user_type != "1") {
            iziToast.error({
                title: strings.error,
                message: strings.youAreTester,
                position: "topRight",
            });
            return;
        }

        const { formData, hasBiomarkers } = buildOrganTestFormData("#addOrganTestForm", "#addBiomarkersContainer");

        if (!hasBiomarkers) {
            iziToast.error({
                title: strings.error,
                message: "Please add at least one biomarker.",
                position: "topRight",
            });
            return;
        }

        const $btn = $("#addSubmitBtn");
        $btn.prop("disabled", true).val("Please wait...");
        $("#addFormLoader").removeClass("d-none");

        $.ajax({
            url: `${domainUrl}majorOrganTests/add`,
            type: "POST",
            data: formData,
            dataType: "json",
            contentType: false,
            cache: false,
            processData: false,
            success: function () {
                $("#addFormLoader").addClass("d-none");
                $btn.prop("disabled", false).val("Submit");
                $("#addOrganTestModal").modal("hide");
                $("#addOrganTestForm").trigger("reset");
                $("#addPreviewIcon").attr("src", placeholderIcon);
                resetBiomarkerContainer("#addBiomarkersContainer");
                $("#organTestsTable").DataTable().ajax.reload(null, false);
                iziToast.success({
                    title: strings.success,
                    message: strings.operationSuccessful,
                    position: "topRight",
                });
            },
            error: (error) => {
                $("#addFormLoader").addClass("d-none");
                $btn.prop("disabled", false).val("Submit");
                console.log(JSON.stringify(error));
            },
        });
    });

    $("#editOrganTestForm").on("submit", function (event) {
        event.preventDefault();

        if (!this.checkValidity()) {
            this.reportValidity();
            return;
        }

        if (user_type != "1") {
            iziToast.error({
                title: strings.error,
                message: strings.youAreTester,
                position: "topRight",
            });
            return;
        }

        const { formData, hasBiomarkers } = buildOrganTestFormData("#editOrganTestForm", "#editBiomarkersContainer");

        if (!hasBiomarkers) {
            iziToast.error({
                title: strings.error,
                message: "Please add at least one biomarker.",
                position: "topRight",
            });
            return;
        }

        const $btn = $("#editSubmitBtn");
        $btn.prop("disabled", true).val("Please wait...");
        $("#editFormLoader").removeClass("d-none");

        $.ajax({
            url: `${domainUrl}majorOrganTests/edit`,
            type: "POST",
            data: formData,
            dataType: "json",
            contentType: false,
            cache: false,
            processData: false,
            success: function () {
                $("#editFormLoader").addClass("d-none");
                $btn.prop("disabled", false).val("Submit");
                $("#editOrganTestModal").modal("hide");
                $("#organTestsTable").DataTable().ajax.reload(null, false);
                iziToast.success({
                    title: strings.success,
                    message: strings.operationSuccessful,
                    position: "topRight",
                });
            },
            error: (error) => {
                $("#editFormLoader").addClass("d-none");
                $btn.prop("disabled", false).val("Submit");
                console.log(JSON.stringify(error));
            },
        });
    });

    $("#organTestsTable").on("click", ".edit", function (event) {
        event.preventDefault();

        const id = $(this).attr("rel");
        const biomarkers = $(this).data("biomarkers") || [];

        $("#editOrganTestId").val(id);
        $("#editName").val($(this).data("name"));
        $("#editStatus").val(String($(this).data("status")));
        $("#editDisplayOrder").val($(this).data("display_order"));
        $("#editPreviewIcon").attr("src", $(this).data("icon") || placeholderIcon);

        resetBiomarkerContainer("#editBiomarkersContainer", biomarkers.length ? biomarkers : [""]);
        $("#editOrganTestModal").modal("show");
    });
});
