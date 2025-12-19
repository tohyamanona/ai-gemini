(function($) {
    console.log('AI Gemini credit.js loaded');

    $(document).on('click', '.btn-select-package', function() {
        console.log('Package button clicked');

        var packageId = $(this).data('package');
        var $button = $(this);
        
        $button.prop('disabled', true).text(AIGeminiCredit.i18n_processing);
        
        $.ajax({
            url: AIGeminiCredit.rest_url_order,
            method: 'POST',
            headers: {
                'X-WP-Nonce': AIGeminiCredit.nonce
            },
            data: JSON.stringify({ package_id: packageId }),
            contentType: 'application/json',
            success: function(response) {
                console.log('Order response:', response);

                if (response.success && response.order_code) {
                    window.location.href = AIGeminiCredit.pay_base_url + response.order_code;
                } else {
                    alert(response.message || AIGeminiCredit.i18n_error);
                    $button.prop('disabled', false).text(AIGeminiCredit.i18n_select);
                }
            },
            error: function(xhr) {
                console.log('Order error:', xhr);

                var message = xhr.responseJSON && xhr.responseJSON.message 
                    ? xhr.responseJSON.message 
                    : AIGeminiCredit.i18n_error;
                alert(message);
                $button.prop('disabled', false).text(AIGeminiCredit.i18n_select);
            }
        });
    });
})(jQuery);