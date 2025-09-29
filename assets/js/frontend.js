jQuery(document).ready(function($) {
    let submittedEmail = '';

    // Disable submit button initially
    $('.forminator-button.forminator-button-submit').prop('disabled', true);

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

    // 🔵 4️⃣ Capture email and validate live
    let emailCheckTimeout;
    $(document).on('input change', '.forminator-custom-form input[type="email"]', function() {
        submittedEmail = $(this).val();
        const $emailField = $(this);
        const $submitBtn = $('.forminator-button.forminator-button-submit');

        // Initially disable the submit button
        $submitBtn.prop('disabled', true);

        // Clear previous error message if any
        $emailField.next('.email-error-message').remove();

        // Clear previous timeout
        clearTimeout(emailCheckTimeout);

        // Set new timeout to check email (wait 500ms after user stops typing)
        emailCheckTimeout = setTimeout(function() {
            if (!submittedEmail) {
                $submitBtn.prop('disabled', true);
                return;
            }

            showLoader();
            $.ajax({
                url: alcoData.decreaseUrl + '?check_only=1', // Adding check parameter to indicate just checking
                method: 'POST',
                headers: { 'X-WP-Nonce': alcoData.nonce },
                data: { email: submittedEmail, check_only: true },
                success: function(response) {
                    if (response.code === 'email_exists') {
                        $submitBtn.prop('disabled', true);
                        // Show error message under email field
                        $('<div class="email-error-message" style="color: #E04562; font-size: 12px; margin-top: 5px;">' +
                                'This email has already been submitted. Please use a different email address.</div>')
                            .insertAfter($emailField);
                    } else {
                        // Enable submit button only if email is valid and not used
                        $submitBtn.prop('disabled', false);
                    }
                },
                error: function() {
                    $submitBtn.prop('disabled', true);
                },
                complete: hideLoader
            });
        }, 500);
    });

    // 🔵 5️⃣ Decrease counter after successful submission
    $(document).on('forminator:form:submit:success', function(e) {
        if (!submittedEmail) return;

        showLoader();
        $.ajax({
            url: alcoData.decreaseUrl,
            method: 'POST',
            headers: { 'X-WP-Nonce': alcoData.nonce },
            data: { email: submittedEmail },
            success: function(response) {
                if (response.code === 'email_exists') {
                    hideLoader();
                    alert('This email has already been submitted. Please use a different email address.');
                    // Prevent form submission success
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
                updateCounter();
            },
            error: function(xhr) {
                if (xhr.responseJSON && xhr.responseJSON.code === 'email_exists') {
                    alert('This email has already been submitted. Please use a different email address.');
                } else {
                    alert('An error occurred. Please try again.');
                }
                // Prevent form submission success
                e.preventDefault();
                e.stopPropagation();
                updateCounter();
                hideLoader();

            },
        });
    });
});