<?php

include_once(_VEPLATFORM_PLUGIN_DIR_ . '/classes/interfaces/class-ve-logger-interface.php');

class VeLogger implements VeLoggerInterface {
    private $telemetryClient;
    private $wp_version;
    private $woocommerce_version;

    public function __construct() {
        try {
            global $wp_version, $woocommerce;
            $this->wp_version = $wp_version;

            if (isset($woocommerce)) {
                $this->woocommerce_version = $woocommerce->version;
            } else {
                $this->woocommerce_version = "";
            }

            $this->telemetryClient = new \ApplicationInsights\Telemetry_Client();
            $this->telemetryClient->getContext()->setInstrumentationKey('35a3d3fa-b65d-440f-be92-4330255f523d');

        } catch (Exception $ex) {
            $this->log_to_file($ex, 'VeLogger ctor');
        }
    }

    public function logMessage($message, $level = 'INFO') {
        try {
            if (isset($message)) {
                $this->telemetryClient->trackMessage($message, $this->getEnvInfo($level));
                $this->telemetryClient->flush();

            }
        } catch (Exception $ex) {
            $this->log_to_file($ex, 'logMessage method');
        }
    }

    public function logException(\Exception $exception) {
        try {
            if (isset($exception)) {
                $this->telemetryClient->trackException($exception, $this->getEnvInfo('ERROR'));
                $this->telemetryClient->flush();
            }
        } catch (Exception $ex) {
            $this->log_to_file($ex, 'logException method');
        }
    }

    public function trackMetric($name, $value, $forceFlush = true)
    {
        try {
            if (isset($name) && isset($value) && isset($this->telemetryClient)) {
                $this->telemetryClient->trackMetric(
                    $name,
                    $value,
                    null,
                    null,
                    null,
                    null,
                    null,
                    $this->getEnvInfo('INFO')
                );

                if ($forceFlush) {
                    $this->telemetryClient->flush();
                }
            }
        } catch (Exception $ex) {
            $this->log_to_file($ex, 'trackMetric method');
        }
    }

    private function getEnvInfo($level) {
        return array(
            'Level' => $level,
            'Shop' => 'WooCommerce',
            'WooCommerce_Version' => $this->woocommerce_version,
            'WordPress_Version' => $this->wp_version,
            'URL' => $this->getCurrentUrl(),
            'PHP_Version' => phpversion(),
            'Module_Version' => VE_MODULE_VERSION
        );
    }

    private function getCurrentUrl() {
        $url = home_url( add_query_arg( NULL, NULL ) );

        return isset($url) ? $url : '';
    }

    private function log_to_file($exception, $message) {
        $message = $exception->getMessage() . ' caught in ' . $message;

        $plugin_dir_path = dirname(plugin_dir_path(__FILE__));
        $file = $plugin_dir_path . '/veplatform.log';
        if (is_writable($file) || (!file_exists($file) && is_writable($plugin_dir_path))) {
            $formatted_message = '*ERROR* ' . "\t" . date('Y/m/d - H:i:s') . ': ' . $message . "\r\n";
            file_put_contents($file, $formatted_message, FILE_APPEND);
        }
    }
}
