<?php
// Get settings
$options = get_option('aaf_settings');
$business_name = isset($options['business_name']) ? $options['business_name'] : 'Your Business Name';
$business_address = isset($options['business_address']) ? $options['business_address'] : 'Manirampur, Jashore, Khulna Division, Bangladesh';
$business_phone = isset($options['business_phone']) ? $options['business_phone'] : '+880 1981-308611';
$business_email = isset($options['business_email']) ? $options['business_email'] : 'wpnayanray@gmail.com';
$recaptcha_site_key = isset($options['recaptcha_site_key']) ? $options['recaptcha_site_key'] : '6LfXKZgrAAAAAEAMHIQEzHvHrspRlgY3rZWiy7up';
$business_hours = isset($options['business_hours']) ? $options['business_hours'] : '';
?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 max-w-6xl mx-auto">
  <div>
    <form id="aaf-appointment-form" class="bg-white p-8 rounded-2xl shadow-xl space-y-6">
      <div class="text-center">
        <h2 class="text-3xl font-bold text-gray-800 mb-2">Book an Appointment with <?php echo esc_html($business_name); ?></h2>
        <p class="text-gray-600">Fill out the form below to schedule your appointment</p>
      </div>

      <div class="space-y-4">
        <div>
          <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
          <input type="text" id="name" name="name" placeholder="John Doe" pattern="[A-Za-z\s]+" title="Only letters and spaces" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
            <input type="email" id="email" name="email" placeholder="john@example.com" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
          </div>

          <div>
            <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
            <input type="tel" id="phone" name="phone" placeholder="(123) 456-7890" pattern="^\+?[0-9]{10,15}$" title="Enter a valid phone number with 10 to 15 digits" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
          </div>
        </div>

        <div>
          <label for="service" class="block text-sm font-medium text-gray-700 mb-1">Service</label>
          <select id="service" name="service" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
            <option value="">Select a Service</option>
            <?php
            $services = aaf_get_service_options();
            foreach ($services as $service) {
              $service = trim($service);
              if (!empty($service)) {
                echo '<option value="' . esc_attr($service) . '">' . esc_html($service) . '</option>';
              }
            }
            ?>
          </select>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Appointment Date</label>
            <?php
            $date_restrictions = aaf_get_date_restrictions();
            ?>
            <input type="date" id="date" name="date" min="<?php echo date('Y-m-d', strtotime('+' . $date_restrictions['min'] . ' days')); ?>" max="<?php echo date('Y-m-d', strtotime('+' . $date_restrictions['max'] . ' days')); ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
          </div>

          <div>
            <label for="time" class="block text-sm font-medium text-gray-700 mb-1">Appointment Time</label>
            <input type="time" id="time" name="time" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
          </div>
        </div>
        
        <div>
          <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Additional Notes</label>
          <textarea id="notes" name="notes" placeholder="Any special requests or details..." class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition" rows="4"></textarea>
          <p class="text-sm text-gray-500 mt-1">Maximum 500 characters</p>
        </div>
      </div>

      <div class="flex justify-center my-6">
        <div class="g-recaptcha" data-sitekey="<?php echo esc_attr($recaptcha_site_key); ?>"></div>
      </div>

      <button type="submit" name="aaf_submit" class="w-full bg-gradient-to-r from-blue-600 to-indigo-700 hover:from-blue-700 hover:to-indigo-800 text-white font-semibold py-3 px-4 rounded-lg shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition duration-300 ease-in-out">
        Book Appointment
      </button>
    </form>
  </div>
  
  <div>
   <div class="bg-white p-8 rounded-2xl shadow-xl">
  <h3 class="text-2xl font-bold text-gray-800 mb-4">Location of <?php echo esc_html($business_name); ?></h3>
  <div id="aaf-map" class="w-full h-96 rounded-lg"></div>
  <div class="mt-6">
    <h4 class="text-xl font-semibold text-gray-800 mb-2">Contact Information for <?php echo esc_html($business_name); ?></h4>
    <ul class="space-y-2">
      <li class="flex items-start">
        <svg class="w-5 h-5 text-blue-600 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
        </svg>
        <span><?php echo esc_html($business_address); ?></span>
      </li>
      <li class="flex items-start">
        <svg class="w-5 h-5 text-blue-600 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
        </svg>
        <span><?php echo esc_html($business_phone); ?></span>
      </li>
      <li class="flex items-start">
        <svg class="w-5 h-5 text-blue-600 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
        </svg>
        <span><?php echo esc_html($business_email); ?></span>
      </li>
    </ul>
    
    <?php if (!empty($business_hours)) : ?>
    <div class="mt-6">
      <h4 class="text-xl font-semibold text-gray-800 mb-2">Business Hours</h4>
      <div class="bg-gray-50 p-4 rounded-lg">
        <?php echo nl2br(esc_html($business_hours)); ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

  </div>
</div>
