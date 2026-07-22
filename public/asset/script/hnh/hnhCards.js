$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".HNHCardsSideA").addClass("activeLi");

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
                targets: [0, 1, 2],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}HNH/fetchHnHCards`,
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
                    var url = `${domainUrl}smo/deleteTopHospitals` + "/" + id;

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
                url: `${domainUrl}smo/addTopHospitals`,
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
                url: `${domainUrl}smo/editTopHospitals`,
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
        var card_number = $(this).data("card_number");
        var user_name = $(this).data("user_name");

        var phone_number = $(this).data("phone_number");
        var email = $(this).data("email");
        var date_of_birth = $(this).data("date_of_birth");
        var gender = $(this).data("gender");
        var address = $(this).data("address");
        var points = $(this).data("points");
        var emirates_id = $(this).data("emirates_id");
        var payment_status = $(this).data("payment_status");
        var payment_amount = $(this).data("payment_amount");


        $("#editCatId").val(id);

        $("#cardNumberView").val(card_number);
        $("#usernameView").val(user_name);
        $("#phoneNumberView").val(phone_number);
        $("#emailView").val(email);
        $("#dateOfBirthView").val(date_of_birth);
        $("#genderView").val(gender === 0 ? 'Male' : gender === 1 ? 'Female' : 'Not Specified');
        $("#addressView").val(address);
        $("#pointsView").val(points);
        $("#emiratesidView").val(emirates_id);
        $("#paymentStatusView").val(payment_status == 0 ? 'Pending' :(payment_status == 1 ? "Paid" : "Failed"));
        $("#paymentAmountView").val(payment_amount);

       

        $("#editCatModal").modal("show");
    });

    $("#faqTable").on("click", ".edit", function (event) {
        event.preventDefault();

        var question = $(this).data("question");
        var answer = $(this).data("answer");
        var catId = $(this).data("cat");
        var id = $(this).attr("rel");

        $("#editFaqId").val(id);
        $("#editFaqQuestion").val(question);
        $("#editFaqAnswer").val(answer);

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


    fetch(`${domainUrl}smo/getHospitals`, {
        method: "GET",
    })
    .then(res => res.json())
    .then(data => {
        const hospitals = data.hospitals; //  assuming response = { hospitals: [...] }

        const editHospitalName = $('#editHospitalName');
        const addHospitalName = $('#addHospitalName');

        // Clear old options
        editHospitalName.empty();
        addHospitalName.empty();

        // Add default options
        editHospitalName.append('<option value="">Select Hospital</option>');
        addHospitalName.append('<option value="">Select Hospital</option>');

        // Loop and append options to both
        hospitals.forEach(hospital => {
            const option = `<option value="${hospital.id}">${hospital.name}</option>`;
            editHospitalName.append(option);
            addHospitalName.append(option);
        });
    })
    .catch(err => console.error('Error fetching hospitals:', err));

    $('#editHospitalName').on('change', function () {
        
    const selectedText = $(this).find('option:selected').text(); //  Get the visible text
    $('#selectedHospitalTextEdit').val(selectedText); //  Set it to hidden input
});

 $('#addHospitalName').on('change', function () {
    const selectedText = $(this).find('option:selected').text(); //  Get the visible text
    $('#selectedHospitalTextAdd').val(selectedText); //  Set it to hidden input
});


});
