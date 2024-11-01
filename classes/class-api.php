<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

include_once dirname(__FILE__) . '/class-veplatform-api.php';

class Ve_API extends Ve_Platform_API {

    protected $requestEcommerce = 'WooCommerce/';

    public function __construct() {
        parent::__construct();
        include_once plugin_dir_path(__FILE__) . 'class-masterdata.php';
    }    

    protected function loadConfig() {
        try {
            $config = get_option('ve_platform', array());
            $this->config['tag'] = array_key_exists('ve_tag', $config) ? $config['ve_tag'] : $this->config['tag'];
            $this->config['pixel'] = array_key_exists('ve_pixel', $config) ? $config['ve_pixel'] : $this->config['pixel'];
            $this->config['token'] = array_key_exists('ve_token', $config) ? $config['ve_token'] : $this->config['token'];
            $this->config['data_active'] = array_key_exists('ve_data_active', $config) ? $config['ve_data_active'] : $this->config['data_active'];
            $this->config['productSync_active'] = array_key_exists('ve_productSync_active', $config) ? $config['ve_productSync_active'] : $this->config['productSync_active'];
        } catch (Exception $e) {
            $this->veLogger->logException($e);
        }
    }

    protected function deleteConfig() {
        delete_option('ve_platform');
    }

    public function setParams($returnParams = false) {
        try {
            global $wp_version, $woocommerce;
            if(!isset($woocommerce)) {
                return;
            }

            $domain = preg_replace("(^https?:\/\/)", "", get_site_url());
            $default_country = explode(':', get_option('woocommerce_default_country', ''));
            $country = $default_country[0];

            $userInfo = get_userdata(get_current_user_id());
            $name = "";

            if ($userInfo) {
                $name = trim($userInfo->first_name . ' ' . $userInfo->last_name);
            } else if (empty($name) && $userInfo != false) {
                $name = $userInfo->display_name;
            }

            $this->requestParams = array(
                'domain' => $domain,
                'language' => get_option('WPLANG', 'en'),
                'email' => get_option('admin_email'),
                'phone' => null,
                'merchant' => get_option('blogname'),
                'country' => $country,
                'currency' => get_option('woocommerce_currency'),
                'contactName' => $name,
                'version' => 'wp:' . $wp_version . ';woo:' . $woocommerce->version,
                'ecommerce' => 'WooCommerce',
                'moduleVersion' => VE_MODULE_VERSION
            );

            if ($returnParams) return $this->requestParams;
        } catch (Exception $e) {
            $this->veLogger->logException($e);
        }
    }

    public function getWSUrl($requestAction) {
        return $url = esc_url($this->requestDomain . "veconnect/" . $requestAction);
    }

    public function getMasterData() {
        $data = array();

        try {
            $ve_data = new VeData($this->veLogger, $this->wp_service);
            $data = $ve_data->getMasterData();

        } catch (Exception $e) {
            $this->veLogger->logException($e);
        }

        return json_encode($data);
    }

    public function get_tag_template_path() {
        $module_path = str_replace('\\','/',_VEPLATFORM_PLUGIN_DIR_);
        $template_path = $module_path . 'templates/frontend/ve-tag.php';
        return realpath($template_path);
    }
}
