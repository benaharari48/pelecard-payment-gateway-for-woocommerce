<?php
/*
Plugin Name: פלאגין תשלום פלקארד
Description: שער תשלום מותאם אישית עבור WooCommerce באמצעות פלקארד פותח ע"י חברת Dooble.
Version: 1.0
Author: Dooble
*/

// הגדרה כללית של הפלגין והכללת הקבצים הנדרשים
if (!defined('ABSPATH')) {
    exit; // יציאה אם ניגשו ישירות
}

add_action('plugins_loaded', 'pelecard_payment_gateway_init', 11);

function pelecard_payment_gateway_init() {
    if (class_exists('WC_Payment_Gateway')) {
        include_once('includes/class-pelecard-payment-gateway.php');
    }
}

add_filter('woocommerce_payment_gateways', 'add_pelecard_payment_gateway');

function add_pelecard_payment_gateway($gateways) {
    $gateways[] = 'WC_Gateway_Pelecard';
    return $gateways;
}
?>