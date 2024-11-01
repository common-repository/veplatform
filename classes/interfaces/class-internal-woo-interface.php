<?php
interface WooServiceInterface
{
    public function getWooProducts($query_args);
    public function formatProductInfo($product);
    public function getProductInfo($productId);
    public function apply_woo_filters($filters);
    public function get_currency();
}