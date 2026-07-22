$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".helpCenterSideA").addClass("activeLi");

    let summernoteOptions = {
        height: 550,
        toolbar: [
            ['style', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['font', ['fontsize', 'color']], // ✅ font size & color
            ['height', ['height']] // optional if you want line height
        ],
         lineHeights: ['0.1', '0.3', '0.8', '1.0', '1.2', '1.5']
    };
    
    $("#summernote").summernote(summernoteOptions);
    

    $("#helpCenter").on("submit", function (event) {
        event.preventDefault();
        $(".loader").show();
        if (user_type == "1") {
            var formdata = new FormData($("#helpCenter")[0]);
            $.ajax({
                url: domainUrl + "updateHelpCenter",
                type: "POST",
                data: formdata,
                dataType: "json",
                contentType: false,
                cache: false,
                processData: false,
                success: function (response) {
                    // $(".loader").hide();
                    location.reload();
                },
                error: (error) => {
                    $(".loader").hide();
                    console.log(JSON.stringify(error));
                },
            });
        } else {
            $(".loader").hide();
            iziToast.error({
                title: "Error!",
                message: " you are Tester ",
                position: "topRight",
            });
        }
    });
});
