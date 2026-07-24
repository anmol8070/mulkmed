$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".longevityPlansSideA").addClass("activeLi");

    const placeholderImage = "http://placehold.jp/320x180.png";

    function createDynamicRow(value = "", placeholder = "") {
        return `
            <div class="dynamic-row">
                <input type="text" class="form-control dynamic-input" placeholder="${placeholder}" value="${$("<div>").text(value).html()}">
                <button type="button" class="btn btn-outline-danger btn-sm remove-dynamic-btn">Remove</button>
            </div>
        `;
    }

    function resetDynamicContainer(containerSelector, values = [""], placeholder = "") {
        const $container = $(containerSelector);
        $container.empty();
        values.forEach((value) => {
            $container.append(createDynamicRow(value, placeholder));
        });
    }

    function collectDynamicValues(containerSelector) {
        const values = [];
        $(containerSelector).find(".dynamic-input").each(function () {
            const value = $(this).val().trim();
            if (value) {
                values.push(value);
            }
        });
        return values;
    }

    function buildFormData(formSelector, includedSelector, benefitsSelector) {
        const formData = new FormData();
        const $form = $(formSelector);

        $form.find(":input[name]").each(function () {
            const $input = $(this);
            const name = $input.attr("name");
            if (!name) {
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

        collectDynamicValues(includedSelector).forEach((item, index) => {
            formData.append(`whats_included[${index}]`, item);
        });

        collectDynamicValues(benefitsSelector).forEach((item, index) => {
            formData.append(`benefits[${index}]`, item);
        });

        return formData;
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
                preview.src = placeholderImage;
            }
        });
    }

    $("#longevityPlansTable").dataTable({
        dom: "Bfrtip",
        buttons: ["copy", "csv", "excel", "pdf", "print"],
        processing: true,
        serverSide: true,
        serverMethod: "POST",
        aaSorting: [[8, "asc"]],
        columnDefs: [
            {
                orderable: false,
                targets: [0, 5, 6, 9],
            },
        ],
        ajax: {
            url: `${domainUrl}longevityPlans/fetch`,
            error: (error) => {
                console.log(error);
            },
        },
    });

    resetDynamicContainer("#addIncludedContainer", [""], "e.g. Wellness Activities");
    resetDynamicContainer("#addBenefitsContainer", [""], "e.g. Improved energy and vitality");
    resetDynamicContainer("#editIncludedContainer", [""], "e.g. Wellness Activities");
    resetDynamicContainer("#editBenefitsContainer", [""], "e.g. Improved energy and vitality");

    attachImagePreview("addImage", "addPreviewImage");
    attachImagePreview("editImage", "editPreviewImage");

    $('a[data-target="#addPlanModal"]').on("click", function () {
        $("#addPlanForm")[0].reset();
        $("#addPreviewImage").attr("src", placeholderImage);
        resetDynamicContainer("#addIncludedContainer", [""], "e.g. Wellness Activities");
        resetDynamicContainer("#addBenefitsContainer", [""], "e.g. Improved energy and vitality");
    });

    $(document).on("click", ".add-dynamic-btn", function () {
        const target = $(this).data("target");
        const placeholder = $(this).data("placeholder") || "";
        $(target).append(createDynamicRow("", placeholder));
    });

    $(document).on("click", ".remove-dynamic-btn", function () {
        const $container = $(this).closest('[id$="Container"]');
        if ($container.find(".dynamic-row").length <= 1) {
            $(this).closest(".dynamic-row").find(".dynamic-input").val("");
            return;
        }
        $(this).closest(".dynamic-row").remove();
    });

    $("#longevityPlansTable").on("click", ".delete", function (event) {
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
            $.getJSON(`${domainUrl}longevityPlans/delete/${id}`).done(function () {
                $("#longevityPlansTable").DataTable().ajax.reload(null, false);
                iziToast.success({
                    title: strings.success,
                    message: strings.operationSuccessful,
                    position: "topRight",
                });
            });
        });
    });

    $("#addPlanForm").on("submit", function (event) {
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

        const formData = buildFormData("#addPlanForm", "#addIncludedContainer", "#addBenefitsContainer");
        const $btn = $("#addSubmitBtn");
        $btn.prop("disabled", true).val("Please wait...");
        $("#addFormLoader").removeClass("d-none");

        $.ajax({
            url: `${domainUrl}longevityPlans/add`,
            type: "POST",
            data: formData,
            dataType: "json",
            contentType: false,
            cache: false,
            processData: false,
            success: function () {
                $("#addFormLoader").addClass("d-none");
                $btn.prop("disabled", false).val("Submit");
                $("#addPlanModal").modal("hide");
                $("#addPlanForm").trigger("reset");
                $("#addPreviewImage").attr("src", placeholderImage);
                resetDynamicContainer("#addIncludedContainer", [""], "e.g. Wellness Activities");
                resetDynamicContainer("#addBenefitsContainer", [""], "e.g. Improved energy and vitality");
                $("#longevityPlansTable").DataTable().ajax.reload(null, false);
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

    $("#editPlanForm").on("submit", function (event) {
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

        const formData = buildFormData("#editPlanForm", "#editIncludedContainer", "#editBenefitsContainer");
        const $btn = $("#editSubmitBtn");
        $btn.prop("disabled", true).val("Please wait...");
        $("#editFormLoader").removeClass("d-none");

        $.ajax({
            url: `${domainUrl}longevityPlans/edit`,
            type: "POST",
            data: formData,
            dataType: "json",
            contentType: false,
            cache: false,
            processData: false,
            success: function () {
                $("#editFormLoader").addClass("d-none");
                $btn.prop("disabled", false).val("Submit");
                $("#editPlanModal").modal("hide");
                $("#longevityPlansTable").DataTable().ajax.reload(null, false);
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

    $("#longevityPlansTable").on("click", ".edit", function (event) {
        event.preventDefault();

        const whatsIncluded = $(this).data("whats_included") || [];
        const benefits = $(this).data("benefits") || [];

        $("#editPlanId").val($(this).attr("rel"));
        $("#editTitle").val($(this).data("title"));
        $("#editSubtitle").val($(this).data("subtitle"));
        $("#editPrice").val($(this).data("price"));
        $("#editPlanExpiryDays").val($(this).data("plan_expiry_days"));
        $("#editDescription").val($(this).data("description"));
        $("#editStatus").val(String($(this).data("status")));
        $("#editDisplayOrder").val($(this).data("display_order"));
        $("#editPreviewImage").attr("src", $(this).data("image") || placeholderImage);

        resetDynamicContainer(
            "#editIncludedContainer",
            whatsIncluded.length ? whatsIncluded : [""],
            "e.g. Wellness Activities"
        );
        resetDynamicContainer(
            "#editBenefitsContainer",
            benefits.length ? benefits : [""],
            "e.g. Improved energy and vitality"
        );

        $("#editPlanModal").modal("show");
    });
});
