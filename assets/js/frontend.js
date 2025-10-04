jQuery(document).ready(function($) {
    // Handle check-in button clicks
    $(".gymlite-checkin").on("click", function(e) {
        e.preventDefault();
        var classId = $(this).data("class-id");
        $.ajax({
            url: gymlite_ajax.ajax_url,
            type: "POST",
            data: {
                action: "gymlite_checkin",
                class_id: classId,
                nonce: gymlite_ajax.nonce
            },
            success: function(response) {
                UIkit.notification({ message: response.data.message, status: "success" });
            },
            error: function(xhr) {
                UIkit.notification({ message: xhr.responseJSON.data.message || "Check-in failed", status: "danger" });
            }
        });
    });

    // Handle form submissions (lead, signup, booking, referral)
    $(".gymlite-lead-form, .gymlite-signup, .gymlite-booking, .gymlite-referrals").on("submit", function(e) {
        e.preventDefault();
        var form = $(this);
        var action = form.attr("id").replace("-form", "_submit");
        $.ajax({
            url: gymlite_ajax.ajax_url,
            type: "POST",
            data: form.serialize() + "&action=" + action,
            success: function(response) {
                UIkit.notification({ message: response.data.message, status: "success" });
                form[0].reset();
            },
            error: function(xhr) {
                UIkit.notification({ message: xhr.responseJSON.data.message || "Submission failed", status: "danger" });
            }
        });
    });

    // Handle booking link clicks
    $("a[href*='book_class']").on("click", function(e) {
        e.preventDefault();
        var url = $(this).attr("href");
        $.ajax({
            url: url,
            type: "GET",
            success: function(response) {
                UIkit.notification({ message: response.data.message, status: "success" });
            },
            error: function(xhr) {
                UIkit.notification({ message: xhr.responseJSON.data.message || "Booking failed", status: "danger" });
            }
        });
    });

    // Copy referral link to clipboard
    $(".gymlite-referrals input[type='text']").on("click", function() {
        $(this).select();
        document.execCommand("copy");
        UIkit.notification({ message: "Referral link copied to clipboard!", status: "success" });
    });
});