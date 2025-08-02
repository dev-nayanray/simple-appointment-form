<?php
/**
 * Plugin Name: Advanced Appointment Form
 * Description: Appointment booking form with reCAPTCHA, database saving, user email confirmation, and admin view.
 * Version: 1.0
 * Author: Nayan Ray
 */

if (!defined('ABSPATH')) exit;

include_once plugin_dir_path(__FILE__) . 'inc/database.php';

// Create DB table on activation
register_activation_hook(__FILE__, 'aaf_create_appointments_table');

// Enqueue Tailwind CSS and reCAPTCHA scripts in front-end
function aaf_enqueue_scripts() {
    if (!is_admin()) {
        echo '<script src="https://cdn.tailwindcss.com"></script>';
        echo '<script src="https://www.google.com/recaptcha/api.js" async defer></script>';
        
        // Get settings
        $options = get_option('aaf_settings');
        $recaptcha_secret_key = isset($options['recaptcha_secret_key']) ? $options['recaptcha_secret_key'] : '6LfXKZgrAAAAAKTy_0xOG4h_HAyQN6rzqGIRdkCp';
        $custom_css = isset($options['custom_css']) ? $options['custom_css'] : '';
        
        // Get custom messages
        $custom_messages = aaf_get_custom_messages();
        
        // Enqueue our custom script for AJAX
        wp_enqueue_script('aaf-ajax-script', plugin_dir_url(__FILE__) . 'assets/ajax-handler.js', array('jquery'));
        wp_localize_script('aaf-ajax-script', 'aaf_ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aaf_nonce'),
            'recaptcha_secret' => $recaptcha_secret_key,
            'success_message' => $custom_messages['success'],
            'error_message' => $custom_messages['error']
        ));
        
        // Enqueue custom CSS if set
        if (!empty($custom_css)) {
            wp_register_style('aaf-custom-css', false);
            wp_enqueue_style('aaf-custom-css');
            wp_add_inline_style('aaf-custom-css', $custom_css);
        }
        
        // Enqueue Google Maps API
        $google_maps_api_key = isset($options['google_maps_api_key']) ? $options['google_maps_api_key'] : 'AIzaSyABea1k_3ou5i7hgZ38KB6NMBmXBOF2EcQ';
        wp_enqueue_script('google-maps', 'https://maps.googleapis.com/maps/api/js?key=' . $google_maps_api_key, array(), null, true);
    }
}
add_action('wp_head', 'aaf_enqueue_scripts');

// Register shortcode for appointment form
function aaf_appointment_form_shortcode() {
    ob_start();
    include plugin_dir_path(__FILE__) . 'template/form-template.php';
    return ob_get_clean();
}
add_shortcode('appointment_form', 'aaf_appointment_form_shortcode');

// Function to get service options
function aaf_get_service_options() {
    $options = get_option('aaf_settings');
    $service_options = isset($options['service_options']) ? $options['service_options'] : "Web Design\nWordPress Development\nConsultation";
    return explode("\n", $service_options);
}

// Function to get date and time restrictions
function aaf_get_date_restrictions() {
    $options = get_option('aaf_settings');
    $min_days_advance = isset($options['min_days_advance']) ? $options['min_days_advance'] : 1;
    $max_days_advance = isset($options['max_days_advance']) ? $options['max_days_advance'] : 30;
    return array(
        'min' => $min_days_advance,
        'max' => $max_days_advance
    );
}

// Function to get custom messages
function aaf_get_custom_messages() {
    $options = get_option('aaf_settings');
    $success_message = isset($options['success_message']) ? $options['success_message'] : 'Appointment submitted successfully!';
    $error_message = isset($options['error_message']) ? $options['error_message'] : 'An error occurred. Please try again.';
    return array(
        'success' => $success_message,
        'error' => $error_message
    );
}

// AJAX handler for form submission
function aaf_process_appointment_form() {
    // Check nonce for security
    if (!wp_verify_nonce($_POST['nonce'], 'aaf_nonce')) {
        wp_die('Security check failed');
    }
    
    // Get settings
    $options = get_option('aaf_settings');
    $recaptcha_secret_key = isset($options['recaptcha_secret_key']) ? $options['recaptcha_secret_key'] : '6LfXKZgrAAAAAKTy_0xOG4h_HAyQN6rzqGIRdkCp';
    
    // Verify reCAPTCHA
    $secret = $recaptcha_secret_key;
    $response = $_POST['g_recaptcha_response'] ?? '';
    $verify = wp_remote_get("https://www.google.com/recaptcha/api/siteverify?secret=$secret&response=$response");
    $verify_body = wp_remote_retrieve_body($verify);
    $captcha_success = false;
    if ($verify_body) {
        $captcha_success = json_decode($verify_body)->success ?? false;
    }
    
    if (!$captcha_success) {
        wp_send_json_error('Captcha verification failed.');
        return;
    }
    
    global $wpdb;
    
    // Sanitize input data
    $name = sanitize_text_field($_POST['name']);
    $email = sanitize_email($_POST['email']);
    $phone = sanitize_text_field($_POST['phone']);
    $service = sanitize_text_field($_POST['service']);
    $date = sanitize_text_field($_POST['date']);
    $time = sanitize_text_field($_POST['time']);
    $notes = sanitize_textarea_field($_POST['notes']);
    
    // Insert appointment into DB
    $result = $wpdb->insert($wpdb->prefix . 'appointments', [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'service' => $service,
        'date' => $date,
        'time' => $time,
        'notes' => $notes,
    ]);
    
    if ($result === false) {
        wp_send_json_error('Failed to save appointment.');
        return;
    }
    
    // Get settings
    $options = get_option('aaf_settings');
    $admin_notification = isset($options['admin_notification']) ? $options['admin_notification'] : 1;
    $user_confirmation = isset($options['user_confirmation']) ? $options['user_confirmation'] : 1;
    $admin_email_setting = isset($options['admin_email']) ? $options['admin_email'] : '';
    $admin_email = !empty($admin_email_setting) ? $admin_email_setting : get_option('admin_email');
    $confirmation_email_subject = isset($options['confirmation_email_subject']) ? $options['confirmation_email_subject'] : 'Appointment Confirmation';
    $confirmation_email_message = isset($options['confirmation_email_message']) ? $options['confirmation_email_message'] : "Hi {name},\n\nThank you for booking an appointment.\n\nDetails:\nService: {service}\nDate: {date}\nTime: {time}\n\nRegards,\nYour Team";
    
    // Replace placeholders in confirmation email message
    $confirmation_email_message = str_replace(
        ['{name}', '{service}', '{date}', '{time}'],
        [$name, $service, $date, $time],
        $confirmation_email_message
    );
    
    // Email to admin if enabled
    if ($admin_notification) {
        wp_mail($admin_email, "New Appointment Booking",
            "Name: $name\nEmail: $email\nPhone: $phone\nService: $service\nDate: $date\nTime: $time\nNotes: $notes");
    }
    
    // Confirmation email to user if enabled
    if ($user_confirmation) {
        wp_mail($email, $confirmation_email_subject, $confirmation_email_message);
    }
    
    // Get custom success message
    $custom_messages = aaf_get_custom_messages();
    wp_send_json_success($custom_messages['success']);
}
add_action('wp_ajax_aaf_process_appointment', 'aaf_process_appointment_form');
add_action('wp_ajax_nopriv_aaf_process_appointment', 'aaf_process_appointment_form');

// Add admin menu for appointments
function aaf_register_admin_menu() {
    add_menu_page(
        'Appointment Form',
        'Appointments',
        'manage_options',
        'aaf_dashboard',
        'aaf_dashboard_page',
        'dashicons-calendar-alt',
        25
    );

    add_submenu_page(
        'aaf_dashboard',
        'All Appointments',
        'All Appointments',
        'manage_options',
        'aaf_dashboard',
        'aaf_dashboard_page'
    );
    
    add_submenu_page(
        'aaf_dashboard',
        'Settings',
        'Settings',
        'manage_options',
        'aaf_settings',
        'aaf_settings_page'
    );
}
add_action('admin_menu', 'aaf_register_admin_menu');

// Register plugin settings
function aaf_register_settings() {
    register_setting('aaf_settings_group', 'aaf_settings');
}
add_action('admin_init', 'aaf_register_settings');

// Admin page to display appointments
function aaf_dashboard_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'appointments';
    
    // Handle delete request
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $wpdb->delete($table_name, array('id' => $id));
        // Redirect to avoid resubmission
        wp_redirect(admin_url('admin.php?page=aaf_dashboard&deleted=true'));
        exit;
    }
    
    // Handle search
    $search = '';
    if (isset($_GET['s']) && !empty($_GET['s'])) {
        $search = sanitize_text_field($_GET['s']);
    }
    
    // Handle pagination
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 10;
    $offset = ($paged - 1) * $per_page;
    
    // Get total count for pagination
    if (!empty($search)) {
        $total_query = $wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE name LIKE %s OR email LIKE %s OR phone LIKE %s OR service LIKE %s", 
            '%' . $wpdb->esc_like($search) . '%', 
            '%' . $wpdb->esc_like($search) . '%', 
            '%' . $wpdb->esc_like($search) . '%', 
            '%' . $wpdb->esc_like($search) . '%');
    } else {
        $total_query = "SELECT COUNT(*) FROM $table_name";
    }
    $total_items = $wpdb->get_var($total_query);
    $total_pages = ceil($total_items / $per_page);
    
    // Get results with pagination and search
    if (!empty($search)) {
        $query = $wpdb->prepare("SELECT * FROM $table_name WHERE name LIKE %s OR email LIKE %s OR phone LIKE %s OR service LIKE %s ORDER BY created_at DESC LIMIT %d OFFSET %d", 
            '%' . $wpdb->esc_like($search) . '%', 
            '%' . $wpdb->esc_like($search) . '%', 
            '%' . $wpdb->esc_like($search) . '%', 
            '%' . $wpdb->esc_like($search) . '%', 
            $per_page, 
            $offset);
    } else {
        $query = $wpdb->prepare("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset);
    }
    $results = $wpdb->get_results($query);

    echo '<div class="wrap">';
    echo '<h1 class="wp-heading-inline">All Appointments</h1>';
    echo '<hr class="wp-header-end">';
    
    // Search form
    echo '<form method="get" action="' . admin_url('admin.php') . '" class="search-form" style="margin: 20px 0;">';
    echo '<input type="hidden" name="page" value="aaf_dashboard">';
    echo '<p class="search-box">';
    echo '<label class="screen-reader-text" for="appointment-search-input">Search Appointments:</label>';
    echo '<input type="search" id="appointment-search-input" name="s" value="' . esc_attr($search) . '" placeholder="Search by name, email, phone, or service...">';
    echo '<input type="submit" id="search-submit" class="button" value="Search Appointments">';
    echo '</p>';
    echo '</form>';
    
    // Display delete success message
    if (isset($_GET['deleted']) && $_GET['deleted'] == 'true') {
        echo '<div class="notice notice-success is-dismissible"><p>Appointment deleted successfully.</p></div>';
    }

    if ($results) {
        echo '<table class="wp-list-table widefat striped" style="margin-top:20px;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Service</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Notes</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';
        foreach ($results as $row) {
            echo "<tr>
                <td>{$row->id}</td>
                <td>" . esc_html($row->name) . "</td>
                <td>" . esc_html($row->email) . "</td>
                <td>" . esc_html($row->phone) . "</td>
                <td>" . esc_html($row->service) . "</td>
                <td>" . esc_html($row->date) . "</td>
                <td>" . esc_html($row->time) . "</td>
                <td>" . esc_html($row->notes) . "</td>
                <td>" . esc_html($row->created_at) . "</td>
                <td>
                    <a href='" . wp_nonce_url(admin_url('admin.php?page=aaf_dashboard&action=delete&id=' . $row->id), 'delete_appointment_' . $row->id) . "' class='button button-small' onclick='return confirm(\"Are you sure you want to delete this appointment?\")'>Delete</a>
                </td>
              </tr>";
        }
        echo '</tbody></table>';
        
        // Pagination
        if ($total_pages > 1) {
            echo '<div class="tablenav bottom">';
            echo '<div class="tablenav-pages">';
            echo '<span class="displaying-num">' . $total_items . ' items</span>';
            
            echo '<span class="pagination-links">';
            if ($paged > 1) {
                echo '<a class="first-page button" href="' . admin_url('admin.php?page=aaf_dashboard&s=' . urlencode($search) . '&paged=1') . '">&laquo;</a>';
                echo '<a class="prev-page button" href="' . admin_url('admin.php?page=aaf_dashboard&s=' . urlencode($search) . '&paged=' . ($paged - 1)) . '">&lsaquo;</a>';
            }
            
            echo '<span class="paging-input">';
            echo '<span class="tablenav-paging-text">';
            echo $paged . ' of <span class="total-pages">' . $total_pages . '</span>';
            echo '</span>';
            echo '</span>';
            
            if ($paged < $total_pages) {
                echo '<a class="next-page button" href="' . admin_url('admin.php?page=aaf_dashboard&s=' . urlencode($search) . '&paged=' . ($paged + 1)) . '">&rsaquo;</a>';
                echo '<a class="last-page button" href="' . admin_url('admin.php?page=aaf_dashboard&s=' . urlencode($search) . '&paged=' . $total_pages) . '">&raquo;</a>';
            }
            echo '</span>';
            echo '</div>';
            echo '</div>';
        }
    } else {
        echo '<p>No appointments found.</p>';
    }

    echo '</div>';
}

// Admin page for plugin settings
function aaf_settings_page() {
    // Get saved settings or set defaults
    $options = get_option('aaf_settings');
    $business_name = isset($options['business_name']) ? $options['business_name'] : '';
    $business_address = isset($options['business_address']) ? $options['business_address'] : '';
    $business_phone = isset($options['business_phone']) ? $options['business_phone'] : '';
    $business_email = isset($options['business_email']) ? $options['business_email'] : '';
    $recaptcha_site_key = isset($options['recaptcha_site_key']) ? $options['recaptcha_site_key'] : '';
    $recaptcha_secret_key = isset($options['recaptcha_secret_key']) ? $options['recaptcha_secret_key'] : '';
    $admin_notification = isset($options['admin_notification']) ? $options['admin_notification'] : 1;
    $user_confirmation = isset($options['user_confirmation']) ? $options['user_confirmation'] : 1;
    $admin_email = isset($options['admin_email']) ? $options['admin_email'] : '';
    $confirmation_email_subject = isset($options['confirmation_email_subject']) ? $options['confirmation_email_subject'] : 'Appointment Confirmation';
    $confirmation_email_message = isset($options['confirmation_email_message']) ? $options['confirmation_email_message'] : "Hi {name},\n\nThank you for booking an appointment.\n\nDetails:\nService: {service}\nDate: {date}\nTime: {time}\n\nRegards,\nYour Team";
    $custom_css = isset($options['custom_css']) ? $options['custom_css'] : '';
    $business_hours = isset($options['business_hours']) ? $options['business_hours'] : '';
    $google_maps_api_key = isset($options['google_maps_api_key']) ? $options['google_maps_api_key'] : 'AIzaSyABea1k_3ou5i7hgZ38KB6NMBmXBOF2EcQ';
    $service_options = isset($options['service_options']) ? $options['service_options'] : "Web Design\nWordPress Development\nConsultation";
    $min_days_advance = isset($options['min_days_advance']) ? $options['min_days_advance'] : 1;
    $max_days_advance = isset($options['max_days_advance']) ? $options['max_days_advance'] : 30;
    $success_message = isset($options['success_message']) ? $options['success_message'] : 'Appointment submitted successfully!';
    $error_message = isset($options['error_message']) ? $options['error_message'] : 'An error occurred. Please try again.';
    ?>
    <div class="wrap">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Appointment Form Settings</h1>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <form method="post" action="options.php" class="bg-white p-6 rounded-lg shadow-md">
                    <?php settings_fields('aaf_settings_group'); ?>
                    <?php do_settings_sections('aaf_settings_group'); ?>
                    
                    <div class="space-y-6">
                        <div>
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">Business Information</h2>
                            <div class="space-y-4">
                                <div>
                                    <label for="aaf_business_name" class="block text-sm font-medium text-gray-700 mb-1">Business Name</label>
                                    <input type="text" id="aaf_business_name" name="aaf_settings[business_name]" value="<?php echo esc_attr($business_name); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label for="aaf_business_address" class="block text-sm font-medium text-gray-700 mb-1">Business Address</label>
                                    <textarea id="aaf_business_address" name="aaf_settings[business_address]" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"><?php echo esc_textarea($business_address); ?></textarea>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="aaf_business_phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                                        <input type="text" id="aaf_business_phone" name="aaf_settings[business_phone]" value="<?php echo esc_attr($business_phone); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    
                                    <div>
                                        <label for="aaf_business_email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                                        <input type="email" id="aaf_business_email" name="aaf_settings[business_email]" value="<?php echo esc_attr($business_email); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">reCAPTCHA Settings</h2>
                            <div class="space-y-4">
                                <div>
                                    <label for="aaf_recaptcha_site_key" class="block text-sm font-medium text-gray-700 mb-1">Site Key</label>
                                    <input type="text" id="aaf_recaptcha_site_key" name="aaf_settings[recaptcha_site_key]" value="<?php echo esc_attr($recaptcha_site_key); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    <p class="mt-1 text-sm text-gray-500">Get your reCAPTCHA keys from <a href="https://www.google.com/recaptcha/admin" target="_blank" class="text-blue-600 hover:underline">Google reCAPTCHA</a></p>
                                </div>
                                
                                <div>
                                    <label for="aaf_recaptcha_secret_key" class="block text-sm font-medium text-gray-700 mb-1">Secret Key</label>
                                    <input type="text" id="aaf_recaptcha_secret_key" name="aaf_settings[recaptcha_secret_key]" value="<?php echo esc_attr($recaptcha_secret_key); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">Notification Settings</h2>
                            <div class="space-y-4">
                                <div class="flex items-center">
                                    <input type="checkbox" id="aaf_admin_notification" name="aaf_settings[admin_notification]" value="1" <?php checked(1, $admin_notification); ?> class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    <label for="aaf_admin_notification" class="ml-2 block text-sm text-gray-700">Send notification to admin when new appointment is booked</label>
                                </div>
                                
                                <div>
                                    <label for="aaf_admin_email" class="block text-sm font-medium text-gray-700 mb-1">Admin Email Address</label>
                                    <input type="email" id="aaf_admin_email" name="aaf_settings[admin_email]" value="<?php echo esc_attr($admin_email); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    <p class="mt-1 text-sm text-gray-500">Leave blank to use the default admin email</p>
                                </div>
                                
                                <div class="flex items-center">
                                    <input type="checkbox" id="aaf_user_confirmation" name="aaf_settings[user_confirmation]" value="1" <?php checked(1, $user_confirmation); ?> class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    <label for="aaf_user_confirmation" class="ml-2 block text-sm text-gray-700">Send confirmation email to user after booking</label>
                                </div>
                                
                                <div>
                                    <label for="aaf_confirmation_email_subject" class="block text-sm font-medium text-gray-700 mb-1">Confirmation Email Subject</label>
                                    <input type="text" id="aaf_confirmation_email_subject" name="aaf_settings[confirmation_email_subject]" value="<?php echo esc_attr($confirmation_email_subject); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label for="aaf_confirmation_email_message" class="block text-sm font-medium text-gray-700 mb-1">Confirmation Email Message</label>
                                    <textarea id="aaf_confirmation_email_message" name="aaf_settings[confirmation_email_message]" rows="6" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"><?php echo esc_textarea($confirmation_email_message); ?></textarea>
                                    <p class="mt-1 text-sm text-gray-500">Use {name}, {service}, {date}, and {time} as placeholders</p>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">Custom CSS</h2>
                            <div class="space-y-4">
                                <div>
                                    <label for="aaf_custom_css" class="block text-sm font-medium text-gray-700 mb-1">Custom CSS</label>
                                    <textarea id="aaf_custom_css" name="aaf_settings[custom_css]" rows="6" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"><?php echo esc_textarea($custom_css); ?></textarea>
                                    <p class="mt-1 text-sm text-gray-500">Add custom CSS to style the appointment form</p>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">Business Hours</h2>
                            <div class="space-y-4">
                                <div>
                                    <label for="aaf_business_hours" class="block text-sm font-medium text-gray-700 mb-1">Business Hours</label>
                                    <textarea id="aaf_business_hours" name="aaf_settings[business_hours]" rows="6" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"><?php echo esc_textarea($business_hours); ?></textarea>
                                    <p class="mt-1 text-sm text-gray-500">Enter your business hours (e.g., Monday-Friday: 9am-5pm)</p>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">Google Maps API</h2>
                            <div class="space-y-4">
                                <div>
                                    <label for="aaf_google_maps_api_key" class="block text-sm font-medium text-gray-700 mb-1">Google Maps API Key</label>
                                    <input type="text" id="aaf_google_maps_api_key" name="aaf_settings[google_maps_api_key]" value="<?php echo esc_attr($google_maps_api_key); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    <p class="mt-1 text-sm text-gray-500">Enter your Google Maps API key</p>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">Service Options</h2>
                            <div class="space-y-4">
                                <div>
                                    <label for="aaf_service_options" class="block text-sm font-medium text-gray-700 mb-1">Service Options</label>
                                    <textarea id="aaf_service_options" name="aaf_settings[service_options]" rows="6" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"><?php echo esc_textarea($service_options); ?></textarea>
                                    <p class="mt-1 text-sm text-gray-500">Enter service options, one per line</p>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">Date and Time Restrictions</h2>
                            <div class="space-y-4">
                                <div>
                                    <label for="aaf_min_days_advance" class="block text-sm font-medium text-gray-700 mb-1">Minimum Days in Advance</label>
                                    <input type="number" id="aaf_min_days_advance" name="aaf_settings[min_days_advance]" value="<?php echo esc_attr($min_days_advance); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    <p class="mt-1 text-sm text-gray-500">Minimum number of days in advance an appointment can be booked</p>
                                </div>
                                
                                <div>
                                    <label for="aaf_max_days_advance" class="block text-sm font-medium text-gray-700 mb-1">Maximum Days in Advance</label>
                                    <input type="number" id="aaf_max_days_advance" name="aaf_settings[max_days_advance]" value="<?php echo esc_attr($max_days_advance); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    <p class="mt-1 text-sm text-gray-500">Maximum number of days in advance an appointment can be booked</p>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">Custom Messages</h2>
                            <div class="space-y-4">
                                <div>
                                    <label for="aaf_success_message" class="block text-sm font-medium text-gray-700 mb-1">Success Message</label>
                                    <textarea id="aaf_success_message" name="aaf_settings[success_message]" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"><?php echo esc_textarea($success_message); ?></textarea>
                                    <p class="mt-1 text-sm text-gray-500">Message displayed after successful appointment submission</p>
                                </div>
                                
                                <div>
                                    <label for="aaf_error_message" class="block text-sm font-medium text-gray-700 mb-1">Error Message</label>
                                    <textarea id="aaf_error_message" name="aaf_settings[error_message]" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"><?php echo esc_textarea($error_message); ?></textarea>
                                    <p class="mt-1 text-sm text-gray-500">Message displayed when there's an error</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-end">
                            <?php submit_button('Save Settings', 'primary', 'submit', false, array('class' => 'bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500')); ?>
                        </div>
                    </div>
                </form>
            </div>
            
            <div>
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Shortcode</h2>
                    <p class="text-gray-600 mb-4">Use this shortcode to display the appointment form on any page or post:</p>
                    <code class="bg-gray-100 p-3 rounded-md block text-sm">[appointment_form]</code>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow-md mt-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Need Help?</h2>
                    <ul class="space-y-2">
                        <li><a href="https://developers.google.com/recaptcha/docs/display" target="_blank" class="text-blue-600 hover:underline">reCAPTCHA Documentation</a></li>
                        <li><a href="https://wordpress.org/support/" target="_blank" class="text-blue-600 hover:underline">WordPress Support</a></li>
                        <li><a href="mailto:wpnayanray@gmail.com" class="text-blue-600 hover:underline">Contact Developer</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php
}
