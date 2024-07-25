<?php

if (!defined('ABSPATH')) {
    exit; // יציאה אם ניגשו ישירות
}

class WC_Gateway_Pelecard extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'pelecard';
        $this->method_title = __('תשלום פלקארד', 'woocommerce');
        $this->method_description = __('שער תשלום מותאם אישית עבור WooCommerce באמצעות פלקארד פותח ע"י חברת Dooble.', 'woocommerce');
        $this->has_fields = false;

        // טעינת ההגדרות
        $this->init_form_fields();
        $this->init_settings();

        // הגדרת משתנים שהוגדרו על ידי המשתמש
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->terminal = $this->get_option('terminal');
        $this->user = $this->get_option('user');
        $this->password = $this->get_option('password');
        $this->GoodURL = $this->get_option('GoodURL');
        $this->ErrorURL = $this->get_option('ErrorURL');

        // פעולות
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

        // מאזין תשלומים/וו API
        add_action('woocommerce_api_wc_gateway_pelecard', array($this, 'check_response'));

        // Example of adding custom HTML to the checkout page
        add_action('woocommerce_review_order_before_payment', array($this, 'add_pelecard_iframe') );

        add_action('wp_footer', array($this, 'redirect_after_payment') );


    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('הפעל/השבת', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('הפעל תשלום פלקארד', 'woocommerce'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('כותרת', 'woocommerce'),
                'type' => 'text',
                'description' => __('כותרת זו תוצג למשתמש במהלך התשלום.', 'woocommerce'),
                'default' => __('תשלום פלקארד', 'woocommerce'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('תיאור', 'woocommerce'),
                'type' => 'textarea',
                'description' => __('תיאור זה יוצג למשתמש במהלך התשלום.', 'woocommerce'),
                'default' => __('שלמו בבטחה באמצעות פלקארד.', 'woocommerce')
            ),
            'terminal' => array(
                'title' => __('מספר טרמינל', 'woocommerce'),
                'type' => 'text',
                'description' => __('מספר הטרמינל שלך בפלקארד.', 'woocommerce'),
                'default' => '',
                'desc_tip' => true,
            ),
            'user' => array(
                'title' => __('משתמש', 'woocommerce'),
                'type' => 'text',
                'description' => __('שם המשתמש שלך בפלקארד.', 'woocommerce'),
                'default' => '',
                'desc_tip' => true,
            ),
            'password' => array(
                'title' => __('סיסמא', 'woocommerce'),
                'type' => 'password',
                'description' => __('הסיסמא שלך בפלקארד.', 'woocommerce'),
                'default' => '',
                'desc_tip' => true,
            ),
            'GoodURL' => array(
                'title' => __('כתובת URL מוצלחת', 'woocommerce'),
                'type' => 'text',
                'description' => __('כתובת URL להפניה לאחר תשלום מוצלח.', 'woocommerce'),
                'default' => '',
                'desc_tip' => true,
            ),
            'ErrorURL' => array(
                'title' => __('כתובת URL שגיאה', 'woocommerce'),
                'type' => 'text',
                'description' => __('כתובת URL להפניה לאחר תשלום שנכשל.', 'woocommerce'),
                'default' => '',
                'desc_tip' => true,
            ),
        );
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        // נתוני העסקה
        $data = array(
            'terminal' => $this->terminal,
            'user' => $this->user,
            'password' => $this->password,
            'GoodURL' => $this->GoodURL.'?order_id='.$order_id,
            'ErrorURL' => $this->ErrorURL,
            'ActionType' => 'J4',
            'Currency' => 1,
            'Total' => $order->get_total()*100,
            'CreateToken' => 'True',
            'Language' => 'HE',
            'CustomerIdField' => 'must',
            'Cvv2Field' => 'must',
            'MaxPayments' => '12',
            'MinPayments' => '1',
            'MinPaymentsForCredit' => '7',
            'FirstPayment' => 'auto',
            'ShopNo' => '001',
            'ParamX' => 'תשלום',
            'CssURL' => 'https://gateway21.pelecard.biz/Content/Css/variant-he-1.css',
            'LogoURL' => 'https://gateway21.pelecard.biz/Content/images/Pelecard.png',
            'UserData1' => 'xxxx',
            'UserData2' => $order_id,
        );

        // קידוד הנתונים ל-JSON
        $jsonData = json_encode($data);

        // אתחול cURL
        $ch = curl_init('https://gateway21.pelecard.biz/PaymentGW/init');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json; charset=UTF-8',
            'Content-Length: ' . strlen($jsonData)
        ));

        // ביצוע הבקשה ב-cURL
        $result = curl_exec($ch);
        $serverData = json_decode($result, true);


        // בדיקת שגיאות
        if (curl_errno($ch)) {
            wc_add_notice(__('שגיאת חיבור:', 'woocommerce') . ' ' . curl_error($ch), 'error');
            return;
        }
        curl_close($ch);

        // Example: Generate or fetch the payment URL from Pelecard API
        $payment_url = $serverData['URL']; // Replace with actual URL
        
        // Mark order as pending payment and set Pelecard payment URL
        $order->update_status('pending', __('Awaiting payment via Pelecard.', 'woocommerce'));
        update_post_meta($order_id, '_pelecard_payment_url', $payment_url);
        
        // Return redirect URL to display Pelecard payment page in an iframe
        return array(
            'result'   => 'success',
            'redirect' => $order->get_checkout_payment_url(true), // Redirect to checkout page
            // 'redirect' => $payment_url, // This is where you'd redirect to Pelecard's payment page
        );

    }

    public function receipt_page($order) {
        echo '<p>' . __('תודה על הזמנתך, אנא לחץ על הכפתור למטה כדי לשלם באמצעות פלקארד.', 'woocommerce') . '</p>';
        echo '<a class="button alt" href="' . esc_url($order->get_checkout_payment_url(true)) . '">' . __('שלם עכשיו', 'woocommerce') . '</a>';
    }

    public function check_response() {
        // התמודדות עם התגובה מהסליקה
    }



    public function add_pelecard_iframe() {
        $order_id = get_query_var('order-pay');
        $payment_url = get_post_meta($order_id, '_pelecard_payment_url', true);

        if( is_wc_endpoint_url( 'order-pay' ) ){
            if (!empty($payment_url)) {
                ?>
                <div class="pelecard-iframe-container">
                    <iframe src="<?php echo esc_url($payment_url); ?>" width="100%" height="700px" frameborder="0"></iframe>
                </div>
                <?php
            }
        }


    }


    function redirect_after_payment() {
        
        if (is_page('thank-you-page-slug')) {
            // Ensure you have logic to check if this is coming from Pelecard
            // For example, by checking a specific query parameter or session variable
            if (isset($_GET['payment_status']) && $_GET['payment_status'] == 'success') {
                wp_redirect('https://yourwebsite.com/thank-you');
                exit();
            }
        }

    }


}
?>