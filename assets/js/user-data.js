jQuery(document).ready(function($) {
    $('#gymlite-user-data-form').on('submit', function(e) {
        e.preventDefault();
        var formData = $(this).serialize() + '&action=gymlite_update_user_data';
        $.ajax({
            url: gymlite_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                UIkit.notification({message: response.data.message, status: 'success'});
            },
            error: function(xhr) {
                UIkit.notification({message: xhr.responseJSON?.data?.message || 'Update failed', status: 'danger'});
            }
        });
    });
});