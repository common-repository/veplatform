<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

include_once(_VEPLATFORM_PLUGIN_DIR_ . '/classes/interfaces/class-internal-db-interface.php');
$upgrade_file = ABSPATH . 'wp-admin/includes/upgrade.php';
if(file_exists($upgrade_file)){
    require_once $upgrade_file;
}

class DatabaseService implements DatabaseServiceInterface { 
    
    private $wpdbInstance;
    private $tableName;
    const PRODUCT_SYNC_TABLE_NAME = 've_product_sync';

    public function __construct() {
        global $wpdb;
        $this->wpdbInstance = $wpdb;
        $this->tableName = $this->wpdbInstance->prefix . self::PRODUCT_SYNC_TABLE_NAME;
    }

    public function createTable() {
        $charset_collate = $this->wpdbInstance->get_charset_collate();

        $sql = "CREATE TABLE $this->tableName (
            id_product mediumint(10) NOT NULL,
            update_time datetime,
            url_product varchar(255),
            name_product varchar(100),
            PRIMARY KEY  (id_product)
        ) $charset_collate;";

        dbDelta( $sql );
    }

    public function dropTable() {
        $sql = "DROP TABLE IF EXISTS $this->tableName;";
        $this->wpdbInstance->query($sql);
    }

    public function updateTable($updateArray, $updateCondition) {
        $this->wpdbInstance->update($this->tableName, $updateArray, $updateCondition);
    }

    public function insertTable($insertArray) {
        $this->wpdbInstance->insert($this->tableName, $insertArray);
    }

    public function selectFromTable($whereCondition = "", $orderByCondition = "", $limitCondition = "") {
        $sql = "SELECT id_product, url_product, name_product FROM $this->tableName" . $whereCondition . $orderByCondition . $limitCondition;
        return $this->wpdbInstance->get_results($sql, 'ARRAY_A');
    }

    public function deleteFromTable($deleteArray) {
        if(empty($deleteArray)) {
            return;
        }

        $deleteArray = implode($deleteArray, ', ');
        $sql = "DELETE FROM $this->tableName WHERE id_product IN($deleteArray);";
        $this->wpdbInstance->query($sql);
    }
}    
