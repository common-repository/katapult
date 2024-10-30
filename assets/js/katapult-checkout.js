/* Add addEventListener */
(function(){
    document.addEventListener("katapultjs-init", function(e) {
        katapult.checkout.set(window.katapultCart);
        katapult.checkout.load();
    });
})();
