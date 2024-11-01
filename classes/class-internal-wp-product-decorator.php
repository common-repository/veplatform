<?php

class InternalWpProductDecorator {

    protected $product;

    protected $is_latest_woo_version;

    public function __construct($product_info) {
        $wc_product = is_a($product_info, 'WC_Product') || is_a($product_info, 'WC_Product_Variation') ?
            $product_info : wc_get_product($product_info);
        if (is_a($wc_product, 'WC_Product')) {
            $this->product = $wc_product;
        } else {
            $this->product = null;
        }

        $this->is_latest_woo_version = VeHelper::is_latest_woo_version();
    }

    public function __call($method, $args) {
        return call_user_func_array(array($this->product, $method), $args);
    }

    public function is_product_set() {
        return isset($this->product) && !empty($this->product);
    }

    public function get_description($is_variant = false) {
        if ($is_variant) {
            $description = $this->product->get_variation_description();

            if (empty($description) && isset($this->product->parent)) {
                $parentProduct = $this->product->parent;
                $description = $parentProduct->post->post_content;
            }

            return trim(preg_replace('/\s\s+/', ' ', strip_tags($description)));
        }

        if ($this->is_latest_woo_version){
            return $this->product->get_description();
        }

        $product_id = $this->product->get_id();
        $post = get_post($product_id);
        return $post->post_content;        
    }

    public function get_short_description($is_variant = false) {
        if ($is_variant && isset($this->product->parent)) {
            $parentProduct = $this->product->parent;
            return $parentProduct->post->post_excerpt;
        }

        if ($this->is_latest_woo_version){
            return $this->product->get_short_description();
        }

        $product_id = $this->product->get_id();
        $post = get_post($product_id);
        return $post->post_excerpt;
    }

    public function get_price_discount() {
        return min($this->product->get_variation_prices()["sale_price"]);
    }

    public function get_price_without_discount() {
        return max($this->product->get_variation_prices()["sale_price"]);
    }

    public function get_reviews_allowed() {
        if($this->is_latest_woo_version){
            return $this->product->get_reviews_allowed();
        }

        $post = get_post($this->product->get_id());
        return $post->comment_status == 'open' ? true : false;
    }

    public function get_gallery_image_ids() {
        if($this->is_latest_woo_version){
            return $this->product->get_gallery_image_ids();
        }
        return $this->product->get_gallery_attachment_ids();
    }

    public function get_title($is_variant = false) {
        $title = $this->product->get_title();
        if($is_variant && $this->is_latest_woo_version){
            $title = $this->product->get_name();
        }elseif ($is_variant){
            $attributes = wc_get_product_variation_attributes($this->product->get_id());
            foreach ($attributes as $attribute) {
                $title = $title . ' - ' . $attribute;
            }
        }

        return $title;
    }

    public function is_variation()
    {
        return $this->product->get_type() == 'variation' ? true : false;
    }
}