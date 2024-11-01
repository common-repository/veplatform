<?php

include_once 'class-ve-logger.php';
include_once 'class-internal-wp-service.php';
include_once 'class-internal-wp-product-decorator.php';

class VeData {

    protected $veLogger;

    protected $empty_product;

    protected $wp_service;

    const DEFAULT_DATE_FORMAT = 'd/M/Y H:i:s';

    public function __construct(VeLogger $veLogger, InternalWpService $wp_service) {
        $this->veLogger = $veLogger;
        $this->wp_service = $wp_service;

        $this->empty_product = array(
            'productId' => null,
            'name' => null,
            'category' => null,
            'description' => null,
            'descriptionShort' => null,
            'manufacturerName' => null,
            'priceCurrent' => null,
            'priceDiscount' => null,
            'priceWithoutDiscount' => null,
            'productLink' => null,
            'images' => array(
                'partialImagePath' => null,
                'fullImagePath' => null
                )
        );
    }

    public function getMasterData() {
        $data = array();

        try {
		    error_reporting(0);
            $data['currency'] = $this->getCurrency();
            $data['language'] = $this->getLanguage();
            $data['culture'] = $this->getCulture();
            $data['user'] = $this->getUser();
            $data['currentPage'] = $this->getCurrentPage();
            $data['cart'] = $this->getCart();
            $data['history'] = $this->getHistory();

        } catch (Exception $exception) {
            $this->veLogger->logException($exception);
        }

        return $data;
    }

    public function getCurrency() {
        $currency = array(
            'isoCode' => null,
            'isoCodeNum' => null,
            'name' => null,
            'sign' => null
        );

        try {
            $isoCode = get_woocommerce_currency();

            if (isset($isoCode)) {
                $currency = array(
                    'isoCode' => $isoCode,
                    'isoCodeNum' => null,
                    'name' => get_woocommerce_currencies()[$isoCode],
                    'sign' => $this->formatSymbol()
                );
            }
            return $currency;
        } catch (Exception $exception) {
            $this->veLogger->logException($exception);
        }

        return $currency;
    }

    public function getLanguage() {
        $language = array(
            'isoCode' => null,
            'languageCode' => null,
            'name' => null
        );

        try {
            $languageCode = get_locale();
            if (isset($languageCode)) {
                $language = array(
                    'isoCode' => substr($languageCode, 0, 2),
                    'languageCode' => $languageCode,
                    'name' => null
                );

                return $language;
            }
        } catch (Exception $exception) {
            $this->veLogger->logException($exception);
        }

        return $language;
    }

    public function getCulture() {

        $date_format_full = array(
            'dateFormatFull' => self::DEFAULT_DATE_FORMAT
        );

        try {
            $date_format = $this->wp_service->get_wp_option('date_format');
            $time_format = $this->wp_service->get_wp_option('time_format');

            // contains special  chars
            if(strpos($date_format, '\\') || strpos($time_format, '\\')){
                return $date_format_full;
            }

            // day
            $dayNumberReplace = array('j', 'l', 'N');
            $dayWordReplace = array('L');

            $dateParsed = str_replace($dayNumberReplace, 'd', $date_format);
            $dateParsed = str_replace($dayWordReplace, 'D', $dateParsed);

            // month
            $monthNumberReplace = array('m', 'n');
            $monthWordReplace = array('F', 'M');

            $dateParsed = str_replace($monthNumberReplace, 'm', $dateParsed);
            $dateParsed = str_replace($monthWordReplace, 'M', $dateParsed);

            // year
            $yearNumberReplace = array('o');
            $dateParsed = str_replace($yearNumberReplace, 'Y', $dateParsed);

            // remove unwanted
            $replaceEmptyDate = array('S', 'w', 'z', 'W', 't', 'L');
            $dateParsed = str_replace($replaceEmptyDate, '', $dateParsed);

            // hour
            $hourReplaceFormat12 = array('g');
            $hourReplaceFormat24 = array('G');

            $timeParsed = str_replace($hourReplaceFormat12, 'h', $time_format);
            $timeParsed = str_replace($hourReplaceFormat24, 'H', $timeParsed);

            // minutes
            $minutesReplace = array('i');
            $timeParsed = str_replace($minutesReplace, 'm', $timeParsed);

            // seconds
            $secondsReplace = array('s');
            $timeParsed = str_replace($secondsReplace, 's', $timeParsed);

            // custom formats
            $timeParsed = str_replace('c', 'yyyy-mm-dd hh:mm:ss', $timeParsed);
            $timeParsed = str_replace('r', 'D, d M yyyy hh:mm:ss', $timeParsed);

            // remove unwanted
            $replaceEmptyTime = array('a', 'A', 'B', 'u', 'e', 'i', 'I', 'O', 'P', 'T', 'Z', 'U');
            $timeParsed = str_replace($replaceEmptyTime, '', $timeParsed);

            $date_format_full['dateFormatFull'] = trim($dateParsed . ' ' . $timeParsed);

        } catch (Exception $exception) {
            $this->veLogger->logException($exception);
        }

        return $date_format_full;
    }

    public function getUser() {
        $currentUser = null;

        try {
            $user = wp_get_current_user();
            $userLogged = is_user_logged_in();
            if (isset($user) && isset($userLogged)) {
                $currentUser = array(
                    'email' => $userLogged ? $user->user_email : null,
                    'firstName' => $userLogged ? $user->user_firstname : null,
                    'lastName' => $userLogged ? $user->user_lastname : null
                );

                return $currentUser;
            }
        } catch (Exception $exception) {
            $this->veLogger->logException($exception);
        }

        return array(
            'email' => null,
            'firstName' => null,
            'lastName' => null
        );
    }

    public function getCurrentPage() {
        try {
            $post = get_post();
            $post_id = $post->ID;
            if (!isset($_SERVER["REQUEST_SCHEME"]) && !isset($_SERVER["HTTP_HOST"]) && !isset($_SERVER["REQUEST_URI"])) {
                $pageUrl = get_permalink($post);
            } else {
                $pageUrl = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
            }

            $currentPage = null;

            if (isset($post)) {
                $currentPage = array(
                    'currentUrl' => $pageUrl,
                    'currentPageType' => $this->getCurrentPageType(),
                    'orderId' => is_wc_endpoint_url('order-received') ? $GLOBALS['order-received'] : null,
                    'product' => $this->empty_product
                );

                switch ($currentPage['currentPageType']) {
                    case 'product':
                        $currentPage['product'] = $this->getProductInformation($post_id);
                        $this->setHistory($post_id, 'product');
                        break;
                    case 'category':
                        $cate = get_queried_object();
                        $this->setHistory($cate->term_id, 'category');
                        break;
                    default:
                        break;
                }
            } else {
                $currentPage = array(
                    'currentUrl' => null,
                    'currentPageType' => null,
                    'orderId' => null,
                    'product' => $this->empty_product
                );
            }
            return $currentPage;
        } catch (Exception $exception) {
            $this->veLogger->logException($exception);
        }

        return array(
            'currentPage' => null,
            'orderId' => null,
            'currentPageType' => null,
            'product' => $this->empty_product
        );
    }

    /**
     *
     * @return string
     */
    public function getCurrentPageType() {
        try {
            if (is_shop()) {
                return 'home';
            } else if (is_product()) {
                return 'product';
            } else if (is_cart()) {
                return 'basket';
            } else if (is_wc_endpoint_url('order-received')) {
                return 'complete';
            } else if (is_checkout()) {
                return 'checkout';
            } else if (is_product_category()) {
                return 'category';
            } else if (is_account_page()) {
                return 'login';
            } else {
                return 'other';
            }
        } catch (Exception $exception) {
            $this->veLogger->logException($exception);
        }
        return 'other';
    }

    /**
     *
     * @return array
     */
    public function getCart() {
        try {
            if (is_wc_endpoint_url('order-received')) {
                return null;
            }

            $product = array();
            $wc_cart = WC()->cart;

            $i = 0;
            $total_cart = 0;
            foreach ($wc_cart->get_cart() as $cart_item_key => $cart_item) {

                $prod_subtotal = $cart_item['line_subtotal'] + $cart_item['line_subtotal_tax'];
                $prod_quantity = $cart_item['quantity'];

                $product[$i] = $this->getProductInformation(apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key));
                $product[$i]['quantity'] =  $prod_quantity;
                $product[$i]['productSubTotal'] = $this->formatSymbol() . " " . wc_format_decimal($this->convertCurrency($prod_subtotal), 2);

                $total_cart +=  $prod_subtotal;
                $i++;
            }

            $cart_update_date = null;
            $totalPromocodeDiscount = null;
            $totalProducts = null;
            $totalPrice = null;
            $total_discount = $wc_cart->discount_cart + $wc_cart->discount_cart_tax;

            if ($i > 0) {
                $cart_update_date = $this->getUpdCart();
                $totalPromocodeDiscount = $this->formatSymbol() . " " . wc_format_decimal($this->convertCurrency($wc_cart->discount_cart + $wc_cart->discount_cart_tax), 2);
                $totalProducts = $this->formatSymbol() . " " . wc_format_decimal($this->convertCurrency($total_cart - $total_discount + $wc_cart->shipping_tax_total), 2);
                $totalPrice = $this->formatSymbol() . " " . wc_format_decimal($this->convertCurrency($total_cart - $total_discount + $wc_cart->shipping_tax_total + $wc_cart->shipping_total), 2);
            }

            $promocode = $this->getPromocode();

            $cart = array(
                "products" => $product,
                "totalPrice" => $totalPrice,
                "totalProducts" => $totalProducts,
                "promocode" => $promocode,
                "taxes" => $this->getTaxes($wc_cart),
                "totalPromocodeDiscount" => $totalPromocodeDiscount,
                "dateUpd" => $cart_update_date,
                "nProducts" => $wc_cart->get_cart_contents_count()
            );
            return $cart;

        } catch (Exception $exception) {

            $this->veLogger->logException($exception);
        }

        return array(
            'products' => array(),
            'subTotal' => null,
            'totalPrice' => null,
            'totalProducts' => null,
            'promocode' => null,
            'taxes' => array(),
            'totalPromocodeDiscount' => null,
            'dateUpd' => null
        );
    }

    public function getUpdCart() {
        return date('Y-m-d H:i:s', time());
    }

    public function getTaxes($wc_cart = null) {
        $taxes = array();
        try {
            $cart = isset($wc_cart) ? WC()->cart : $wc_cart;

            // tax_total
            if (isset($cart->tax_total) && $cart->tax_total > 0) {
                $feeElement = array(
                    'name' => 'tax_total',
                    'value' => $this->formatSymbol() . " " . wc_format_decimal($this->convertCurrency($cart->tax_total), 2)
                );
                array_push($taxes, $feeElement);
            }

            // discount_cart_tax
            if (isset($cart->shipping_tax_total) && $cart->shipping_tax_total > 0) {
                $feeElement = array(
                    'name' => 'shipping_tax_total',
                    'value' => $this->formatSymbol() . " " . wc_format_decimal($this->convertCurrency($cart->shipping_tax_total), 2)
                );
                array_push($taxes, $feeElement);
            }
        } catch (Exception $exception) {
            $this->veLogger->logException($exception);
        }

        return $taxes;
    }

    public function convertCurrency($value) {
        try {
            global $WOOCS;
            if (isset($WOOCS)) {
                $currencies = $WOOCS->get_currencies();
                $value = $value * $currencies[$WOOCS->current_currency]['rate'];
            }
        } catch (Exception $exception) {
            $this->veLogger->logException($exception);
        }

        return $value;
    }

    public function getPromocode() {

        $coupon = isset($_SESSION["coupon"]) ? $_SESSION["coupon"] : null;
        $promocode = array(
            'code' => null,
            'name' => null,
            'type' => null,
            'value' => null
        );

        try {
            if (isset($coupon) && $coupon != "reset") {
                if (!empty($coupon->code)) {
                    $promocode = array(
                        'code' => $coupon->code,
                        'name' => null,
                        'type' => $coupon->discount_type,
                        'value' => $coupon->discount_type == 'fixed_cart' ? $this->formatSymbol() . " " . wc_format_decimal($this->convertCurrency($coupon->coupon_amount), 2) : $coupon->coupon_amount,
                    );
                }
            }
        } catch (Exception $exception) {
            $this->veLogger->logException($exception);
        }

        return $promocode;
    }

    /**
     * Get Product information by productId
     *
     * @param WC_Product|int $product_info
     *
     * @return array
     */
    public function getProductInformation($product_info) {

        try {
            if (!isset($product_info)) {
               return $this->empty_product;
            }

            $current_product = InternalWPProductFactory::create_internal_product($product_info);
            if(!$current_product->is_product_set()) {
                return $this->empty_product;
            }

            if (isset($current_product)) {
                $product_id = $current_product->get_id();
                $product_sku = $current_product->get_sku();

                $full_image_path = get_the_post_thumbnail_url($product_id,'full');
                $full_image_path = $this->check_full_image_path($full_image_path);
                $images = array(
                    'partialImagePath' => null,
                    'fullImagePath' => $full_image_path ? $full_image_path : null
                );

                if ($current_product->get_type() == "variable") {
                    $product_price = $product_price_discount = $current_product->get_price_discount();
                    $product_price_without_discount = $current_product->get_price_without_discount();
                } else {
                    $product_price_without_discount = $current_product->get_regular_price();
                    $product_price = $current_product->get_price();
                    $product_price_discount = $product_price_without_discount - $product_price;
                }

                return array(
                    'productId' => isset($product_sku) && !empty($product_sku) ? $product_sku : $product_id,
                    'name' => $this->cleanStr($current_product->get_title()),
                    'category' => $this->cleanStr($this->getCategoryString($product_id)),
                    'description' => $this->cleanStr($current_product->get_description()),
                    'descriptionShort' => $this->cleanStr($current_product->get_short_description()),
                    'manufacturerName' => null,
                    'priceCurrent' => $this->formatSymbol() . ' ' . wc_format_decimal($this->convertCurrency
                        ($product_price), 2),
                    'priceDiscount' => $this->formatSymbol() . ' ' . wc_format_decimal($this->convertCurrency
                        ($product_price_discount), 2),
                    'priceWithoutDiscount' => $this->formatSymbol() . ' ' . wc_format_decimal($this->convertCurrency
                        ($product_price_without_discount), 2),
                    'productLink' => $current_product->get_permalink(),
                    'images' => (isset($images['fullImagePath'])) ? $images : array('partialImagePath' => null, 'fullImagePath'
                    => null)
                );
            }
        } catch (Exception $exception) {
            $this->veLogger->logException($exception);
        }

        return $this->empty_product;
    }

    public function formatSymbol() {
        return html_entity_decode(get_woocommerce_currency_symbol());
    }

    public function getCategoryString($postID) {
        $category = null;
        try {
            $catObjs = get_the_terms($postID, 'product_cat');
            if (count($catObjs) > 0) {
                $category = $catObjs[count($catObjs) - 1]->name;
            }

        } catch (Exception $exception) {
            $this->veLogger->logException($exception);
        }
        return $category;
    }

    public function getHistory() {
        $history = array(
            'lastVisitedCategory' => array(
                'name' => null,
                'link' => null),
            'productHistory' => array());

        try {
            if (array_key_exists('vePlatformHistory', $_SESSION)) {
                foreach ($_SESSION['vePlatformHistory']['product'] as $post_id) {
                    $history['productHistory'][] = $this->getProductInformation($post_id);
                }
                if ($_SESSION['vePlatformHistory']['category'] !== "") {
                    $cat_id = $_SESSION['vePlatformHistory']['category'];
          		    if( isset($cat_id)) {
          				 $category = get_category($cat_id);
          				 $history['lastVisitedCategory']['name'] = $this->cleanStr($category->name);
          				 $history['lastVisitedCategory']['link'] = get_category_link($cat_id);
          		    }
                }
            }
        } catch (Exception $exception) {
            $this->veLogger->logException($exception);
        }

        return $history;
    }

    public function setHistory($id, $type) {
        try {
            $history = (array_key_exists('vePlatformHistory', $_SESSION)) ? $_SESSION['vePlatformHistory'] : array('category' => array('name' => null, 'link' => null), 'product' => array());
            if ($type === 'product') {
                if (!in_array($id, $history[$type])) {
                    $history[$type][] = $id;
                }
                $categories = get_the_terms($id, 'product_cat');
        		if( isset($categories) && $categories != false) {
        			$last = count($categories);
        			$history['category'] = $categories[$last - 1]->term_id;
        		}
            } else if ($type === 'category') {
                $history[$type] = $id;
            }
            $_SESSION['vePlatformHistory'] = $history;
        } catch (Exception $exception) {
            $this->veLogger->logException($exception);
        }
    }

    public function cleanStr($str) {
        if (!isset($str)) {
            return null;
        }

        try {
            $str = trim($str);
            $str = html_entity_decode($str);
            $str = strip_tags($str);
            $str = str_replace('/', ' ', $str);
            $str = str_replace('\\', ' ', $str);
            $str = str_replace('\'', ' ', $str);
            $str = str_replace('"', ' ', $str);
            $str = trim(preg_replace('/\s\s+/', ' ', $str));

            return $str;
        } catch (Exception $exception) {
            $this->veLogger->logException($exception);
        }

        return null;
    }

    /*
     * Check image contains full path, if not add it
     *
     * @param string $full_image_path
     * return string
     */
    private function check_full_image_path($full_image_path) {
        $site_url = get_site_url();
        if(strpos($full_image_path, $site_url) === false) {
            $full_image_path = $site_url . $full_image_path;
        }

        return $full_image_path;
    }
}
