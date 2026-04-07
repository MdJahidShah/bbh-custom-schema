/**
 * BBH Custom Schema - Review Notice Script
 *
 * @package BBH_Custom_Schema
 */

(function( $ ) {
    'use strict';

    $(document).ready(function() {
        // Handle notice dismissal via AJAX
        $(document).on('click', '.bbhcuschma-schema-review-notice .notice-dismiss', function(e) {
            e.preventDefault();
            
            $.ajax({
                url: bbhcuschmaSchemaReview.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bbhcuschma_mark_reviewed',
                    nonce: bbhcuschmaSchemaReview.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('.bbhcuschma-schema-review-notice').fadeOut();
                    }
                }
            });
        });
    });

})(jQuery);
