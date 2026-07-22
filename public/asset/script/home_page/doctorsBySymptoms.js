$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".doctorsBySymptomsSideA").addClass("activeLi");

    function lockSubmit($form) {
        var $btn = $form.find('input[type="submit"], button[type="submit"]');
        if ($btn.data("busy")) {
            return false;
        }
        $btn.data("busy", true).prop("disabled", true);
        $btn.data("original-val", $btn.val());
        $btn.val("Please wait...");
        return true;
    }

    function unlockSubmit($form) {
        var $btn = $form.find('input[type="submit"], button[type="submit"]');
        $btn.data("busy", false).prop("disabled", false);
        if ($btn.data("original-val")) {
            $btn.val($btn.data("original-val"));
        }
    }

    function showErrorToast(message) {
        iziToast.error({
            title: strings.error,
            message: message || "Something went wrong. Please try again.",
            position: "topRight",
        });
    }

    function isValidImageFile(file) {
        if (!file) {
            return false;
        }
        var allowedTypes = [
            "image/jpeg",
            "image/jpg",
            "image/png",
            "image/gif",
            "image/webp",
            "image/bmp",
        ];
        if (allowedTypes.indexOf(file.type) !== -1) {
            return true;
        }
        // fallback for browsers that omit mime type
        return /\.(jpe?g|png|gif|webp|bmp)$/i.test(file.name || "");
    }

    function validateImageInput($input, previewEl, required) {
        var file = $input[0] && $input[0].files ? $input[0].files[0] : null;
        if (!file) {
            if (required) {
                showErrorToast("Please select an image file.");
                return false;
            }
            return true;
        }
        if (!isValidImageFile(file)) {
            $input.val("");
            if (previewEl) {
                previewEl.src = "http://placehold.jp/150x150.png";
            }
            showErrorToast("Only image files are allowed (JPG, PNG, GIF, WEBP).");
            return false;
        }
        return true;
    }

    $("#DiseaseTable").dataTable({
        dom: "Bfrtip",
        buttons: ["copy", "csv", "excel", "pdf", "print"],
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1, 2, 3, 4, 5],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchDoctorsBySymptoms`,
            data: function (data) {},
            error: (error) => {
                console.log(error);
            },
        },
    });

    $("#DiseaseTable").on("xhr.dt", function (e, settings, json) {
        populateSpecialityDropdown("#editSpeciality", json.specialities);
        populateSpecialityDropdown("#addSpeciality", json.specialities);
    });

    function populateSpecialityDropdown(dropdownId, items) {
        const dropdown = $(dropdownId);
        dropdown.empty().append('<option value="">Select Speciality</option>');

        if (!items || !items.length) {
            return;
        }

        items.forEach((item) => {
            dropdown.append(
                $("<option></option>").val(item.id).text(item.title)
            );
        });
    }

    $("#DiseaseTable").on("click", ".delete", function (event) {
        event.preventDefault();
        swal({
            title: strings.doYouReallyWantToContinue,
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((isConfirm) => {
            if (isConfirm) {
                if (user_type == "1") {
                    var id = $(this).attr("rel");
                    var url = `${domainUrl}deleteDoctorsBySymptoms` + "/" + id;

                    $.getJSON(url).done(function (data) {
                        $("#DiseaseTable")
                            .DataTable()
                            .ajax.reload(null, false);
                        iziToast.success({
                            title: strings.success,
                            message: strings.operationSuccessful,
                            position: "topRight",
                        });
                    });
                } else {
                    showErrorToast(strings.youAreTester);
                }
            }
        });
    });

    $("#addDiseaseForm").on("submit", function (event) {
        event.preventDefault();
        var $form = $(this);
        if (
            !validateImageInput(
                $("#addImage"),
                document.getElementById("addPreviewImage"),
                true
            )
        ) {
            return;
        }
        if (!lockSubmit($form)) {
            return;
        }
        $(".loader").show();
        if (user_type == "1") {
            var formdata = new FormData($("#addDiseaseForm")[0]);
            $.ajax({
                url: `${domainUrl}addDoctorsBySymptoms`,
                type: "POST",
                data: formdata,
                dataType: "json",
                contentType: false,
                cache: false,
                processData: false,
                success: function (response) {
                    $(".loader").hide();
                    unlockSubmit($form);
                    $("#addDiseaseModel").modal("hide");
                    $("#addDiseaseForm").trigger("reset");
                    $("#DiseaseTable").DataTable().ajax.reload(null, false);
                    iziToast.success({
                        title: strings.success,
                        message: strings.operationSuccessful,
                        position: "topRight",
                    });
                },
                error: (error) => {
                    $(".loader").hide();
                    unlockSubmit($form);
                    console.log(JSON.stringify(error));
                    var msg =
                        error.responseJSON &&
                        (error.responseJSON.message ||
                            (error.responseJSON.errors &&
                                error.responseJSON.errors.image &&
                                error.responseJSON.errors.image[0]));
                    showErrorToast(msg);
                },
            });
        } else {
            $(".loader").hide();
            unlockSubmit($form);
            showErrorToast(strings.youAreTester);
        }
    });

    $("#editDiseaseForm").on("submit", function (event) {
        event.preventDefault();
        var $form = $(this);
        if (
            !validateImageInput(
                $("#editImage"),
                document.getElementById("editpreviewImage"),
                false
            )
        ) {
            return;
        }
        if (!lockSubmit($form)) {
            return;
        }
        $(".loader").show();
        if (user_type == "1") {
            var formdata = new FormData($("#editDiseaseForm")[0]);
            $.ajax({
                url: `${domainUrl}editDoctorsBySymptoms`,
                type: "POST",
                data: formdata,
                dataType: "json",
                contentType: false,
                cache: false,
                processData: false,
                success: function (response) {
                    $(".loader").hide();
                    unlockSubmit($form);
                    $("#editDiseaseModel").modal("hide");
                    $("#editDiseaseForm").trigger("reset");
                    $("#DiseaseTable").DataTable().ajax.reload(null, false);
                    iziToast.success({
                        title: strings.success,
                        message: strings.operationSuccessful,
                        position: "topRight",
                    });
                },
                error: (error) => {
                    $(".loader").hide();
                    unlockSubmit($form);
                    console.log(JSON.stringify(error));
                    var msg =
                        error.responseJSON &&
                        (error.responseJSON.message ||
                            (error.responseJSON.errors &&
                                error.responseJSON.errors.image &&
                                error.responseJSON.errors.image[0]));
                    showErrorToast(msg);
                },
            });
        } else {
            $(".loader").hide();
            unlockSubmit($form);
            showErrorToast(strings.youAreTester);
        }
    });

    $("#DiseaseTable").on("click", ".edit", function (event) {
        event.preventDefault();
        var problem = $(this).data("problem");
        var speciality = $(this).data("speciality");
        var priority = $(this).data("priority");
        var info = $(this).data("info");
        var id = $(this).attr("rel");

        const previewImageEdit = document.getElementById("editpreviewImage");
        if (previewImageEdit) {
            previewImageEdit.src = $(this).data("icon");
        }

        $("#editDiseaseId").val(id);
        $("#editProblem").val(problem);
        $("#editSpeciality").val(speciality).trigger("change");
        $("#editPriority").val(priority);
        $("#editInfo").val(info);

        $("#editDiseaseModel").modal("show");
    });

    const fileInputEdit = document.getElementById("editImage");
    const previewImageEdit = document.getElementById("editpreviewImage");

    if (fileInputEdit && previewImageEdit) {
        fileInputEdit.addEventListener("change", function () {
            const file = this.files[0];
            if (file) {
                if (!isValidImageFile(file)) {
                    this.value = "";
                    previewImageEdit.src = "http://placehold.jp/150x150.png";
                    showErrorToast(
                        "Only image files are allowed (JPG, PNG, GIF, WEBP)."
                    );
                    return;
                }
                const reader = new FileReader();
                reader.onload = function (e) {
                    previewImageEdit.src = e.target.result;
                };
                reader.readAsDataURL(file);
            } else {
                previewImageEdit.src = "http://placehold.jp/150x150.png";
            }
        });
    }

    const fileInputAdd = document.getElementById("addImage");
    const previewImageAdd = document.getElementById("addPreviewImage");

    if (fileInputAdd && previewImageAdd) {
        fileInputAdd.addEventListener("change", function () {
            const file = this.files[0];
            if (file) {
                if (!isValidImageFile(file)) {
                    this.value = "";
                    previewImageAdd.src = "http://placehold.jp/150x150.png";
                    showErrorToast(
                        "Only image files are allowed (JPG, PNG, GIF, WEBP)."
                    );
                    return;
                }
                const reader = new FileReader();
                reader.onload = function (e) {
                    previewImageAdd.src = e.target.result;
                };
                reader.readAsDataURL(file);
            } else {
                previewImageAdd.src = "http://placehold.jp/150x150.png";
            }
        });
    }

    $('[data-target="#addDiseaseModel"]').on("click", function () {
        const preview = document.getElementById("addPreviewImage");
        if (preview) {
            preview.src = "http://placehold.jp/150x150.png";
        }
    });
});
