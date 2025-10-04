jQuery(document).ready(function($) {
    $('#gymlite-login-form').on('submit', function(e) {
        e.preventDefault();
        var formData = $(this).serialize() + '&action=gymlite_login';
        $.ajax({
            url: gymlite_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                UIkit.notification({message: response.data.message, status: 'success'});
                if (response.data.redirect) {
                    window.location.href = response.data.redirect;
                }
            },
            error: function(xhr) {
                UIkit.notification({message: xhr.responseJSON?.data?.message || 'Login failed', status: 'danger'});
            }
        });
    });
});