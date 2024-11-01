<?php

class VeHelper {

    private static $hasRun = 0;

    public static function getHasRun() {
        return self::$hasRun;
    }

    public static function increaseHasRun() {
        self::$hasRun++;
    }

    /**
     * Check if WooCommerce is installed
     *
     * @return bool
     */
    public static function checkWooCommerceIsInstalled() {
        return !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' )
            ) ) && !is_plugin_active_for_network( 'woocommerce/woocommerce.php' );
    }

    /**
     * Check if CURL extension is installed
     *
     * @return bool
     */
    public static function checkCurlExtension() {
        return extension_loaded('curl') && function_exists('curl_init') && function_exists('curl_reset');
    }

    /**
     * Check if GD extension is installed
     *
     * @return bool
     */
    public static function checkGdExtension() {
        return extension_loaded('gd') && function_exists('gd_info');
    }

    /**
     * Check if WooCommerce version is greater than 3.0
     *
     * @return bool
     */
    public static function is_latest_woo_version() {
        if (self::checkWooCommerceIsInstalled()) {
           return false;
        }
        
        $wooCommerceVersion = WooCommerce::instance()->version;
        return version_compare( $wooCommerceVersion, '3.0', ">=" );
    }
}
