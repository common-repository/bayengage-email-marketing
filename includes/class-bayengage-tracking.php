<?php

global $session;

@ini_set("session.cookie_httponly", false);
@ini_set("session.cookie_secure", false);
@ini_set("session.use_only_cookies", false);

/*if (!isset($_SESSION)) {
    @session_start(['read_and_close' => true]);
}*/

/**
 * BayEngage Tracking BayEngage_Tracking.
 *
 * @since   0.1.0
 * @package BayEngage_Tracking
 */

/**
 * BayEngage Tracking BayEngage_Tracking.
 *
 * @since 0.1.0
 */

class WC_BayEngage_Tracking
{
    /**
     * Parent plugin class.
     *
     * @since 0.1.0
     *
     * @var   BayEngage_Tracking
     */

    protected $userName;

    protected $userMail;

    protected $userId;

    protected $sessionId;

    protected $bePath;

    /**
     * Constructor.
     *
     * @since  0.1.0
     *
     */
    public function __construct()
    {
        $current_user = wp_get_current_user();
        $this->userName = $current_user->user_login;
        $this->userMail = $current_user->user_email;
        $this->userId = $current_user->ID;
        $this->sessionId = $current_user->ID;

        $connected = get_option('BEM_CONNECTION_STATUS');

        // proceed for registered users only
        if( $connected == 1 && !is_admin() ){

            if( isset($_COOKIE) && isset($_COOKIE['cart']) ){
                $this->sessionId = $_COOKIE['cart'];
            }else{

                if(!empty($_SESSION['woo_registered_user_id'])){
                    $this->sessionId = \md5( $_SESSION['woo_registered_user_id'] );
                }else{
                    $_SESSION['woo_registered_user_id'] = $this->sessionId . '_woo_registered_user_' . uniqid();
                    $this->sessionId = \md5( $this->sessionId . '_woo_registered_user_' . uniqid() );
                }

                /* if( null !== WC()->session && null !== WC()->session->get( 'woo_registered_user_id' ) ) {
                    $this->sessionId = \md5( WC()->session->get( 'woo_registered_user_id' ) );
                }else{

                    WC()->session->set( 'woo_registered_user_id',  $this->sessionId . '_woo_registered_user_' . uniqid() );
                    $this->sessionId = \md5( $this->sessionId . '_woo_registered_user_' . uniqid() );
                } */
            }

            $this->hooks();

            // Cart products preload.
            $this->wc_cart_preload();
        }
    }

    /**
     * Initiate our hooks.
     *
     * @since  0.1.0
     */
    public function hooks()
    {
        try {
            add_action('woocommerce_add_to_cart', array($this, 'be_action_woocommerce_add_to_cart'));

            add_action('woocommerce_ajax_added_to_cart', array($this, 'be_action_woocommerce_add_to_cart'));
            //add_action('woocommerce_add_to_cart_redirect', array($this, 'be_action_woocommerce_add_to_cart'), 10, 2);

            add_action('woocommerce_update_cart_action_cart_updated', array($this, 'be_action_woocommerce_update_to_cart'));
            add_action('woocommerce_cart_item_removed', array($this, 'be_action_woocommerce_cart_item_removed'));
            add_action('woocommerce_before_checkout_form', array($this, 'be_action_woocommerce_checkout_after_customer_details'));
            // enable for single page checkout only
            add_action( 'woocommerce_checkout_after_customer_details', array($this, 'be_action_woocommerce_checkout_after_customer_details'));

            //After billing form details submit
            add_action( 'woocommerce_after_checkout_billing_form', array($this, 'be_action_woocommerce_checkout_after_customer_details'));

            //add_action('woocommerce_new_order', array($this, 'be_action_woocommerce_new_order'), 10, 2);
            add_action( 'wp_loaded', array($this,'woocommerce_add_multiple_products_to_cart') );
        } catch (\Exception $e) {
            $errorMsg = ', Message: ' . $e->getMessage();
            $errorMsg .= ', Line: ' . $e->getLine();
        }
    }

    /**
     * To create new user from single page checkout.
     */
    public function be_action_woocommerce_checkout_after_customer_details()
    {
        $logger = wc_get_logger();
        $logger->debug( 'bayengage checkout after details');
        // prepare data to send
        $dataList['shop_url'] = get_home_url();
        $dataList['action_webhook'] = 'action.woocommerce_checkout_after_customer_details';
        $dataList['session_id'] = $this->sessionId;
        $dataList['email'] = $this->userMail;
        $dataList['user_id'] = $this->userId ?? null;
        $dataList['first_name'] = '';
        $dataList['last_name'] = '';
        $dataList['role'] = 'customer';
        $dataList['id'] = $this->userId ?? null;

        $tenantUuid = get_option('BEM_CONNECTION_USER_ID');

        if (BAYENGAGE_ENVIRONMENT === 'LIVE'){
            $baseUrl = BAYENGAGE_LIVE_WEBHOOK_URL.$tenantUuid;
        }else{
            $baseUrl = BAYENGAGE_DEV_WEBHOOK_URL.$tenantUuid;
        }

        // set data in cookie
        @setcookie('be_cookie_shop_url', $dataList['shop_url']);
        @setcookie('be_cookie_action_webhook', $dataList['action_webhook']);
        @setcookie('be_cookie_session_id', $dataList['session_id']);
        @setcookie('be_cookie_email', $dataList['email']);
        @setcookie('be_cookie_user_id', $dataList['user_id']);
        @setcookie('be_cookie_first_name', $dataList['first_name']);
        @setcookie('be_cookie_last_name', $dataList['last_name']);
        @setcookie('be_cookie_role', $dataList['role']);
        @setcookie('be_base_url', $baseUrl);
        @setcookie('be_id', $dataList['id']);

    }

    /**
     * @param $cartItem
     * @param $cartItemKey
     */
    public function be_action_woocommerce_add_to_cart()
    {

        $logger = wc_get_logger();
        $logger->debug( 'bayengage add to cart' );

        try {
            $dataList = [];
            $arr = [];
            $cartTotal = 0;
            if (count(WC()->cart->get_cart()) > 0) {

                $dataList['shop_url'] = get_home_url();
                $dataList['action_webhook'] = 'action.woocommerce_add_to_cart';
                foreach (WC()->cart->get_cart() as $cart_item) {

                    $productId = $this->getProductId($cart_item);
                    // product details
                    $productDetails = wc_get_product($cart_item['data']->get_id());
                    $productCats = wp_get_post_terms($cart_item['data']->get_id(), 'product_cat', array('fields' => 'names'));
                    $imgDetails = get_the_post_thumbnail_url($cart_item['product_id']);
                    $dataListCart['order_id'] = $cart_item['key'];
                    $dataListCart['product_id'] = $productId;
                    $dataListCart['product_sku'] = $productDetails->get_sku();
                    $dataListCart['product_name'] = $productDetails->get_title();
                    $dataListCart['quantity'] = $cart_item['quantity'];
                    $dataListCart['price'] = $cart_item['data']->price;
                    $dataListCart['total_price'] = $dataListCart['quantity'] * $dataListCart['price'];
                    $cartTotal = $cartTotal + $dataListCart['total_price'];
                    $dataListCart['special_price'] = $productDetails->get_sale_price();
                    $dataListCart['productimg'] = $imgDetails;
                    $dataListCart['category_name'] = implode(',', $productCats);
                    $dataListCart['category'] = '';
                    $dataListCart['page_url'] = $productDetails->get_permalink();
                    $dataListCart['product_type'] = $productDetails->get_type();
                    $arr[] = $dataListCart;
                }
            }

            if (isset($_COOKIE['__bay_email'])) {
                $dataList['be_user_email'] = $_COOKIE['__bay_email'];
            }
            $dataList['user_id'] = $this->userId;
            $dataList['session_id'] = $this->sessionId;
            $dataList['user_name'] = $this->userName;
            $dataList['user_mail'] = $this->userMail;
            $dataList['cart_items'] = $arr;

            setcookie('cart', $this->sessionId, time() + 60 * 60 * 24 * 30, '/');
            setcookie('cart_total', $cartTotal, time() + 60 * 60 * 24 * 30, '/');
            $this->be_send_action_data($dataList);
        } catch (\Exception $e) {
            $errorMsg = ', Message: ' . $e->getMessage();
            $errorMsg .= ', Line: ' . $e->getLine();
        }
    }

    /**
     * Update cart event tracking.
     */
    public function be_action_woocommerce_update_to_cart()
    {

        $logger = wc_get_logger();
        $logger->debug( 'bayengage update cart' );

        $dataList = [];
        $arr = [];
        $cartTotal = 0;
        try {
            if (isset($_REQUEST['cart']) && count($_REQUEST['cart']) > 0) {
                foreach ($_REQUEST['cart'] as $key => $value) {
                    if (count(WC()->cart->get_cart()) > 0) {

                        $dataList['shop_url'] = get_home_url();
                        $dataList['action_webhook'] = 'action.woocommerce_update_cart_action_cart_updated';
                        foreach (WC()->cart->get_cart() as $cart_item) {

                            $productId = $this->getProductId($cart_item);
                            // product details
                            if ($cart_item['key'] === $key) {
                                $productDetails = wc_get_product($cart_item['data']->get_id());
                                $productCats = wp_get_post_terms($cart_item['data']->get_id(), 'product_cat', array('fields' => 'names'));
                                $imgDetails = get_the_post_thumbnail_url($cart_item['product_id']);
                                $dataListCart['order_id'] = $cart_item['key'];
                                $dataListCart['product_id'] = $productId;
                                $dataListCart['product_sku'] = $productDetails->get_sku();
                                $dataListCart['product_name'] = $productDetails->get_title();
                                $dataListCart['quantity'] = $cart_item['quantity'];
                                $dataListCart['price'] = $cart_item['data']->price;
                                $dataListCart['total_price'] = $dataListCart['quantity'] * $dataListCart['price'];
                                $cartTotal = $cartTotal + $dataListCart['total_price'];
                                $dataListCart['special_price'] = $productDetails->get_sale_price();
                                $dataListCart['productimg'] = $imgDetails;
                                $dataListCart['category_name'] = implode(',', $productCats);
                                $dataListCart['category'] = '';
                                $dataListCart['page_url'] = $productDetails->get_permalink();
                                $dataListCart['product_type'] = $productDetails->get_type();
                                $arr[] = $dataListCart;
                            }
                        }
                    }
                }
                if (isset($_COOKIE['__bay_email'])) {
                    $dataList['be_user_email'] = $_COOKIE['__bay_email'];
                }
                $dataList['user_id'] = $this->userId;
                $dataList['session_id'] = $this->sessionId;
                $dataList['user_name'] = $this->userName;
                $dataList['user_mail'] = $this->userMail;
                $dataList['cart_items'] = $arr;

                setcookie('cart', $this->sessionId, time() + 60 * 60 * 24 * 30, '/');
                setcookie('cart_total', $cartTotal, time() + 60 * 60 * 24 * 30, '/');
                $this->be_send_action_data($dataList);
            }
        } catch (\Exception $e) {
            $errorMsg = ', Message: ' . $e->getMessage();
            $errorMsg .= ', Line: ' . $e->getLine();
        }
    }

    /**
     * Remove cart item event.
     *
     * @param $cart_item_key
     * @param $cartItemKey
     */
    public function be_action_woocommerce_cart_item_removed()
    {

        $logger = wc_get_logger();
        $logger->debug( 'bayengage remove cart' );

        try {
            $dataList = [];
            $cartData = [];
            $cartTotal = 0;
            $dataList['shop_url'] = get_home_url();
            $dataList['action_webhook'] = 'action.woocommerce_cart_item_removed';
            if (count(WC()->cart->get_cart()) > 0) {
                foreach (WC()->cart->get_cart() as $cart_item) {

                    $productId = $this->getProductId($cart_item);
                    // product details
                    $productDetails = wc_get_product($cart_item['data']->get_id());
                    $productCats = wp_get_post_terms($cart_item['data']->get_id(), 'product_cat', array('fields' => 'names'));
                    $imgDetails = get_the_post_thumbnail_url($cart_item['product_id']);
                    $dataListCart['order_id'] = $cart_item['key'];
                    $dataListCart['product_id'] = $productId;
                    $dataListCart['product_sku'] = $productDetails->get_sku();
                    $dataListCart['product_name'] = $productDetails->get_title();
                    $dataListCart['quantity'] = $cart_item['quantity'];
                    $dataListCart['price'] = $productDetails->get_price();
                    $dataListCart['total_price'] = (int)$dataListCart['quantity'] * floatval($dataListCart['price']);
                    $cartTotal = $cartTotal + $dataListCart['total_price'];
                    $dataListCart['special_price'] = $productDetails->get_sale_price();
                    $dataListCart['productimg'] = $imgDetails;
                    $dataListCart['category_name'] = implode(',', $productCats);
                    $dataListCart['category'] = '';
                    $dataListCart['page_url'] = $productDetails->get_permalink();
                    $dataListCart['product_type'] = $productDetails->get_type();
                    $cartData[] = $dataListCart;

                }
            }
            if(count($cartData) > 0) {
                $dataList['user_id'] = $this->userId;
                $dataList['session_id'] = $this->sessionId;
                $dataList['user_name'] = $this->userName;
                $dataList['user_mail'] = $this->userMail;
                $dataList['cart_items'] = $cartData;


                @setcookie('cart', $this->sessionId, time() + 60 * 60 * 24 * 30, '/', '', false, false);
                @setcookie('cart_total', $cartTotal, time() + 60 * 60 * 24 * 30, '/', '', false, false);

                $this->be_send_action_data($dataList);
            }

        } catch (\Exception $e) {
            $errorMsg = ', Message: ' . $e->getMessage();
            $errorMsg .= ', Line: ' . $e->getLine();
        }
    }

    /**
     * Remove cookie and session on new order
     */
    public function be_action_woocommerce_new_order() {

        $logger = wc_get_logger();
        $logger->debug( 'bayengage order placed' );

        $dataList['shop_url'] = get_home_url();
        $dataList['action_webhook'] = 'action.woocommerce_new_order';
        $dataList['session_id'] = $this->sessionId;
        $this->be_send_action_data($dataList);

        // remove previous session
        /* if( null !== WC()->session && null !== WC()->session->get( 'woo_registered_user_id' ) ) {
            WC()->session->__unset( 'woo_registered_user_id' );
        } */

        if(!empty($_SESSION['woo_registered_user_id'])){
            unset($_SESSION['woo_registered_user_id']);
        }

        // remove cart cookie
        if (isset($_COOKIE['cart'])) {

            unset($_COOKIE['cart']);
            @setcookie('cart', null, -1, '/');
        }

        // remove cart total cookie
        if (isset($_COOKIE['cart_total'])) {

            unset($_COOKIE['cart_total']);
            @setcookie('cart_total', null, -1, '/');
        }
    }

    /**
     * Checkout Form Event.
     */
    public function be_woocommerce_before_checkout_form() {

        $logger = wc_get_logger();
        $logger->debug( 'bayengage before checkout');
        $dataList['shop_url'] = get_home_url();
        $dataList['action_webhook'] = 'action.woocommerce_before_checkout_form';
        $dataList['session_id'] = $this->sessionId;
        $dataList['user_id'] = $this->userId;
        $this->be_send_action_data($dataList);
    }

    /**
     * @param array $dataList
     */
    public function be_send_action_data($dataList)
    {

        try {
            $tenantUuid = get_option('BEM_CONNECTION_USER_ID');

            if (BAYENGAGE_ENVIRONMENT === 'LIVE'){
                $baseUrl = BAYENGAGE_LIVE_WEBHOOK_URL.$tenantUuid;
            }else{
                $baseUrl = BAYENGAGE_DEV_WEBHOOK_URL.$tenantUuid;
            }

            $headers = array(
                'Content-Type' => 'application/json',
                'x-wc-webhook-source' => $dataList['shop_url'],
                'x-wc-webhook-topic' => $dataList['action_webhook']
            );
            $request = new WP_Http;
            $result = $request->request( $baseUrl, array( 'method' => 'POST', 'body' => wp_json_encode( $dataList ), 'headers' => $headers) );

        } catch (\Exception $e) {
            $errorMsg = ', Message: ' . $e->getMessage();
            $errorMsg .= ', Line: ' . $e->getLine();
        }
    }

    public function wc_cart_preload(){
        add_action( 'template_redirect', array($this,'woocommerce_add_multiple_products_to_cart') );
    }

    public function woocommerce_add_multiple_products_to_cart() {

        $cartUrl = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';

        if (!class_exists('WC_Form_Handler') || empty($cartUrl) || strpos($cartUrl, 'add-to-cart') === false) {
            return;
        }
        $cartSplitStepOne = explode('?', $cartUrl)[1];
        $addToCart = ( explode( '=', explode('&', $cartSplitStepOne)[0] )[1] );
        $quantity = ( explode( '=', explode('&', $cartSplitStepOne)[1] )[1] );
        $product_ids = explode(',', $addToCart);
        $quantity = empty($_REQUEST['quantity']) ? 1 : explode(',', $quantity);
        foreach ($product_ids as $key => $product_id) {
            $quantity_value = empty($_REQUEST['quantity']) ? 1 : wc_stock_amount($quantity[$key]);
            $adding_to_cart = wc_get_product($product_id);
            //$passed_validation = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $quantity_value);

            $found = false;
            //check if product already in cart
            if(count($product_ids) > 1){
                if ( sizeof( WC()->cart->get_cart() ) > 0 ) {
                    foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
                        $_product = $values['data'];
                        if ( $_product->get_id() == $product_id )
                            $found = true;
                    }
                    // if product not found, add it
                    if ( ! $found )
                        WC()->cart->add_to_cart( $product_id,$quantity_value );
                } else {
                    // if no products in cart, add it
                    WC()->cart->add_to_cart( $product_id,$quantity_value );
                }
            }
        }
    }

    /**
     * Get product id from cart data.
     */
    public function getProductId($cartItem){

        $productId = $cartItem['product_id'];
        if( $cartItem['variation_id'] !== 0 ){
            $productId = $cartItem['variation_id'];
        }
        return $productId;
    }

}