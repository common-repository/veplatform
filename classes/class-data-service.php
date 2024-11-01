<?php

include_once _VEPLATFORM_PLUGIN_DIR_ . '/classes/interfaces/class-data-service-interface.php';
include_once _VEPLATFORM_PLUGIN_DIR_ . '/classes/class-internal-wp-product-decorator.php';

class DataService implements DataServiceInterface { 

    private $veLogger;
    private $internalWooService;
    private $databaseService;
    const MAPPING_DURATION_METRIC = 'InitialProductSync.Module.ProductMapping.Duration';

    public function __construct($veLogger, $internalWooService, $databaseService) {
        $this->veLogger = $veLogger;
        $this->internalWooService = $internalWooService;
        $this->databaseService = $databaseService;
    }

    /**
     * Helper method to get product post objects
     *
     * @param array $filters request arguments for filtering query
     * @return array
     */
    public function getProducts($filters) {
        $query_args = $this->setQueryArguments( $filters );
        $method = $filters['method'];

        $timePreFormatting = microtime(true);

        if($method === 'initialProductSync') {
            $formattedProducts = $this->getProductsForInitialSync($query_args);
        } else if ($method === 'continuousProductSync') {
            $formattedProducts = $this->getProductsForContinuousSync($query_args['posts_per_page']);
        } else {
            return array();
        }

        $timePostFormatting = microtime(true);
        $finalTimeFormatting = $timePostFormatting - $timePreFormatting;

        $this->veLogger->trackMetric($this::MAPPING_DURATION_METRIC, $finalTimeFormatting);  

        return $formattedProducts; 
    }
    
    /**
     * Method to create a new table for product sync
     */
    public function createProductSyncTable() {
        $this->databaseService->createTable();
    }
    
    /**
     * Method to delete the table for product sync
     */
    public function deleteProductSyncTable() {
        $this->databaseService->dropTable();
    }

    /**
     * Method to insert or update a product for product sync
     * @param int $productId
     * @param bool $unmarkDeleted
     * @return void
     */
    public function storeUpdatedProduct($productId, $unmarkDeleted = false) {
        if( !isset($productId)) {
            return;
        }

        $results = $this->databaseService->selectFromTable(" WHERE id_product = $productId");

        if (!empty($results)) {
            $updateArray = array('update_time' => current_time('mysql'));
            if ($unmarkDeleted) {
                //undo mark as deleted for this product
                $updateArray = array('update_time' => current_time('mysql'), 'name_product' => null, 'url_product' => null);
            }

            $this->databaseService->updateTable($updateArray, array('id_product' => $productId));
        } else {
            $this->databaseService->insertTable(array('id_product' => $productId, 'update_time' => current_time('mysql')));        
        }
    }

     /**
     * Method to mark a product as deleted for product sync
      * @param int $productId
      * @return void
     */
    public function storeDeletedProduct($productId, $deleteChildren = true) {
        if( !isset($productId)) {
            return;
        }

        $results = $this->databaseService->selectFromTable(" WHERE id_product = $productId");
        $product = $this->internalWooService->getInternalProduct($productId);
        if (!$product->is_product_set()) {
            return;
        }

        $this->markProductAsDeleted($product, $results, $productId); 

        if ( $product->is_type( 'variable' ) && $product->has_child() && $deleteChildren) {
            foreach ( $product->get_children() as $variantId ) {
                $results = $this->databaseService->selectFromTable(" WHERE id_product = $variantId");
                $variant = $this->internalWooService->getInternalProduct($variantId);
                $this->markProductAsDeleted($variant, $results, $variantId);   
            }   
        }
    }

    private function markProductAsDeleted($product, $results, $productId) {
        if (!$product->is_product_set()) {
            return;
        }

        $is_variant = $product->is_variation();

        if (!empty($results) && !empty($product)) {
            $this->databaseService->updateTable(array(
                'update_time'  => current_time('mysql'),
                'url_product'  => $product->get_permalink(),
                'name_product' => $product->get_title($is_variant)), array('id_product' => $productId)
            );
        } else {
            $this->databaseService->insertTable(array(
                'id_product'   => $productId,
                'update_time'  => current_time('mysql'),
                'url_product'  => $product->get_permalink(),
                'name_product' => $product->get_title($is_variant))
            );        
        }
    }

    /**
     * Get all products for initial sync
     *
     * @return array
     */
    private function getProductsForInitialSync($query_args) {
        $formattedProducts = array();
        $products = $this->internalWooService->getWooProducts($query_args); 

        foreach ($products as $product) {
            $formattedProduct = $this->internalWooService->formatProductInfo($product);

            foreach($formattedProduct as $variantOrDefault) {
                $formattedProducts[] = $variantOrDefault;
            }            
        }   

        return $formattedProducts;
    }

    /**
     * Get products that were changed for continuous sync
     *
     * @return array
     */
    private function getProductsForContinuousSync($batchSize) {
        if (!is_numeric($batchSize)) {
            return array();
        }

        $formattedProducts = array();
        $productsToDelete = array();

        $updatedProductIds = $this->databaseService->selectFromTable("", " ORDER BY id_product DESC", " LIMIT $batchSize");     

        foreach ($updatedProductIds as $updatedProduct) {
            if (isset($updatedProduct['name_product']) ) {
                //deleted product
                $formattedProducts[] = array(
                    'title'          => $updatedProduct['name_product'],
                    'id'             => isset($updatedProduct['id_product']) ? $updatedProduct['id_product'] : "0",    
                    'permalink'      => isset($updatedProduct['url_product']) ? $updatedProduct['url_product'] : "default_url",
                    'currency'       => $this->internalWooService->get_currency(),
                    'is_blacklisted' => 'true'
                ); 
            } else {
                $product = $this->internalWooService->getProductInfo($updatedProduct['id_product']);
                $formattedProduct = $this->internalWooService->formatProductInfo($product);

                foreach ($formattedProduct as $variantOrDefault) {
                    if (!in_array($variantOrDefault, $formattedProducts)) {
                         $formattedProducts[] = $variantOrDefault;
                    }                    
                }  
                
            }

            $productsToDelete[] = $updatedProduct['id_product'];     
        }

        $this->databaseService->deleteFromTable($productsToDelete);

        return $formattedProducts;
    }

    /**
     * Add common request arguments to argument list before WP_Query is run
     *
     * @param array $request_args arguments provided in the request
     * @return array
     */
    private function setQueryArguments( $request_args ) {

         $default_query_args = array(
            'fields'      => 'ids',
            'post_type'   => 'product',
            'post_status' => 'publish',
            'meta_query'  => array(),
        );
        $filters = array();        

        // resources per response
        if ( !empty( $request_args['limit'] ) ) {
            $filters['posts_per_page'] = $request_args['limit'];
        }

        // resource offset
        if ( !empty( $request_args['offset'] ) ) {
            $filters['offset'] = $request_args['offset'];
        }

        $filters = $this->internalWooService->apply_woo_filters($filters);
        return array_merge( $default_query_args, $filters );
    }

}