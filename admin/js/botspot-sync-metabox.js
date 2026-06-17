(function($) {
    'use strict';
    $(document).ready(function() {
        $('.botspot-sync-now').on('click', function(e) {
            e.preventDefault();
            var btn = $(this);
            var result = btn.siblings('.botspot-sync-result');
            btn.prop('disabled', true).text(bsptMetabox.syncing);
            result.html('');
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'botspot_wp_manual_sync',
                    nonce: bsptMetabox.nonce,
                    post_id: btn.data('post-id')
                },
                success: function(response) {
                    if (response.success) {
                        result.html('<span style="color: green;">&#10003; ' + response.data.message + '</span>');
                    } else {
                        result.html('<span style="color: red;">&#10007; ' + (response.data.message || bsptMetabox.failed) + '</span>');
                    }
                },
                error: function() {
                    result.html('<span style="color: red;">&#10007; ' + bsptMetabox.requestFailed + '</span>');
                },
                complete: function() {
                    btn.prop('disabled', false).text(bsptMetabox.syncNow);
                }
            });
        });
    });
})(jQuery);
