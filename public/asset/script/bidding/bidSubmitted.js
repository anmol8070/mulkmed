$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".SubmittedBidSideA").addClass("activeLi");

    // // Fetch Sound Categories
    // var url = `${domainUrl}getFaqCats`;
    // var faqCategories;
    // $.getJSON(url).done(function (data) {
    //     faqCategories = data.data;
    // });

    $("#THP").dataTable({
        dom: "Bfrtip",
        buttons: ["copy", "csv", "excel", "pdf", "print"],
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1],
                orderable: false,
            },
            
        ],
        ajax: {
            url: `${domainUrl}bidding/fetchBidData`,
            data: function (data) {},
            error: (error) => {
                console.log(error);
            },
        },
    });
    
    $("#THP").on("click", ".delete", function (event) {
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
                    var url = `${domainUrl}smo/deleteSubmitYourQuery` + "/" + id;

                    $.getJSON(url).done(function (data) {
                        console.log(data);
                        $("#THP")
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

    $("#addCatForm").on("submit", function (event) {
        event.preventDefault();
        $(".loader").show();
        if (user_type == "1") {
            var formdata = new FormData($("#addCatForm")[0]);
            $.ajax({
                url: `${domainUrl}smo/addSubmitYourQuery`,
                type: "POST",
                data: formdata,
                dataType: "json",
                contentType: false,
                cache: false,
                processData: false,
                success: function (response) {
                    $(".loader").hide();
                    $("#addCatModal").modal("hide");
                    $("#addCatForm").trigger("reset");
                    $("#THP").DataTable().ajax.reload(null, false);
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
    });
    $("#editCatForm").on("submit", function (event) {
        event.preventDefault();
        $(".loader").show();
        if (user_type == "1") {
            var formdata = new FormData($("#editCatForm")[0]);
            $.ajax({
                url: `${domainUrl}smo/editSubmitYourQuery`,
                type: "POST",
                data: formdata,
                dataType: "json",
                contentType: false,
                cache: false,
                processData: false,
                success: function (response) {
                    $(".loader").hide();
                    $("#editCatModal").modal("hide");
                    $("#editCatForm").trigger("reset");
                    $("#THP").DataTable().ajax.reload(null, false);
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
    });

    $("#THP").on("click", ".edit", function (event) {
        event.preventDefault();

        var id = $(this).attr("rel");

        $("#editCatId").val(id);

        var service = $(this).data("service");
        var budget = $(this).data("budget");
        var city = $(this).data("city");
        var country = $(this).data("country");
        var date = $(this).data("date");
        var comments = $(this).data("comments");
        var other_service = $(this).data("other_service");
  
        var id = $(this).attr("rel");

        $("#editFaqId").val(id);
        $("#service").val(service);
        $("#budget").val(budget);
        $("#city").val(city);
        $("#country").val(country);
        $("#date").val(date);
        $("#comments").val(comments);

        if(other_service != ''){

            $("#other_service").val(other_service);
             $("#otherServiceEdit").css("display", "block");
        }
        
        else{
            $("#otherServiceEdit").css("display", "none");
        }
        

          var docs = $(this).data("docs");

    // Clear old docs
    $("#medical_docs").empty();

    if (docs && docs !== "") {
        // Split docs into array
        var docArray = docs.split(",");

        // Loop and show links
        docArray.forEach(function(doc, index) {
            // Add base path if needed
            let docUrl = doc.startsWith('http')
    ? doc
    : `${domainUrl}storage/${doc.trim()}`;

            $("#medical_docs").append(`
                <a href="${docUrl}" target="_blank" class="btn btn-sm btn-outline-primary mb-1 mr-2">
                    View Doc ${index + 1}
                </a><br>
            `);
        });
    } else { 
        $("#medical_docs").html('<p class="text-muted mb-0">No documents available</p>');
    }

        $("#editCatModal").modal("show");
    });

    $("#faqTable").on("click", ".edit", function (event) {
        event.preventDefault();

        

        $("#editFaqCategory").empty();

        $.each(faqCategories, function (indexInArray, category) {
            $("#editFaqCategory").append(`
                    <option ${
                        category.id == catId ? "selected" : ""
                    } value="${category.id}">${category.title}</option>
                `);
        });

        $("#editFaqModal").modal("show");
    });

    const fileInputEdit = document.getElementById('editImage');
        const previewImageEdit = document.getElementById('previewImage');
        // previewImageEdit.src = $(this).data("icon");

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

         const fileInput = document.getElementById('addImage');
        const previewImage = document.getElementById('addPreviewImage');
        console.log("dd");
     previewImage.src = "http://placehold.jp/150x150.png";

        fileInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
            const reader = new FileReader();
            reader.onload = function (e) {
                previewImage.src = e.target.result; // update preview with chosen file
            };
            reader.readAsDataURL(file);
            } else {
            // if user cancels selection, keep existing preview
            previewImage.src = "http://placehold.jp/150x150.png";
            }
        });

         document.querySelector(".btn-primary.text-white").onclick = function (event) {
  
            const previewImageAdd = document.getElementById('addPreviewImage');
             previewImageAdd.src = "http://placehold.jp/150x150.png";
        };
});
