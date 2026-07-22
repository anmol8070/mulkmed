$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".termsSideA").addClass("activeLi");

    let summernoteOptions = {
        height: 550,
        toolbar: [
            ['style', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
              ['para', ['ul', 'ol', 'paragraph']], // ✅ bullets & numbered list
            ['height', ['height']] ,// optional if you want line height,

              ['view', ['codeview']] 
        ],
         lineHeights: ['0.1', '0.3', '0.8', '1.0', '1.2', '1.5']
    };
    $("#summernote").summernote(summernoteOptions);

    $("#terms").on("submit", function (event) {
        event.preventDefault();
        $(".loader").show();
        if (user_type == "1") {
            let urlParams = new URLSearchParams(window.location.search);
            let selectedLang = urlParams.get("lang") || "en"; // default to 'en' if not found
            var formdata = new FormData($("#terms")[0]);
            $.ajax({
                url: domainUrl + "updateTerms?lang=" + encodeURIComponent(selectedLang),
                type: "POST",
                data: formdata,
                dataType: "json",
                contentType: false,
                cache: false,
                processData: false,
                success: function (response) {
                    // $(".loader").hide();
                    console.log(response);
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
