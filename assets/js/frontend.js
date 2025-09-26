jQuery(document).ready(function($) {

    function updateCounter() {
        $.getJSON(alcoData.restUrl, function(res) {
            var count = parseInt(res.count, 10);

            var $sliderValue = $('.forminator-slider-value');
            var $slider = $('.forminator-slide.ui-slider');
            var $hiddenInput = $('.forminator-slider-hidden-min');
            var $submitBtn = $('.forminator-button-submit');
            $slider.slider('disable');
            if ($sliderValue.length) {
                $sliderValue.text(count);
            }

            if ($slider.length && $slider.slider) {
                $slider.slider('value', count);
            }

            if ($hiddenInput.length) {
                $hiddenInput.val(count);
            }

            if ($submitBtn.length) {
                if (count <= 0) {
                    $submitBtn.prop('disabled', true).text('Limit Reached');
                } else {
                    $submitBtn.prop('disabled', false).text('Submit');
                }
            }
        });
    }

    updateCounter();

    $(document).on('forminator:form:submit:success', function() {
        $.ajax({
            url: alcoData.decreaseUrl,
            method: 'POST',
            headers: { 'X-WP-Nonce': alcoData.nonce },
            success: function() {
                updateCounter();
            }
        });
    });

});