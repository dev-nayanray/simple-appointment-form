jQuery(document).ready(function($) {
    // Initialize map
    function initMap() {
        // Default location (you can change this to your business location)
        var location = {lat: 23.1046, lng: 89.0764}; // Manirampur, Jashore, Bangladesh as example
        
        var map = new google.maps.Map(document.getElementById('aaf-map'), {
            zoom: 15,
            center: location
        });
        
        var marker = new google.maps.Marker({
            position: location,
            map: map,
            title: 'Our Location'
        });
    }
    
    // Initialize map when Google Maps is loaded
    if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
        initMap();
    }
    
    // Create modal popup
    function createModal(message, isSuccess) {
        // Remove existing modal
        $('#aaf-modal').remove();
        
        // Create modal HTML
        var modalHtml = `
            <div id="aaf-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white rounded-2xl shadow-xl max-w-md w-full mx-4">
                    <div class="p-6">
                        <div class="text-center">
                            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full ${isSuccess ? 'bg-green-100' : 'bg-red-100'} mb-4">
                                ${isSuccess ? 
                                    '<svg class="h-10 w-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>' :
                                    '<svg class="h-10 w-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>'
                                }
                            </div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-2">${isSuccess ? 'Success!' : 'Error!'}</h3>
                            <div class="mt-2">
                                <p class="text-gray-600">${message}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-6 py-4 rounded-b-2xl">
                        <div class="flex justify-center">
                            <button id="aaf-modal-close" class="px-6 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                ${isSuccess ? 'Continue' : 'Try Again'}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Append modal to body
        $('body').append(modalHtml);
        
        // Show modal
        $('#aaf-modal').removeClass('hidden');
        
        // Close modal on button click
        $('#aaf-modal-close').on('click', function() {
            $('#aaf-modal').remove();
            if (isSuccess) {
                // Reset form if success
                $('#aaf-appointment-form')[0].reset();
                // Reset reCAPTCHA
                if (typeof grecaptcha !== 'undefined') {
                    grecaptcha.reset();
                }
            }
        });
        
        // Close modal on backdrop click
        $('#aaf-modal').on('click', function(e) {
            if (e.target === this) {
                $('#aaf-modal').remove();
                if (isSuccess) {
                    // Reset form if success
                    $('#aaf-appointment-form')[0].reset();
                    // Reset reCAPTCHA
                    if (typeof grecaptcha !== 'undefined') {
                        grecaptcha.reset();
                    }
                }
            }
        });
    }
    
    $('#aaf-appointment-form').on('submit', function(e) {
        e.preventDefault();
        
        // Get form data
        var formData = {
            action: 'aaf_process_appointment',
            nonce: aaf_ajax_object.nonce,
            name: $('input[name="name"]').val(),
            email: $('input[name="email"]').val(),
            phone: $('input[name="phone"]').val(),
            service: $('select[name="service"]').val(),
            date: $('input[name="date"]').val(),
            time: $('input[name="time"]').val(),
            notes: $('textarea[name="notes"]').val(),
            g_recaptcha_response: $('#g-recaptcha-response').val()
        };
        
        // Disable submit button and show loading
        $('button[name="aaf_submit"]').prop('disabled', true).text('Submitting...');
        
        // Send AJAX request
        $.post(aaf_ajax_object.ajax_url, formData, function(response) {
            if (response.success) {
                // Show success modal
                createModal(response.data, true);
            } else {
                // Show error modal
                createModal(response.data, false);
            }
            // Re-enable submit button
            $('button[name="aaf_submit"]').prop('disabled', false).text('Book Appointment');
        }).fail(function() {
            // Show error modal with custom message
            createModal(aaf_ajax_object.error_message, false);
            // Re-enable submit button
            $('button[name="aaf_submit"]').prop('disabled', false).text('Book Appointment');
        });
    });
});
