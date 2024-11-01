<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * VePlatformAPI requests
 */
abstract class Ve_Platform_API {
    protected $veLogger = null;
    protected $internalWooService = null;
    protected $databaseService = null;
    protected $wp_service;
    protected $requestDomain = 'https://veconnect.veinteractive.com/API/';
    protected $requestInstall = 'Install';
    protected $requestUninstall = 'Uninstall';
    protected $requestTimeout = 15;
    protected $requestParams = array();
    protected $config = array(
        'tag' => null,
        'pixel' => null,
        'token' => null,
        'data_active' => false,
        'productSync_active' => false
    );

    public function __construct() {
        include_once 'class-ve-logger.php';
        include_once 'class-internal-wp-service.php';
        $this->setParams();
        $this->loadConfig();
        $this->veLogger = new VeLogger();
        $this->wp_service = new InternalWpService();
        $this->internalWooService = new InternalWooService($this->veLogger);
        $this->databaseService = new DatabaseService();
    }

    abstract protected function setParams();

    abstract protected function loadConfig();

    abstract protected function deleteConfig();

    /**
     * @return boolean
     */
    protected function getToken() {
        $token = $this->getConfigOption('token');
        return $token;
    }

    /**
     * @param string $option
     * @param boolean $reload (default: false)
     * @return string
     */
    public function getConfigOption($option, $reload = false) {
        try {
            if ($reload === true) {
                $this->loadConfig();
            }
            $value = array_key_exists($option, $this->config) ? $this->config[$option] : null;
            return $value;
        } catch (Exception $exception) {
            $this->veLogger->logException($exception);
        }
    }

    /**
     * @return boolean
     */
    public function isInstalled() {
        foreach (array('tag', 'pixel', 'token') as $name) {
            if ($this->config[$name] === null) {
                return false;
            }
        }
        return true;
    }

    /**
     * @return boolean
     */
    public function showLogin() {
        return $this->isInstalled();
    }

    /**
     * @param string $url
     * @return string
     */
    protected function cleanUrl($url) {
        $cleanUrl = preg_replace("(^https?:)", "", $url);
        return $cleanUrl;
    }

    /**
     * @return boolean
     */
    public function uninstallModule() {
        try {
            $params = $this->requestParams;
            $params['token'] = $this->getToken();

            $dataService = new DataService($this->veLogger, $this->internalWooService, $this->databaseService);                
            $dataService->deleteProductSyncTable();

            $this->deleteConfig();
            $response = $this->getRequest($this->requestUninstall, $params);
            if ($response) {
                return json_decode($response);
            }
            return false;
        } catch (Exception $exception) {
            $this->veLogger->logException($exception);
        }

        return false;
    }

    /**
     * @param string $requestAction
     * @param array $params
     * @return mixed
     */
    protected function getRequest($requestAction, $params) {
        try {
            $url = esc_url($this->requestDomain . "veconnect/" . $requestAction);
            $this->veLogger->logMessage('Start - Call WS endpoint ' . $url, 'INFO');

            $options = array(
                'method' => 'POST',
                'timeout' => $this->requestTimeout,
                'body' => $params
            );
            $response = wp_remote_post($url, $options);

            if (!is_wp_error($response) && is_array($response) && array_key_exists('body', $response)) {
                $this->veLogger->logMessage('End - Call to WS was successful', 'INFO');
                return $response['body'];
            }
            return false;

        } catch (Exception $exception) {
            $this->veLogger->logException($exception);
        }

        return false;
    }
}
