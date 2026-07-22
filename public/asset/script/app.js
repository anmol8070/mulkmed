var browserTimezone = (Intl && Intl.DateTimeFormat)
    ? Intl.DateTimeFormat().resolvedOptions().timeZone
    : '';

window.BROWSER_TIMEZONE = browserTimezone;

$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
        'X-Timezone': browserTimezone
    }
});

function ensureTimezoneInput($form, timezoneValue) {
    if (!$form || !$form.length || !timezoneValue) {
        return;
    }

    var $existing = $form.find('input[name="browser_timezone"]');
    if ($existing.length) {
        $existing.val(timezoneValue);
        return;
    }

    $('<input>', {
        type: 'hidden',
        name: 'browser_timezone',
        value: timezoneValue
    }).appendTo($form);
}

if (browserTimezone) {
    try {
        document.cookie = 'browser_timezone=' + encodeURIComponent(browserTimezone) + '; path=/';
    } catch (e) {}

    $(function () {
        $('form').each(function () {
            ensureTimezoneInput($(this), browserTimezone);
        });
    });

    $(document).on('submit', 'form', function () {
        ensureTimezoneInput($(this), browserTimezone);
    });
}


var user_type = $('#user_type').val();

$(function () {
    $(document).on('submit', '.product-form', function (e) {
        var $form = $(this);
        if ($form.data('submitted') === true) {
            e.preventDefault();
            return false;
        }
        $form.data('submitted', true);

        var $submitBtn = $form.find('button[type="submit"], input[type="submit"]');
        setTimeout(function () {
        $submitBtn.prop('disabled', true);
        }, 10);
    });

    $(document).on('submit', '#addCatForm, #editCatForm', function (e) {
        var $form = $(this);
        var currentUserType = $('#user_type').val() || window.user_type;
        if (currentUserType != "1") {
            return;
        }

        if ($form.data('submitted') === true) {
            e.preventDefault();
            return false;
        }
        $form.data('submitted', true);

        var $submitBtn = $form.find('button[type="submit"], input[type="submit"]');
        $submitBtn.prop('disabled', true);
    });

    $(document).ajaxComplete(function () {
        var $forms = $('#addCatForm, #editCatForm');
        $forms.data('submitted', false);
        $forms.find('button[type="submit"], input[type="submit"]').prop('disabled', false);
    });
});


