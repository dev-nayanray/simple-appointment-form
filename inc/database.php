<?php
function aaf_create_appointments_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'appointments';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id INT NOT NULL AUTO_INCREMENT,
        name VARCHAR(100),
        email VARCHAR(100),
        phone VARCHAR(20),
        service VARCHAR(100),
        date DATE,
        time TIME,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
