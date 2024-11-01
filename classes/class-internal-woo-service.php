<?php

include_once(_VEPLATFORM_PLUGIN_DIR_ . '/classes/interfaces/class-internal-woo-interface.php');
include_once(_VEPLATFORM_PLUGIN_DIR_ . '/classes/class-internal-wp-product-factory.php');

class InternalWooService implements WooServiceInterface { 

    /**
     * @var VeLogger
     */
    protected $ve_logger;
    protected $is_latest_woo_version;

    public function __construct($ve_logger)
    {
        $this->ve_logger = $ve_logger;
        $this->is_latest_woo_version = VeHelper::is_latest_woo_version();
    }

    /**
     * Get standard products 
     *
     * @param array $query_args
     * @return array 
     */
    public function getWooProducts($query_args) {
        $wooProducts = array();
        $basicProducts = new WP_Query( $query_args );

        foreach ($basicProducts->posts as $productId) {
            $wooProducts[] = $this->getProductInfo($productId);
        }

        return $wooProducts;
    }

    /**
     * Get the product for the given ID
     *
     * @param int $id the product ID
     * @param string $fields
     * @return WC_Product|array
     */
    public function getProductInfo($productId) {
        if ( !isset($productId) ) {
            return array();
        }

        return wc_get_product($productId);        
    }

    /**
     * Get an internal product type
     *
     * @param WC_Product|int $product_info
     * @return InternalWpProductDecorator|array
     */
    public function getInternalProduct($product_info) {
        if ( !isset($product_info) ) {
            return array();
        }

        return InternalWPProductFactory::create_internal_product($product_info);
    }


    /**
     * Get standard product data that applies to every product type
     *
     * @param WC_Product|InternalWpProductDecorator $product
     * @return array
     */
    public function formatProductInfo( $product ) {
        $formattedProduct = array();

        try {
            if ( !is_a( $product, 'WC_Product' ) ) {
                return array();
            }

            //parent product with variant
            if ( $product->is_type( 'variable' ) && $product->has_child() ) {
                foreach ( $product->get_children() as $variantsId ) {
                    $variantInfo = $this->getProductInfo($variantsId);
                    $formattedProduct[] = $this->mapProductInfo( $variantInfo, true );     
                }   
            }
            else {
                //variant product
                $is_variant = $product->get_type() == 'variation' ? true : false;

                //simple product
                $formattedProduct[] = $this->mapProductInfo( $product, $is_variant );
            }            
        } catch (Exception $exception) {
            $this->ve_logger->logException($exception);
        }

        return $formattedProduct;
    }

     /**
     * Get standard product data that applies to every product type
     *
     * @param WC_Product $product, bool $is_variant
     * @return array
     */
    private function mapProductInfo($product, $is_variant = false) {
        if ( !is_a( $product, 'WC_Product' ) ) {
            return array();
        }
            
        $product = $this->getInternalProduct($product);
        if (!$product->is_product_set()) {
            return array();
        }

        $prices_decimals = wc_get_price_decimals();

        return array(
            'title'              => $product->get_title($is_variant),
            'id'                 => $product->get_id(),    
            'permalink'          => $product->get_permalink(),
            'sku'                => $product->get_sku(),
            'price'              => wc_format_decimal( $product->get_price(), $prices_decimals ),
            'regular_price'      => wc_format_decimal( $product->get_regular_price(), $prices_decimals ),
            'sale_price'         => $product->get_sale_price() ? wc_format_decimal( $product->get_sale_price(), $prices_decimals ) : null,
            'managing_stock'     => $product->managing_stock(),
            'stock_quantity'     => $product->get_stock_quantity(),
            'in_stock'           => $product->is_in_stock(),           
            'on_sale'            => $product->is_on_sale(),
            'product_url'        => $product->get_type() == 'external' ? $product->get_permalink() : '',   
            'shipping_required'  => $product->needs_shipping(),
            'shipping_taxable'   => $product->is_shipping_taxable(),
            'description'        => $product->get_description($is_variant),
            'short_description'  => $product->get_short_description($is_variant),
            'reviews_allowed'    => $product->get_reviews_allowed(),
            'average_rating'     => wc_format_decimal( $product->get_average_rating(), 2 ),
            'rating_count'       => $product->get_rating_count(),        
            'categories'         => $this->setCategories($product, $is_variant),
            'images'             => $this->setProductImages( $product ),
            'currency'           => get_option('woocommerce_currency'),
            'currency_symbol'    => get_woocommerce_currency_symbol(),
            'currency_position'  => get_option('woocommerce_currency_pos'),
            'culture_code'       => substr(get_locale(), 0, 2),
            'shipping_amount'    => $this->getShippingAmount($product),
            'decimal_separator'  => wc_get_price_decimal_separator(),
            'thousand_separator' => wc_get_price_thousand_separator(),
            'price_decimals'     => $prices_decimals,
            'store_domain'       => get_site_url(),
            'is_blacklisted'     => 'false'
        );   
    }

    /**
     * Get categories for a product
     *
     * @param WC_Product $product, bool $is_variant
     * @return array
     */
    private function setCategories($product, $is_variant) {
        try {
            $emptyCategory = array(''=>'');

            if ( !isset($product) ) {
                return $emptyCategory;
            }

            $categories = array();
            $terms = wc_get_product_terms( $is_variant ? $product->get_parent_id() : $product->get_id(), 'product_cat');

            foreach ( $terms as $category ) {
                $categories[] = $category->to_array();
            };

            if(empty($categories)) {
                return $emptyCategory;
            }

            $categoryTree = $this->buildTree($categories, 'parent', 'term_id');

            return $this->formatCategory(empty($categoryTree) ? $emptyCategory : $categoryTree[0]);
        } catch (Exception $exception) {
            $this->ve_logger->logException($exception);
        }    

        return $emptyCategory;          
    }

    /**
     * Format categories for a product
     *
     * @param WC_Term $category
     * @return array(string, string)
     */
    private function formatCategory($category) {
        if(!isset($category)) {
            return array(''=>'');
        }

        $productCategory = $category['name'];
        $path = $category['name'];
        while(!empty($category['children'])) {
            $child = $category['children'][0];
            $path = $path . ',' . $child['name'];
            $category = $category['children'][0];
            $productCategory = $child['name'];
        }

        return array($productCategory => $path);
    } 

    /**
     * Create a hierarhical tree from an array
     *
     * @param array $flat, string $parentIdKey, string $idKey
     * @return array
     */
    private function buildTree($flat, $parentIdKey, $idKey = null)
    {
        if(empty($flat)) {
            return array();
        }

        $grouped = array();
        foreach ($flat as $sub){
            $grouped[$sub[$parentIdKey]][] = $sub;
        }

        $fnBuilder = function($siblings) use (&$fnBuilder, $grouped, $idKey) {
            foreach ($siblings as $k => $sibling) {
                $id = $sibling[$idKey];
                if(isset($grouped[$id])) {
                    $sibling['children'] = $fnBuilder($grouped[$id]);
                }
                $siblings[$k] = $sibling;
            }

            return $siblings;
        };

        return $fnBuilder($grouped[0]);
    }

    /**
     * Get shipping amount for the specific product
     *
     * @param WC_Product|int $product
     * @return string
     */
    private function getShippingAmount($product) {
        try {
            if ( !isset($product) ) {
                return null;
            }

            $shipping_class_id = $product->get_shipping_class_id();
            $shipping_class= $product->get_shipping_class();
            $fee = 0;

            if ($shipping_class_id) {
                $flat_rates = get_option('woocommerce_flat_rates');
                $fee = $flat_rates[$shipping_class]['cost'];
            }

            $flat_rate_settings = get_option('woocommerce_flat_rate_settings');
            return $flat_rate_settings['cost_per_order'] + $fee;   
        } catch (Exception $exception) {
            $this->ve_logger->logException($exception);
            return null;
        }    
    }

    /**
     * Get the images for a product or product variation
     *
     * @param InternalWpProductDecorator $product
     * @return array
     */
    private function setProductImages( $product ) {
        $images        = $attachment_ids = array();
        $product_image = $product->get_image_id();

        if ( ! empty( $product_image ) ) {
            $attachment_ids[] = $product_image;
        }

        $attachment_ids = array_merge( $attachment_ids, $product->get_gallery_image_ids() );

        foreach ( $attachment_ids as $position => $attachment_id ) {
            $attachment_post = get_post( $attachment_id );
            if ( !isset( $attachment_post ) ) {
                continue;
            }
            $attachment = wp_get_attachment_image_src( $attachment_id, 'full' );
            if ( ! is_array( $attachment ) ) {
                continue;
            }
            $images[] = array(
                'src'        => current( $attachment ),
            );
        }

        // Set a placeholder image if the product has no images set.
        if ( empty( $images ) ) {
            $images[] = array(
                'src'        => wc_placeholder_img_src(),
            );
        }

        return $images;
    }

    /**
     * Apply WooCommerce query filters
     *
     * @param array $filters
     * @return array
     */
    public function apply_woo_filters($filters) {
        return apply_filters( 'woocommerce_api_query_args', $filters );
    }

    /**
     * Get WooCommerce currency
     *
     * @return string
     */
    public function get_currency() {
        return get_option('woocommerce_currency');
    }

}