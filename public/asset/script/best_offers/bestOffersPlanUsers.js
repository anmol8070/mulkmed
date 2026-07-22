$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".BestOffersPlanUsersSideA").addClass("activeLi");


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
            url: `${domainUrl}bestOffers/fetchBestOffersPlanUsers`,
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
                    var url = `${domainUrl}bestOffers/deleteBestOffersPlans` + "/" + id;

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

    function getFilteredFormData(formId){
        var formdata = new FormData();
        $(formId).find(':input[name]').each(function () {
            const $input = $(this);

            if ($input.is(':visible') || ($input.attr('id') == "editBannerId")) {
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
                formdata = getFilteredFormData($('#addBannerForm'));
                $.ajax({
                    url: `${domainUrl}bestOffers/addBestOffersPlans`,
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
                    url: `${domainUrl}bestOffers/editBestOffersPlans`,
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
        var id = $(this).attr("rel");
        var title = $(this).data("title");
        var price_description = $(this).data("price_description");
        var price = $(this).data("price");
        var description = $(this).data("description");
        var benefit = $(this).data("benefit");

         const previewImageEdit = document.getElementById('editPreviewImage');
        previewImageEdit.src = $(this).data("image");

        const previewImageEditDetail = document.getElementById('editPreviewDetailImage');
        previewImageEditDetail.src = $(this).data("detail_image");



        $("#editBannerId").val(id);
        $("#editTitle").val(title);
        $("#editPriceDescription").val(price_description);
        $("#editPrice").val(price);
        $("#editDescription").val(description);
        $("#editBenefits").val(benefit);

        

        $("#editBannerModel").modal("show");
    });


        // Reusable preview handler
function attachImagePreview(inputId, previewId, placeholder = "http://placehold.jp/150x150.png") {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);

    input.addEventListener("change", function () {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = e => preview.src = e.target.result;
            reader.readAsDataURL(file);
        } else {
            preview.src = placeholder;
        }
    });
}

// Apply to all your components
attachImagePreview("editImage", "editPreviewImage");
attachImagePreview("editImageDetail", "editPreviewDetailImage");
attachImagePreview("addImage", "addPreviewImage");
attachImagePreview("addImageDetail", "addPreviewDetailImage");

// Reset preview when clicking primary button
document.querySelector(".btn-primary.text-white").onclick = () => {
    document.getElementById("addPreviewImage").src = "http://placehold.jp/150x150.png";
    document.getElementById("addPreviewDetailImage").src = "http://placehold.jp/150x150.png";
};

    
  
});


