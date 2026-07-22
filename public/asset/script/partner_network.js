
$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".PartnersNetworkSideA").addClass("activeLi");


$('#addBannerModel').on('shown.bs.modal', function () {
    let $summernote = $('#summernote_banner_add');

    if ($summernote.next().hasClass('note-editor')) {
        $summernote.summernote('destroy');
    }

    $summernote.summernote({
        height: 200,
        focus: true
    });

    // Always start with empty content for Add
    $summernote.summernote('code', '');
});




    $("#bannerTable").dataTable({
        dom: "Bfrtip",
        buttons: ["copy", "csv", "excel", "pdf", "print"],
        processing: true,
        serverSide: true,
        serverMethod: "POST",
        aaSorting: [[0, "desc"]],
        columnDefs: [
                {
                    orderable: false,
                },
            ],
        ajax: {
            url: `${domainUrl}fetchPartnerNetwork`,
            data: function (data) {
            },
            error: (error) => {
                console.log(error);
            },
        },
    });

// function populateSpecialityDropdown(dropdownId, items, dropdownName) {
//     const dropdown = $(dropdownId);
//     dropdown.empty().append(`
//         <option value="">Select ${dropdownName}</option>`);

//     items.forEach(item => {
//         dropdown.append(
//             $('<option></option>').val(item.id).text(item.title)
//         );
//     });
// }

// function populateCHPDropdown(dropdownId, items, dropdownName) {
//     const dropdown = $(dropdownId);
//     dropdown.empty().append(`
//         <option value="">Select ${dropdownName}</option>`);

//     items.forEach(item => {
//         dropdown.append(
//             $('<option></option>').val(item.id).text(item.problem)
//         );
//     });
// }
    
    $("#bannerTable").on("click", ".delete", function (event) {
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
                    var url = `${domainUrl}deletePartnerNetwork` + "/" + id;

                    $.getJSON(url).done(function (data) {
                        $("#bannerTable")
                            .DataTable()
                            .ajax.reload(null, false);
                        iziToast.success({
                            title: strings.success,
                            message: strings.operationSuccessful,
                            position: "topRight",
                        });
                    });
                } else {
                    iziToast.error({
                        title: strings.error,
                        message: strings.youAreTester,
                        position: "topRight",
                    });
                }
            }
        });
    });

function getFilteredFormData(formId) {
    var formdata = new FormData();

    // Sync Summernote content into textarea before collecting
    $(formId).find('.summernote-simple').each(function () {
        $(this).val($(this).summernote('code'));
    });

    $(formId).find(':input[name]').each(function () {
        const $input = $(this);

        // Don't skip Summernote textareas just because they are hidden
        if ($input.is(':visible') || $input.hasClass('summernote-simple') || ($input.attr('id') == "editPartnerId")) {
            const name = $input.attr('name');

            if ($input.attr('type') === 'file') {
                const files = $input[0].files;
                for (let i = 0; i < files.length; i++) {
                    formdata.append(name, files[i]);
                }
            } else {
                formdata.append(name, $input.val());
            }
        }
    });

    formdata.append('_token', $('meta[name="csrf-token"]').attr('content'));
    return formdata;
}

    $("#addBannerForm").on("submit", function (event) {
        event.preventDefault();

         $(this).find("select").each(function () {
            const $parent = $(this).closest(".form-group, .dropdownBannerSubselect");

            if (!$parent.hasClass("d-none")) {
                
                $(this).prop("required", true);
            } else {
                $(this).prop("required", false);
            }
        });
        
        if (this.checkValidity()) {

            $(".loader").show();
            if (user_type == "1") {
             formdata = getFilteredFormData("#addBannerForm");

                $.ajax({
                    url: `${domainUrl}addPartnerNetwork`,
                    type: "POST",
                    data: formdata,
                    dataType: "json",
                    contentType: false,
                    cache: false,
                    processData: false,
                    success: function (response) {
                        $(".loader").hide();
                        $("#addBannerModal").modal("hide");
                        $("#addBannerForm").trigger("reset");
                        $("#bannerTable").DataTable().ajax.reload(null, false);
                        iziToast.success({
                            title: strings.success,
                            message: strings.operationSuccessful,
                            position: "topRight",
                        });
                    },
                    error: (error) => {
                        $(".loader").hide();
                        console.log(JSON.stringify(error));
                    },
                });
                
            } else {
                $(".loader").hide();
                iziToast.error({
                    title: strings.error,
                    message: strings.youAreTester,
                    position: "topRight",
                });
            }
        }
        else {
            
            this.reportValidity();
        }
    });
    $("#editBannerForm").on("submit", function (event) {
        event.preventDefault();

        $(this).find("select").each(function () {
            const $parent = $(this).closest(".form-group, .dropdownBannerSubselect");

            if (!$parent.hasClass("d-none")) {
                $(this).prop("required", true);
            } else {
                $(this).prop("required", false);
            }
        });

         if (this.checkValidity()) {
            $(".loader").show();

            if (user_type == "1") {
                formdata = getFilteredFormData($('#editBannerForm'));
                $.ajax({
                    url: `${domainUrl}editPartnerNetwork`,
                    type: "POST",
                    data: formdata,
                    dataType: "json",
                    contentType: false,
                    cache: false,
                    processData: false,
                    success: function (response) {
                        $(".loader").hide();
                        $("#editBannerModel").modal("hide");
                        $("#editBannerForm").trigger("reset");
                        $("#bannerTable").DataTable().ajax.reload(null, false);
                        iziToast.success({
                            title: strings.success,
                            message: strings.operationSuccessful,
                            position: "topRight",
                        });
                    },
                    error: (error) => {
                        $(".loader").hide();
                        console.log(JSON.stringify(error));
                    },
                });
            } else {
                $(".loader").hide();
                iziToast.error({
                    title: strings.error,
                    message: strings.youAreTester,
                    position: "topRight",
                });
            }
        }
        else {
            
            this.reportValidity();
        }
    });

  $("#bannerTable").on("click", ".edit", function (event) {
    event.preventDefault();

    var title = $(this).data("title");
    var headline = $(this).data("headline");
    var hospital_name = $(this).data("hospital_name");
    var address = $(this).data("address");
    var website_link = $(this).data("website_link");
    var data = $(this).data("data");
    var id = $(this).attr("rel");

    
    const previewImageEdit = document.getElementById('editpreviewImage');
    previewImageEdit.src = $(this).data("icon");

    $("#editTitle").val(title);
    $("#editHeadline").val(headline);
    $("#editHospitalName").val(hospital_name);
    $("#editAddress").val(address);
    $("#editWebsiteLink").val(website_link);

    // Store content temporarily
    $("#editBannerModel").data("summernoteContent", data);

    $("#editPartnerId").val(id);

    // Show modal
    $("#editBannerModel").modal("show");
});

// Initialize Summernote after modal is visible
$('#editBannerModel').on('shown.bs.modal', function () {
    let $summernote = $('#summernote_banner_edit');

    // Destroy old instance if it exists
    if ($summernote.next().hasClass('note-editor')) {
        $summernote.summernote('destroy');
    }

    // Init Summernote with minimal toolbar
    $summernote.summernote({
        height: 200,
        focus: true,
        toolbar: [
            ['style', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
            ['para', ['paragraph']]
        ]
    });

    // Set content from stored data
    var content = $(this).data("summernoteContent") || '';
    $summernote.summernote('code', content);
});


    const fileInputEdit = document.getElementById('editImage');
    const previewImageEdit = document.getElementById('editpreviewImage');


    fileInputEdit.addEventListener('change', function () {
        const file = this.files[0];
        if (file) {
        const reader = new FileReader();
        reader.onload = function (e) {
            previewImageEdit.src = e.target.result; // update preview with chosen file
        };
        reader.readAsDataURL(file);
        } else {
        // if user cancels selection, keep existing preview
            previewImageEdit.src = "http://placehold.jp/150x150.png";
        }
    });


    const fileInputAdd = document.getElementById('addImage');
    const previewImageAdd = document.getElementById('addPreviewImage');


    fileInputAdd.addEventListener('change', function () {
        const file = this.files[0];
        if (file) {
        const reader = new FileReader();
        reader.onload = function (e) {
            previewImageAdd.src = e.target.result; // update preview with chosen file
        };
        reader.readAsDataURL(file);
        } else {
        // if user cancels selection, keep existing preview
            previewImageAdd.src = "http://placehold.jp/150x150.png";
        }
    });

    document.querySelector(".btn-primary.text-white").onclick = function (event) {

        const previewImageAdd = document.getElementById('addPreviewImage');
            previewImageAdd.src = "http://placehold.jp/150x150.png";
    };


  
});


