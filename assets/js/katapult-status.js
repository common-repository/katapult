/* Add ajax cancel order */
(function () {
    function getParameterByName(name) {
        let url = window.location.href;
        name = name.replace(/[\[\]]/g, "\\$&");
        var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
            results = regex.exec(url);
        if (!results) return null;
        if (!results[2]) return '';
        return decodeURIComponent(results[2].replace(/\+/g, " "));
    }

    function cancel_order() {
        jQuery.post(
            ajaxurl,
            {
                'action': 'cancel_order',
                'data':  getParameterByName('post')
            },
            function(response){
                location.reload();
            }
        );
    }

    jQuery(document).ready(function() {
        jQuery('.cancel-order').on('click', function() {
            cancel_order();
        });
    });
})();
