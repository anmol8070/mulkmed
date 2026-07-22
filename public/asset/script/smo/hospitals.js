$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".HospitalsSideA").addClass("activeLi");

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
            {
              targets: [6], // 7th column (0-based index)
              visible: false, // hides it
            },
        ],
        ajax: {
            url: `${domainUrl}smo/fetchHospitals`,
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
                    var url = `${domainUrl}smo/deleteHospitals` + "/" + id;

                    $.getJSON(url).done(function (data) {
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
                url: `${domainUrl}smo/addHospitals`,
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
                url: `${domainUrl}smo/editHospitals`,
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

        const id = $(this).attr("rel");
        const name = $(this).data("name");
        const rating = $(this).data("rating");
        const rating_count = $(this).data("rating_count");
        const country = $(this).data("country");
        const address = $(this).data("address");

         // Example usage when editing
        const latitude = $(this).data("latitude");
        const longitude = $(this).data("longitude");
        
        const website = $(this).data("website");

        const contact = $(this).data("contact_number");

        let categories = $(this).data("category");

        let procedure_ids = $(this).data("procedure_ids");

        const clinic_timing = $(this).data("clinic_timing");

        const services_offered = $(this).data("services_offered");

        const exclusive_mulkmed_benefits = $(this).data("exclusive_mulkmed_benefits");

        // prefill Edit input with address
        setAddressFromLatLng(latitude, longitude, "locationInputEdit");

        $("#editCatId").val(id);
        $("#editCatName").val(name);
        $("#editCatRating").val(rating);
        $("#rating_count_edit").val(rating_count);
        $("#editCatCountry").val(country);
        
       const chipHandler = initChipInput("chip-container-services-edit", "chip-input-services-edit", "services-offered-hidden-edit");

       chipHandler.clearChips();
       
       services_offered.split(",").forEach(service => {
        chipHandler.addChip(service.trim());
      });

       const chipHandlerbenefits = initChipInput("chip-container-benefits-edit", "chip-input-benefits-edit", "exclusive-mulkmed-hidden-edit");

       chipHandlerbenefits.clearChips();

       exclusive_mulkmed_benefits.split(",").forEach(benefit => {
        chipHandlerbenefits.addChip(benefit.trim());
      });


        $("#editCatAddress").val(address);
        
        $("#editContact").val(contact);

        // also set hidden fields directly
        $("#lat_edit").val(latitude);
        $("#lng_edit").val(longitude);

        $("#editWebsite").val(website);

        if (typeof categories === "string") {
            categories = JSON.parse(categories);
        }

          if (typeof procedure_ids === "string") {
            procedure_ids = JSON.parse(procedure_ids);
        }

        $('#clinicTimingEdit').val(clinic_timing);
    
        // set preselected values in select2
         // ensure select2 is initialized
        let $select = $('#categorySelectEdit');

        let $selectProdcedures = $('#procedureSelectEdit');

        const $wrap = $("#editImagesWrapper");
        $wrap.empty();

        let images = $(this).data("images");            // could be array or string
        if (typeof images === "string") {
        try { images = JSON.parse(images || "[]"); } catch (e) { images = []; }
        }
        if (!Array.isArray(images)) images = [];

        // existing images
        if (images.length) {
        images.forEach(img => $wrap.append(makeExistingCard(img)));
        } else {
        // no images -> one empty card
        $wrap.append(makeNewCard());
        }

        // “Add image” button inside modal
        $("#editAddMore").off("click").on("click", function () {
        $wrap.append(makeNewCard());
        });
    

    // use small timeout to ensure options are loaded
    setTimeout(() => {
        if (typeof categories === "string") {
            try {
                categories = JSON.parse(categories);
            } catch(e) {
                console.error("Invalid JSON:", categories);
            }
        }
         if (typeof procedure_ids === "string") {
            try {
                procedure_ids = JSON.parse(procedure_ids);
            } catch(e) {
                console.error("Invalid JSON:", procedure_ids);
            }
        }
        $select.val(categories).trigger("change");

        $selectProdcedures.val(procedure_ids).trigger("change");
    
    }, 300);

        const previewImageEdit = document.getElementById('editPreviewImage');
        previewImageEdit.src = $(this).data("icon");


        $("#editCatModal").modal("show");

    });

    $("#faqTable").on("click", ".edit", function (event) {
        event.preventDefault();

        var question = $(this).data("question");
        var answer = $(this).data("answer");
        var catId = $(this).data("cat");
        var id = $(this).data("rel");

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

    function setAddressFromLatLng(lat, lng, inputId) {
     
    var geocoder = new google.maps.Geocoder();
    var latlng = { lat: parseFloat(lat), lng: parseFloat(lng) };
 
        geocoder.geocode({ location: latlng }, function(results, status) {
       
            if (status === "OK") {
                if (results[0]) {
               
                    document.getElementById(inputId).value = results[0].formatted_address;
                } else {
                    console.warn("No results found for lat/lng");
                }
            } else {
                console.error("Geocoder failed due to: " + status);
            }
        });
    }

     const fileInputEdit = document.getElementById('editImage');
        const previewImageEdit = document.getElementById('editPreviewImage');
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
});



document.addEventListener("DOMContentLoaded", function () {
    function initAutocomplete(inputId, latId, lngId) {
        const input = document.getElementById(inputId);
        if (!input) return; // safe check if field doesn’t exist

        const autocomplete = new google.maps.places.Autocomplete(input);

        autocomplete.addListener("place_changed", function () {
            const place = autocomplete.getPlace();
            if (!place.geometry) {
                alert("No details available for: '" + place.name + "'");
                return;
            }

            const lat = place.geometry.location.lat();
            const lng = place.geometry.location.lng();

            document.getElementById(latId).value = lat;
            document.getElementById(lngId).value = lng;
        });
    }

    // Init for both Add + Edit
    initAutocomplete("locationInputAdd", "lat_add", "lng_add");
    initAutocomplete("locationInputEdit", "lat_edit", "lng_edit");
});


document.addEventListener("DOMContentLoaded", function () {
  // Delay loading Select2 JS
  setTimeout(function () {
    var s = document.createElement('script');
    s.src = "https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js";
    s.onload = function () {

    // Call for both dropdowns
    loadCategories("categorySelectEdit");
    loadCategories("categorySelectAdd");
   


    // fetch categories AFTER Select2 is available
    fetch(`${domainUrl}smo/getProcedures`)
    .then((res) => res.json())
    .then((data) => {
      populateProcedureSelect("procedureSelectAdd", data);
      populateProcedureSelect("procedureSelectEdit", data);
    })
  .catch((err) => console.error("Error fetching Procedures:", err));
        
    };
    document.head.appendChild(s);
  }, 2000); // delay 2 seconds
});

function populateProcedureSelect(selectId, data) {
  const select = document.getElementById(selectId);

  // clear old options, keep blank for placeholder
  select.innerHTML = '<option></option>';

  // populate options
  data.hospital_categories.forEach((item) => {
    const option = new Option(item.procedure, item.id, false, false);
    select.add(option);
  });

  // Initialize Select2 safely
  $(`#${selectId}`).select2({
    placeholder: "Select Procedures",
    allowClear: true,
    width: '100%',
  });
}

function loadCategories(selectId) {
    
  fetch(`${domainUrl}smo/getCategories`)
    .then((res) => res.json())
    .then((data) => {
      const select = document.getElementById(selectId);

      // clear old options, keep blank for placeholder
      select.innerHTML = '<option></option>';

      // populate options
      data.hospital_categories.forEach((item) => {
        const option = new Option(item.name, item.id, false, false);
        select.add(option);
      });

      // Initialize or re-initialize Select2
      $(`#${selectId}`).select2({
        placeholder: "Select categories",
        allowClear: true,
        width: '100%',
      });
    })
    .catch((err) => console.error(`Error fetching categories for ${selectId}:`, err));
}


 function initChipInput(containerId, inputId, hiddenId) {
  const chipContainer = document.getElementById(containerId);
  const chipInput = document.getElementById(inputId);
  const hiddenInput = document.getElementById(hiddenId);
  let chips = [];

  function updateHiddenInput() {
    hiddenInput.value = chips.join(",");
  }

  function addChip(text) {
    if (!text.trim() || chips.includes(text.trim())) return;

    chips.push(text.trim());
    updateHiddenInput();

    const chip = document.createElement("span");
    chip.className = "badge badge-primary d-flex align-items-center";
    chip.style.gap = "5px";
    chip.style.padding = "8px";

    chip.innerHTML = `
      ${text}
      <button type="button" class="close ml-2" style="font-size: 14px;">&times;</button>
    `;

    chip.querySelector("button").addEventListener("click", () => {
      chipContainer.removeChild(chip);
      chips = chips.filter(c => c !== text.trim());
      updateHiddenInput();
    });

    chipContainer.insertBefore(chip, chipInput);
    chipInput.value = "";
  }

  // trigger by typing
  chipInput.addEventListener("keydown", function (e) {
    if (e.key === "Enter") {
      e.preventDefault();
      addChip(chipInput.value);
    }
  });

  // trigger by blur
  chipInput.addEventListener("blur", function () {
    if (chipInput.value.trim() !== "") {
      addChip(chipInput.value);
    }
  });

  // return an API so you can call it later
  return {
    addChip,
    getChips: () => [...chips],
    clearChips: () => {
      chips = [];
      chipContainer.querySelectorAll("span.badge").forEach(chip => chip.remove());
      updateHiddenInput();
    }
  };
}


  // initialize for both fields
  document.addEventListener("DOMContentLoaded", function () {
    initChipInput("chip-container-services", "chip-input-services", "services-offered-hidden");
    initChipInput("chip-container-benefits", "chip-input-benefits", "exclusive-mulkmed-hidden");
  });

document.addEventListener("DOMContentLoaded", () => {
  const wrapper = document.getElementById("uploadWrapper");
  const addBtn = document.getElementById("addMore");
  const placeholder = "http://placehold.jp/150x150.png";

  function makeCard() {
    const card = document.createElement("div");
    card.className = "card shadow-sm border-0 upload-item";
    card.innerHTML = `
      <div class="card-body text-center">
        <input type="file" accept="image/png,image/jpeg" name="photos[]" class="form-control file-input mb-3">
        <img src="${placeholder}" class="preview"
             style="width:120px;height:120px;display:block;border:1px solid #ccc;border-radius:6px;object-fit:cover;margin:0 auto;">
        <button type="button" class="btn btn-outline-danger btn-sm mt-3 remove-btn d-none">Remove</button>
      </div>
    `;
    return card;
  }

  // add new card
  addBtn.addEventListener("click", () => wrapper.appendChild(makeCard()));

  // preview on file change
  wrapper.addEventListener("change", (e) => {
    if (!e.target.classList.contains("file-input")) return;
    const card = e.target.closest(".upload-item");
    const img = card.querySelector(".preview");
    const rm = card.querySelector(".remove-btn");

    const file = e.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = ev => {
        img.src = ev.target.result;
        rm.classList.remove("d-none");
      };
      reader.readAsDataURL(file);
    } else {
      img.src = placeholder;
      rm.classList.add("d-none");
    }
  });

  // remove card (reset first one instead of deleting all)
  wrapper.addEventListener("click", (e) => {
    if (!e.target.classList.contains("remove-btn")) return;
    const card = e.target.closest(".upload-item");

    if (wrapper.children.length === 1) {
      // reset
      const input = card.querySelector(".file-input");
      const img = card.querySelector(".preview");
      input.value = "";
      img.src = placeholder;
      e.target.classList.add("d-none");
    } else {
      card.remove();
    }
  });
});

const PLACEHOLDER = "http://placehold.jp/150x150.png";

function makeExistingCard(img) {
  // img = {id, url}
  return $(`
    <div class="card shadow-sm border-0 upload-item" data-image-id="${img.id}" data-original-url="${img.url || PLACEHOLDER}">
      <div class="card-body text-center">
        <input type="hidden" name="existing_images[]" value="${img.id}">
        <input type="hidden" name="remove_images[]" value="" class="remove-flag">

        <input type="file" accept="image/png,image/jpeg" name="replace_images[${img.id}]"
               class="form-control file-input mb-3">

        <img src="${img.url || PLACEHOLDER}" class="preview"
             style="width:120px; height:120px; display:block; border:1px solid #ccc; border-radius:6px; object-fit:cover; margin:0 auto;">

        <div class="d-flex justify-content-center gap-2 mt-3">
          <button type="button" class="btn btn-outline-secondary btn-sm reset-btn">Revert</button>
          <button type="button" class="btn btn-outline-danger btn-sm remove-existing-btn">Remove</button>
        </div>
      </div>
    </div>
  `);
}

function makeNewCard() {
  return $(`
    <div class="card shadow-sm border-0 upload-item">
      <div class="card-body text-center">
        <input type="file" accept="image/png,image/jpeg" name="photos[]" class="form-control file-input mb-3">
        <img src="${PLACEHOLDER}" class="preview"
             style="width:120px; height:120px; display:block; border:1px solid #ccc; border-radius:6px; object-fit:cover; margin:0 auto;">
        <div class="d-flex justify-content-end mt-3">
          <button type="button" class="btn btn-outline-danger btn-sm remove-new-btn d-none">Remove</button>
        </div>
      </div>
    </div>
  `);
}

// Preview on change (works for both existing replace & new upload)
$(document).on("change", "#editImagesWrapper .file-input", function () {
  const file = this.files && this.files[0];
  const $card = $(this).closest(".upload-item");
  const $img  = $card.find(".preview");
  const $removeNew = $card.find(".remove-new-btn");

  if (!file) {
    // reset to original (existing) or placeholder (new)
    const original = $card.data("original-url") || PLACEHOLDER;
    $img.attr("src", original);
    $removeNew.addClass("d-none");
    return;
  }

  if (!/^image\/(png|jpeg)$/.test(file.type)) {
    this.value = "";
    alert("Please select JPG or PNG.");
    return;
  }

  const reader = new FileReader();
  reader.onload = e => {
    $img.attr("src", e.target.result);
    if ($removeNew.length) $removeNew.removeClass("d-none");
  };
  reader.readAsDataURL(file);
});

// Remove NEW card
$(document).on("click", "#editImagesWrapper .remove-new-btn", function () {
  $(this).closest(".upload-item").remove();
});

// Remove EXISTING image (marks for deletion + greys out)
$(document).on("click", "#editImagesWrapper .remove-existing-btn", function () {
  const $card = $(this).closest(".upload-item");
  $card.find(".remove-flag").val($card.data("image-id"));
  $card.find(".file-input").prop("disabled", true).val("");
  $card.css("opacity", 0.6);
  $card.find(".preview").css("filter", "grayscale(1)");
});

// Reset EXISTING (undo delete/replace)
$(document).on("click", "#editImagesWrapper .reset-btn", function () {
  const $card = $(this).closest(".upload-item");
  $card.find(".remove-flag").val("");
  $card.find(".file-input").prop("disabled", false).val("");
  const original = $card.data("original-url") || PLACEHOLDER;
  $card.find(".preview").attr("src", original).css("filter", "none");
  $card.css("opacity", 1);
});
