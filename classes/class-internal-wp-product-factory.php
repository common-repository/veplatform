<?php

class InternalWPProductFactory {

    /**
     * Creates an internal product
     *
     * @param WC_Product|int $product_info
     * @return InternalWpProductDecorator
     */
    public static function create_internal_product($product_info) {
        return new InternalWpProductDecorator($product_info);
    }

}