jQuery(document).ready(function($) {
    let submittedEmail = '';

    // 🔵 1️⃣ Inject CSS for loader
    const loaderCSS = `
        .forminator-slider-loader {
            position:absolute;
            top:50%;
            left:50%;
            transform:translate(-50%,-50%);
            background:rgba(255,255,255,0.85);
            padding:10px 20px;
            border-radius:6px;
            display:flex;
            align-items:center;
            gap:8px;
            z-index:99;
            font-size:14px;
            font-weight:500;
        }
        .forminator-slider-loader .spinner {
            width:18px;
            height:18px;
            border:2px solid #ccc;
            border-top-color:#0073aa;
            border-radius:50%;
            animation:spin 0.7s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    `;
    $('head').append('<style>' + loaderCSS + '</style>');

    // 🔵 2️⃣ Create loader div and append inside slider container
    const $sliderContainer = $('.forminator-field-slider');
    const $loader = $('<div class="forminator-slider-loader" style="display:none;">' +
        '<div class="spinner"></div>' +
        '<span>Updating…</span>' +
        '</div>');
    if ($sliderContainer.length) {
        $sliderContainer.css('position', 'relative').append($loader);
    }

    function showLoader() { $loader.show(); }

    function hideLoader() { $loader.hide(); }

    // 🔵 3️⃣ Counter update with loader
    function updateCounter() {
        showLoader();
        $.getJSON(alcoData.restUrl)
            .done(function(res) {
                var count = parseInt(res.count, 10);
                var $sliderValue = $('.forminator-slider-value');
                var $slider = $('.forminator-slide.ui-slider');
                var $hiddenInput = $('.forminator-slider-hidden-min');
                var $submitBtn = $('.forminator-button-submit');

                if ($slider.length && $slider.slider) {
                    $slider.slider('disable').slider('value', count);
                }
                if ($sliderValue.length) $sliderValue.text(count);
                if ($hiddenInput.length) $hiddenInput.val(count);

                if ($submitBtn.length) {
                    if (count <= 0) {
                        $submitBtn.prop('disabled', true).text('Limit Reached');
                    } else {
                        $submitBtn.prop('disabled', false).text('Submit');
                    }
                }
            })
            .always(hideLoader);
    }

    updateCounter();

    // 🔵 4️⃣ Capture email live
    $(document).on('input change', '.forminator-custom-form input[type="email"]', function() {
        submittedEmail = $(this).val();
    });

    // 🔵 5️⃣ Decrease counter after successful submission
    $(document).on('forminator:form:submit:success', function() {
        if (!submittedEmail) return;

        showLoader();
        $.ajax({
            url: alcoData.decreaseUrl,
            method: 'POST',
            headers: { 'X-WP-Nonce': alcoData.nonce },
            data: { email: submittedEmail },
            success: function() {
                updateCounter();
            },
            // complete: hideLoader
        });
    });
});