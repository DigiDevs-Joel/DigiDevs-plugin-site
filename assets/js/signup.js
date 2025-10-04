jQuery(document).ready(function($) {
    $('#gymlite-signup-form').on('submit', function(e) {
        e.preventDefault();
        var formData = $(this).serialize() + '&action=gymlite_signup';
        $.ajax({
            url: gymlite_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                UIkit.notification({message: response.data.message, status: 'success'});
            },
            error: function(xhr) {
                UIkit.notification({message: xhr.responseJSON?.data?.message || 'Signup failed', status: 'danger'});
            }
        });
    });
});