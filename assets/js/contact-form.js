	/*-------------------------
        Ajax Contact Form 
    ---------------------------*/
    $(function() {

        // Get the form.
        var form = $('#contact-form');

        // Get the messages div.
        var formMessages = $('.form-messege');

        // Set up an event listener for the contact form.
        $(form).submit(function(e) {
            // Stop the browser from submitting the form.
            e.preventDefault();

            // Serialize the form data.
            var formData = $(form).serialize();

            // Submit the form using AJAX.
            $.ajax({
                type: 'POST',
                url: $(form).attr('action'),
                data: formData,
                dataType: 'json'
            })
            .done(function(response) {
                if (response && response.success) {
                    $(formMessages).removeClass('error').addClass('success');
                    $(formMessages).text(response.message || 'Message sent successfully.');
                    $('#contact-form input,#contact-form textarea').val('');
                } else {
                    $(formMessages).removeClass('success').addClass('error');
                    if (response && response.errors) {
                        $(formMessages).text(response.errors.join('; '));
                    } else if (response && response.error) {
                        $(formMessages).text(response.error);
                    } else {
                        $(formMessages).text('An unknown error occurred.');
                    }
                }
            })
            .fail(function(jqXHR) {
                $(formMessages).removeClass('success').addClass('error');
                var text = 'Oops! An error occurred and your message could not be sent.';
                try {
                    var resp = JSON.parse(jqXHR.responseText);
                    if (resp && resp.error) text = resp.error;
                } catch (e) {}
                $(formMessages).text(text);
            });
        });

    });