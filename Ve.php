<?php

/**
 * Plugin Name: Ve for WooCommerce
 * Plugin URI: https://www.ve.com
 * Description: The only automated marketing solution to solve your abandonment & conversion problems at every stage in the customer’s journey.
 * Version: 17.1.3.0
 * Author: Ve Global
 * Author URI: https://www.ve.com
 * License: MIT
 * Text Domain: veplatform
 * Domain Path: /languages/
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('PHP_VERSION_TO_COMPARE', '5.5.0');
define('VE_MODULE_VERSION', '17.1.3');

if (version_compare(phpversion(), PHP_VERSION_TO_COMPARE, '<')) {
    $error = '<p>Please use PHP 5.6.0 or greater.</p>';
    wp_die($error, 'Plugin Activation Error', array('response' => 200, 'back_link' => true));
}

$vendorAutoload = dirname(__FILE__) . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    include_once dirname(__FILE__) . '/vendor/autoload.php';
}

if (!function_exists('get_plugins')) {
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

define('_VEPLATFORM_MINIMUM_WP_VERSION_', '4.0');
define('_VEPLATFORM_PLUGIN_URL_', plugin_dir_url(__FILE__));
define('_VEPLATFORM_PLUGIN_DIR_', plugin_dir_path(__FILE__));
define('_VEPLATFORM_PLUGIN_HTTP_URL_', esc_url(add_query_arg(array('page' => 'veplatform-plugin-settings'), admin_url('admin.php'))));
define('_VEPLATFORM_REVIEW_URL_', 'https://wordpress.org/support/plugin/veplatform/reviews/');

include_once(_VEPLATFORM_PLUGIN_DIR_ . '/classes/class-ve-logger.php');
include_once(_VEPLATFORM_PLUGIN_DIR_ . '/classes/class-masterdata.php');
include_once(_VEPLATFORM_PLUGIN_DIR_ . '/classes/class-api.php');
include_once(_VEPLATFORM_PLUGIN_DIR_ . '/classes/class-helper.php');
include_once(_VEPLATFORM_PLUGIN_DIR_ . '/classes/class-product-service.php');
include_once(_VEPLATFORM_PLUGIN_DIR_ . '/classes/class-data-service.php');
include_once(_VEPLATFORM_PLUGIN_DIR_ . '/classes/class-internal-woo-service.php');
include_once(_VEPLATFORM_PLUGIN_DIR_ . '/classes/class-internal-db-service.php');
veplatform_load_textdomain();

if (is_admin()) {

    add_action('wp_ajax_logaction', 'logAction');
    add_action('wp_ajax_vpinstalled', 'markInstall');
    add_action('wp_ajax_deactivatevp', 'deactivatePlugin');

    register_activation_hook(__FILE__, 'veplatform_plugin_activation');
    register_uninstall_hook(__FILE__, 'veplatform_plugin_uninstall');

    add_action('activated_plugin', 'veplatform_plugin_activated');
    add_action('deactivated_plugin', 'veplatform_plugin_deactivated');
    add_action('admin_init', 'veplatform_plugin_check_dependencies');
    add_action('admin_menu', 'veplatform_plugin_menu');
    $plugin = plugin_basename(__FILE__);
    add_filter('plugin_action_links_' . $plugin, 'veplatform_plugin_settings_link');
} else {
    add_action('wp_footer', 'veplatform_get_masterData');
    add_action('wp_head', 'veplatform_add_tag_head');
    add_action('wp_footer', 'veplatform_add_tag_footer');
    add_action('woocommerce_thankyou', 'veplatform_add_pixel');
    add_action('init', 'veplatform_startSession', 1); // Used to start the session
}

function veplatform_load_textdomain() {
    $locale = apply_filters('plugin_locale', get_locale(), 'veplatform');
    if (file_exists(__DIR__ . '/languages/' . $locale . '.mo')) {
        load_textdomain('veplatform', __DIR__ . '/languages/' . $locale . '.mo');
    } else {
        load_textdomain('veplatform', __DIR__ . '/languages/en_US.mo');
    }
}

function veplatform_plugin_menu() {
    add_submenu_page('woocommerce', 'Ve for WooCommerce', 'Ve for WooCommerce', 'administrator', 'veplatform-plugin-settings', 'veplatform_plugin_settings_page');
    }

function veplatform_plugin_settings_link($links) {
    if (array_key_exists('edit', $links)) {
        unset($links['edit']);
    }

    $settings_link = '<a href="' . _VEPLATFORM_PLUGIN_HTTP_URL_ . '">' . __('Settings') . '</a>';
    $review_link = '<a href="' . _VEPLATFORM_REVIEW_URL_ . '">' . __('Write a review') . '</a>';
    return array_merge( array($settings_link, $review_link), $links );
}

function veplatform_plugin_settings_page() {
    $config = get_option('ve_platform', array());

    // set parameters needed in settings page
    $api = new Ve_API();
    $isInstalled = (isset($config['WSInstall']) && $config['WSInstall']);

    $params = $api->setParams(true);
    $params['isInstallFlow'] = !$isInstalled;
    $params['apiURL'] = $api->getWSUrl('Install');
    $params['pluginsUrl'] = admin_url('plugins.php');
    $params['settingsError'] = 'Error accessing the settings page. Please try again later.';

    wp_enqueue_style('veplatform_admin_styles_ve_veplatform_admin', _VEPLATFORM_PLUGIN_URL_ . 'assets/css/ve_veplatform_admin.css', array());
    wp_register_script('veplatform_admin_js', _VEPLATFORM_PLUGIN_URL_ . 'assets/js/veplatform_admin.js', array('jquery'));
    wp_localize_script('veplatform_admin_js', 'wsData', array_merge($params, array('ajax_url' => admin_url('admin-ajax.php'))));
    wp_enqueue_script('veplatform_admin_js');

    $template = 've-platform-thank-you';
    include(_VEPLATFORM_PLUGIN_DIR_ . '/templates/admin/' . $template . '.php');
}

/**
* Set a flag in the plugin configuration, to prevent multiple install calls.
* Also, if token, tag & pixel are provided, they also get saved (backward compatibility)
*/
function markInstall() {

    $config = get_option('ve_platform', array());
    if (isset($config) &&  $config['WSInstall'] == true) {
        echo json_encode($config);
        die;
    }

    $cfg = array('WSInstall' => true);
    $veLogger = new VeLogger();

    if (!empty($_POST['response'])) {
        $response = $_POST['response'];

        if (!empty($response['Token']) && !empty($response['URLTag']) && !empty($response['URLPixel'])) {
            $cfg['ve_token'] = $response['Token'];
            $cfg['ve_tag'] = $response['URLTag'];
            $cfg['ve_pixel'] = $response['URLPixel'];
            $cfg['ve_data_active'] = $response['VeDataActive'];
            $cfg['ve_productSync_active'] = $response['ProductSyncActive'];
        }

        $veLogger->logMessage('Module configurations: ' . print_r($cfg, true));
        $veLogger->logMessage('Modules installed: ' . print_r(get_plugins(), true));

        if (isset($response['ProductSyncActive']) && $response['ProductSyncActive'] == 'true') {
            $internalWooService = new InternalWooService($veLogger);
            $databaseService = new DatabaseService();
            $dataService = new DataService($veLogger, $internalWooService, $databaseService);

            $dataService->createProductSyncTable();
        }
    }

    update_option('ve_platform', $cfg);
    echo json_encode($cfg);
    die;
}

/**
* Fired when logging action is called via AJAX
*/
function logAction() {

    $message = strip_tags(stripslashes($_POST['message']));
    $logType = (!isset($_POST['isError']) || $_POST['isError'] === false) ? 'INFO' : 'ERROR';
    $veLogger = new VeLogger();
    $veLogger->logMessage($message, $logType);

    if ($logType == 'ERROR') {
        $veLogger->logException(new Exception($message));
    }

    echo json_encode(array('status' => 'ok'));
    die;
}

/**
* Automated deactivation of plugin, if WS install didn't work
*/
function deactivatePlugin() {
    deactivate_plugins(WP_PLUGIN_DIR . '/Ve/Ve.php');

    $activePlugins = get_option('active_plugins');
    if (!in_array('Ve', $activePlugins)) {
        echo json_encode(array('status' => 'ok', 'msg' => __('VE_WSINSTALL_ERROR', 'veplatform'), 'redirectUrl' => admin_url('plugins.php')));
    } else {
        echo json_encode(array('status' => 'error', 'message' => __('VE_DEACTIVATE_ERR', 'veplatform')));
    }

    die;
}

/**
* Method called when "Activate" link is clicked from Plugins page
*/
function veplatform_plugin_activation() {
    $error = validate();
    if (isset($error)) {
        deactivate_plugins(basename(__FILE__));
        wp_die($error, 'Plugin Activation Error', array('response' => 200, 'back_link' => true));
    }
}

/**
* Method called after plugin was successfully activated
*/
function veplatform_plugin_activated($file) {
    if (strpos($file, 'Ve.php') !== false) {
        $veLogger = new VeLogger();
        $veLogger->logMessage('Module has been activated', 'INFO');
        exit(wp_redirect(_VEPLATFORM_PLUGIN_HTTP_URL_));
    }
}

/**
* Method called to deactivate plugin
*/
function veplatform_plugin_deactivated($file) {
    if (strpos($file, 'Ve.php') !== false) {
        uninstall_module('deactivated');
    }
}

function veplatform_plugin_uninstall() {
    uninstall_module('deleted');
}

function uninstall_module($action_type) {
    $veLogger = new VeLogger();
    $veLogger->logMessage("Module has been " . $action_type, 'INFO');

    $config = get_option('ve_platform');
    //the module was already uninstalled
    if($config == false){
        return;
    }

    $api = new Ve_API();
    $api->uninstallModule();
}

function veplatform_get_masterData() {
    $config = get_option('ve_platform', array());
    if (array_key_exists('ve_tag', $config)) {
        $api = new Ve_API();
        $vedata_active = $api->getConfigOption('data_active', true);
        if (isset($vedata_active) && $vedata_active == 'true') {
            wp_register_script('masterdata_js', _VEPLATFORM_PLUGIN_URL_ . 'assets/js/masterdata.js', array('jquery'));
            wp_localize_script('masterdata_js', 'wsData', array('ajax_url' => admin_url('admin-ajax.php')));
            wp_enqueue_script('masterdata_js');
            include(_VEPLATFORM_PLUGIN_DIR_ . '/templates/frontend/ve-masterdata.php');
        }
    }
}

function veplatform_add_tag_head() {
    $config = get_option('ve_platform', array());

    if (array_key_exists('ve_tag', $config)) {
        //needed in the template
        $api = new Ve_API();
        $tag_template_path = $api->get_tag_template_path();
        include $tag_template_path;
    }
    else {
        $veLogger = new VeLogger();
        $veLogger->logMessage("Tag not available in config. Module deactivated.");
        uninstall_module('deactivated');
    }
}

function veplatform_add_tag_footer() {
    $config = get_option('ve_platform', array());

    if (array_key_exists('ve_tag', $config)) {
        $files = get_included_files();
        $api = new Ve_API();
        $tag_template_path = $api->get_tag_template_path();
        if (!in_array($tag_template_path, $files)) {
            echo "<!-- Ve Global Integration -->
                <script type='text/javascript' async src='" . $api->getConfigOption('tag', true) . "'></script>
                <!-- /Ve Global Integration -->";
        }
    }
    else {
        $veLogger = new VeLogger();
        $veLogger->logMessage("Tag not available in config. Module deactivated.");
        uninstall_module('deactivated');
    }
}

function veplatform_add_pixel() {
    $config = get_option('ve_platform', array());

    if (array_key_exists('ve_pixel', $config)) {
        $api = new Ve_API();
        //needed in the template
        $vepixel = $api->getConfigOption('pixel', true);
        include(_VEPLATFORM_PLUGIN_DIR_ . '/templates/frontend/ve-pixel.php');
    }
}

/**
* Check if the session has started or not, and start it.
*/
function veplatform_startSession() {
    if (!session_id()) {
        session_start();
    }
}

add_action('woocommerce_removed_coupon', 'action_woocommerce_removed_coupon');

function action_woocommerce_removed_coupon() {
    $_SESSION["coupon"] = "reset";
}

add_action('woocommerce_coupon_loaded', 'action_woocommerce_coupon_loaded');

function action_woocommerce_coupon_loaded() {
    $coupon = WC()->cart->coupons;
    if (isset($coupon) && count($coupon) > 0) {
        $_SESSION["coupon"] = reset($coupon);
    }
}
//for logged in customers
add_action('wp_ajax_updatecart', 'updatecart_callback');
//for visitors
add_action('wp_ajax_nopriv_updatecart', 'updatecart_callback');

function updatecart_callback() {
    veplatform_startSession();
    $wp_service = new InternalWpService();
    $ve_logger = new VeLogger();
    $api = new VeData($ve_logger, $wp_service);
    $cart = $api->getCart();
    echo json_encode($cart);
    wp_die();
}

function validate() {
    global $wp_version;
    $veLogger = new VeLogger();
    $error = null;

    try {
        if (version_compare(phpversion(), PHP_VERSION_TO_COMPARE, '<')) {
            $error = __('VE_PHP_VERSION', 'veplatform');
        }
        if (!VeHelper::checkCurlExtension() || !VeHelper::checkGdExtension()) {
            $error = __('VE_CURL_GD', 'veplatform');
        }
        if (version_compare($wp_version, _VEPLATFORM_MINIMUM_WP_VERSION_, '<')) {
            $error = __('VE_REQUIREMENT_UNMET', 'veplatform') . _VEPLATFORM_MINIMUM_WP_VERSION_ . ' ' . __('VE_REQUIREMENT_GREATER', 'veplatform');
        }
        if (VeHelper::checkWooCommerceIsInstalled()) {
            $error = __('VE_WOO_MISSING', 'veplatform');
        }
    } catch (Exception $exception) {
        $veLogger->logException($exception);
    }

    return $error;
}

/**
 * This is our callback function to return our products.
 *
 * @param WP_REST_Request $request This function accepts a rest request to process data.
 */
function veapi_get_products( $request ) {
    $veLogger = new VeLogger();
    try {

        // Turn off all error reporting
        error_reporting(0);

        $internalWooService = new InternalWooService($veLogger);
        $databaseService = new DatabaseService();
        $dataService = new DataService($veLogger, $internalWooService, $databaseService);
        $productService = new ProductService($dataService);

        $method = $request->get_param('method');
        $batchSize = $request->get_param('batchSize');
        $startingProductIndex = $request->get_param('startingProductIndex');

        $products = $productService->getProducts($method, $batchSize, $startingProductIndex);
        return rest_ensure_response($products);
    } catch (Exception $exception) {
        $veLogger->logException($exception);
    }
}

add_action( 'rest_api_init', 'register_initial_product_sync_route' );
/**
 * This function is where we register our routes for our example endpoint.
 */
function register_initial_product_sync_route() {
    // Here we are registering our route for a collection of products and creation of products.
    register_rest_route( 'veplatform', '/products', array(
        array(
            'methods'  => WP_REST_Server::READABLE,
            'callback' => 'veapi_get_products',
        )
    ));
}

add_action('transition_post_status', 'action_product_updated', 10, 3);

function action_product_updated( $new_status, $old_status, $post ) {
    $veLogger = new VeLogger();
    try {
        $api = new Ve_API();
        $productSync_active = $api->getConfigOption('productSync_active', true);
        if (isset($productSync_active) && $productSync_active == 'true' && !empty($post->ID)) {
            if (in_array( $post->post_type, array('product', 'shop_order', 'product_variation') ) ) {
                $internalWooService = new InternalWooService($veLogger);
                $databaseService = new DatabaseService();
                $dataService = new DataService($veLogger, $internalWooService, $databaseService);

                if( $post->post_type == 'shop_order') {
                    $order = wc_get_order($post->ID);
                    $items = $order->get_items();
                    foreach ( $items as $item ) {
                        $dataService->storeUpdatedProduct($item['product_id']);
                    }
                }
                else if ($new_status == 'trash') {
                    $dataService->storeDeletedProduct($post->ID);
                }
                else {
                    if ($post->post_type == 'product_variation' && isset($post->post_parent)) {
                        $dataService->storeDeletedProduct($post->post_parent, false);
                    }

                    $dataService->storeUpdatedProduct($post->ID);
                }
            }
        }
    } catch (Exception $exception) {
        $veLogger->logException($exception);
    }
}

add_action( 'before_delete_post',  'action_product_deleted' ); 
add_action( 'woocommerce_save_product_variation', 'action_product_variation_updated', 10, 2 );

function action_product_variation_updated($postId, $i) {
    process_product_variation($postId, 'update');
}

function action_product_deleted($postId) {
    process_product_variation($postId, 'delete');
}

function process_product_variation($postId, $actionType) {
    $veLogger = new VeLogger();
    try {
        $api = new Ve_API();
        $productSync_active = $api->getConfigOption('productSync_active', true);
        if (isset($productSync_active) && $productSync_active == 'true' && !empty($postId)) {
            $internalWooService = new InternalWooService($veLogger);
            $databaseService = new DatabaseService();
            $dataService = new DataService($veLogger, $internalWooService, $databaseService);

            if($actionType == 'update') {
                $dataService->storeUpdatedProduct($postId);
            } elseif ($actionType = 'delete'){
                $dataService->storeDeletedProduct($postId);
            }
        }
    } catch (Exception $exception) {
        $veLogger->logException($exception);
    }
}

function veplatform_plugin_check_dependencies() {
    if (VeHelper::checkWooCommerceIsInstalled()) {
        $plugin = plugin_basename( __FILE__ );
        //our module is active
        if(!in_array(@$_GET['action'], array('activate-plugin', 'upgrade-plugin','activate','do-plugin-upgrade')) &&
            is_plugin_active($plugin) ) {

            deactivate_plugins($plugin);
            $error = __('VE_WOO_MISSING', 'veplatform');
            wp_die($error, 'WooCommerce Plugin Deactivated', array('response' => 200, 'back_link' => true));
        }
    }
}

add_action('woocommerce_product_type_changed', 'action_product_type_updated', 10, 3);

function action_product_type_updated($product, $old_type, $new_type) {
    $veLogger = new VeLogger();
    try {
        $api = new Ve_API();
        $productSync_active = $api->getConfigOption('productSync_active', true);
        if (isset($productSync_active) && $productSync_active == 'true' && !empty($product->id)) {
            $internalWooService = new InternalWooService($veLogger);
            $databaseService = new DatabaseService();
            $dataService = new DataService($veLogger, $internalWooService, $databaseService);

            if ($old_type == 'simple' && $new_type == 'variable') {
                $dataService->storeDeletedProduct($product->id, false);
            } else if ($old_type == 'variable' && $new_type == 'simple') {
                 $dataService->storeUpdatedProduct($product->id, true);
            }
        }
    } catch (Exception $exception) {
        $veLogger->logException($exception);
    }
}
