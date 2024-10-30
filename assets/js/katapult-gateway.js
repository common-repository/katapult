/* Add Update Checkout */
(function(){
    jQuery(document).on('updated_checkout', function() {
        jQuery('.wc_payment_method').on('click', function() {
            // Commented out to stop spinning.
            /*jQuery('body').trigger('update_checkout');*/
        })
    })
})();
