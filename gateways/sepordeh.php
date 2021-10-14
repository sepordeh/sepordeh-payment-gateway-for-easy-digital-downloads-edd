<?php
/**
 * Sepordeh Gateway for Easy Digital Downloads
 *
 * @author 				sepordeh.com
 * @package 			EZP
 * @subpackage 			Gateways
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'EDD_Sepordeh_Gateway' ) ) :

/**
 * Sepordeh Gateway for Easy Digital Downloads
 *
 * @author 				sepordeh.com
 * @package 			EZP
 * @subpackage 			Gateways
 */
class EDD_Sepordeh_Gateway {
	/**
	 * Gateway keyname
	 *
	 * @var 				string
	 */
	public $keyname;

	/**
	 * Initialize gateway and hook
	 *
	 * @return 				void
	 */
	public function __construct() {
		$this->keyname = 'sepordeh';

		add_filter( 'edd_payment_gateways', array( $this, 'add' ) );
		add_action( $this->format( 'edd_{key}_cc_form' ), array( $this, 'cc_form' ) );
		add_action( $this->format( 'edd_gateway_{key}' ), array( $this, 'process' ) );
		add_action( $this->format( 'edd_verify_{key}' ), array( $this, 'verify' ) );
		add_filter( 'edd_settings_gateways', array( $this, 'settings' ) );

		add_action( 'edd_payment_receipt_after', array( $this, 'receipt' ) );

		add_action( 'init', array( $this, 'listen' ) );
	}

	/**
	 * Add gateway to list
	 *
	 * @param 				array $gateways Gateways array
	 * @return 				array
	 */
	public function add( $gateways ) {
		global $edd_options;

		$gateways[ $this->keyname ] = array(
			'checkout_label' 		=>	isset( $edd_options['sepordeh_label'] ) ? $edd_options['sepordeh_label'] : 'پرداخت آنلاین سپرده ',
			'admin_label' 			=>	'سپرده'
		);

		return $gateways;
	}

	/**
	 * CC Form
	 * We don't need it anyway.
	 *
	 * @return 				bool
	 */
	public function cc_form() {
		return;
	}

	/**
	 * Process the payment
	 *
	 * @param 				array $purchase_data
	 * @return 				void
	 */
	public function process( $purchase_data ) {
		global $edd_options;
		@ session_start();
		$payment = $this->insert_payment( $purchase_data );

		if ( $payment ) {

			
			$merchant = ( isset( $edd_options[ $this->keyname . '_merchant' ] ) ? $edd_options[ $this->keyname . '_merchant' ] : '' );
			$desc = 'پرداخت شماره #' . $payment.' | '.$purchase_data['user_info']['first_name'].' '.$purchase_data['user_info']['last_name'];
			$callback = add_query_arg( 'verify_' . $this->keyname, '1', get_permalink( $edd_options['success_page'] ) );

			$amount = intval( $purchase_data['price'] ) / 10;
			if ( edd_get_currency() == 'IRT' )
				$amount = $amount * 10; // Return back to original one.

	
		

			$url='https://sepordeh.com/merchant/invoices/add';
		$params=array(
		'merchant'          => $merchant,
		'amount'       => $amount,
		'callback'     => $callback,
		'orderId' => time(),
		'description'  => $desc,
	);
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$result = curl_exec($ch);
	curl_close($ch);
			$err = curl_error( $ch );
			if ( $err ) {
				edd_insert_payment_note( $payment, 'کد خطا: CURL#' . $err );
				edd_update_payment_status( $payment, 'failed' );
				edd_set_error( 'sepordeh_connect_error', 'در اتصال به درگاه مشکلی پیش آمد.' );
				edd_send_back_to_checkout();
				return false;
			}

			$result = json_decode( $result);
			curl_close( $ch );

			if ($result->status==200) {
				edd_insert_payment_note( $payment, 'کد تراکنش sepordeh ‌: ' . $result->information->invoice_id );
				edd_update_payment_meta( $payment, 'sepordeh_authority', $result->information->invoice_id );
				$_SESSION['sp_payment'] = $payment;

				wp_redirect( "https://sepordeh.com/merchant/invoices/pay/automatic:true/id:".$result->information->invoice_id );
			} else {
				edd_insert_payment_note( $payment, 'کد خطا: ' . $result->status );
				edd_insert_payment_note( $payment, 'علت خطا: ' . $result->status );
				edd_update_payment_status( $payment, 'failed' );

				edd_set_error( 'sepordeh_connect_error', 'در اتصال به درگاه مشکلی پیش آمد. علت: ' . $result->status );
				edd_send_back_to_checkout();
			}
		} else {
			edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
		}
	}

	/**
	 * Verify the payment
	 *
	 * @return 				void
	 */
	public function verify() {
		global $edd_options;

		if ( isset( $_GET['authority'] ) ) {
			$authority = sanitize_text_field( $_GET['authority'] );

			@ session_start();
			$payment = edd_get_payment( $_SESSION['sp_payment'] );
			unset( $_SESSION['sp_payment'] );

			if ( ! $payment ) {
				wp_die( 'رکورد پرداخت موردنظر وجود ندارد!' );
			}

			if ( $payment->status == 'complete' ) {
				return false;
			}

			$amount = intval( edd_get_payment_amount( $payment->ID ) ) / 10;

			if ( 'IRT' === edd_get_currency() ) {
				$amount = $amount * 10;
			}

			$merchant = ( isset( $edd_options[ $this->keyname . '_merchant' ] ) ? $edd_options[ $this->keyname . '_merchant' ] : '' );

			
$url='https://sepordeh.com/merchant/invoices/verify';
$params= [
		'merchant' 	=> $merchant,
		'authority' => $authority,
	];
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$res = curl_exec($ch);
	curl_close($ch);
$result = json_decode($res);
			edd_empty_cart();

			if ( version_compare( EDD_VERSION, '2.1', '>=' ) ) {
				edd_set_payment_transaction_id( $payment->ID, $authority );
			}

			if ( $result->status==200) {
				edd_insert_payment_note( $payment->ID, 'شماره تراکنش بانکی: ' . $result->information->invoice_id );
				edd_update_payment_meta( $payment->ID, 'sepordeh_refid', $result->information->invoice_id );
				edd_update_payment_status( $payment->ID, 'publish' );
				edd_send_to_success_page();
			} else {
				edd_update_payment_status( $payment->ID, 'failed' );
				wp_redirect( get_permalink( $edd_options['failure_page'] ) );

				exit;
			}
		}
	}

	/**
	 * Receipt field for payment
	 *
	 * @param 				object $payment
	 * @return 				void
	 */
	public function receipt( $payment ) {
		$refid = edd_get_payment_meta( $payment->ID, 'sepordeh_refid' );
		if ( $refid ) {
			echo '<tr class="sepordeh-ref-id-row ezp-field ehsaan-dev"><td><strong>شماره تراکنش بانکی:</strong></td><td>' . $refid . '</td></tr>';
		}
	}

	/**
	 * Gateway settings
	 *
	 * @param 				array $settings
	 * @return 				array
	 */
	public function settings( $settings ) {
		return array_merge( $settings, array(
			$this->keyname . '_header' 		=>	array(
				'id' 			=>	$this->keyname . '_header',
				'type' 			=>	'header',
				'name' 			=>	'<strong>درگاه سپرده‌</strong> توسط <a href="https://ehsaan.dev" target="_blank">ehsaan</a>'
			),
			$this->keyname . '_merchant' 		=>	array(
				'id' 			=>	$this->keyname . '_merchant',
				'name' 			=>	'مرچنت‌کد',
				'type' 			=>	'text',
				'size' 			=>	'regular'
			),
			$this->keyname . '_ip' 		=>	array(
				'id' 			=>	$this->keyname . '_ip',
				'name' 			=>	'آی‌پی سرور شما',
				'type' 			=>	'text',
				'readonly' 		=>	true,
				'std' 			=>	$_SERVER['SERVER_ADDR']
			),
			$this->keyname . '_label' 	=>	array(
				'id' 			=>	$this->keyname . '_label',
				'name' 			=>	'نام درگاه در صفحه پرداخت',
				'type' 			=>	'text',
				'size' 			=>	'regular',
				'std' 			=>	'پرداخت آنلاین سپرده'
			)
		) );
	}

	/**
	 * Format a string, replaces {key} with $keyname
	 *
	 * @param 			string $string To format
	 * @return 			string Formatted
	 */
	private function format( $string ) {
		return str_replace( '{key}', $this->keyname, $string );
	}

	/**
	 * Inserts a payment into database
	 *
	 * @param 			array $purchase_data
	 * @return 			int $payment_id
	 */
	private function insert_payment( $purchase_data ) {
		global $edd_options;

		$payment_data = array(
			'price' => $purchase_data['price'],
			'date' => $purchase_data['date'],
			'user_email' => $purchase_data['user_email'],
			'purchase_key' => $purchase_data['purchase_key'],
			'currency' => $edd_options['currency'],
			'downloads' => $purchase_data['downloads'],
			'user_info' => $purchase_data['user_info'],
			'cart_details' => $purchase_data['cart_details'],
			'status' => 'pending'
		);

		// record the pending payment
		$payment = edd_insert_payment( $payment_data );

		return $payment;
	}

	/**
	 * Listen to incoming queries
	 *
	 * @return 			void
	 */
	public function listen() {
		if ( isset( $_GET[ 'verify_' . $this->keyname ] ) && $_GET[ 'verify_' . $this->keyname ] ) {
			do_action( 'edd_verify_' . $this->keyname );
		}
	}

	/**
	 * Error reason for sepordeh
	 *
	 * @param 			int $error_id
	 * @return 			string
	 */

	public function error_reason( $error_id ) {
		$message = 'خطای ناشناخته';

		switch ( $error_id ) {
			case '-1':
				$message = 'اطلاعات ارسال شده ناقص است';
				break;
			case '-2':
				$message = 'IP -2و يا مرچنت كد پذيرنده صحيح نيست.';
				break;
			case '-3':
				$message = 'با توجه به محدوديت هاي شاپرك امكان پرداخت با رقم درخواست شده ميسر نمي باشد';
				break;
			case '-4':
				$message = 'سطح تاييد پذيرنده پايين تر از سطح نقره اي است.';
				break;
			case '-11':
				$message = 'درخواست مورد نظر يافت نشد.';
				break;
			case '-12':
				$message = 'امكان ويرايش درخواست ميسر نمي باشد';
				break;
			case '-21':
				$message = 'هيچ نوع عمليات مالي براي اين تراكنش يافت نشد.';
				break;
			case '-22':
				$message = 'هيچ نوع عمليات مالي براي اين تراكنش يافت نشد.';
				break;
			case '-33':
				$message = 'رقم تراكنش با رقم پرداخت شده مطابقت ندارد.';
				break;
			case '-34':
				$message = 'سقف تقسيم تراكنش از لحاظ تعداد يا رقم عبور نموده است';
				break;
			case '-40':
				$message = 'اجازه دسترسي به متد مربوطه وجود ندارد.';
				break;
			case '-41':
				$message = 'اطلاعات ارسال شده مربوط به  AdditionalDataغيرمعتبر ميباشد.';
				break;
			case '-42':
				$message = 'مدت زمان معتبر طول عمر شناسه پرداخت بايد بين  30دقيه تا  45روز مي باشد.';
				break;
			case '-54':
				$message = 'درخواست مورد نظر آرشيو شده است.';
				break;
			case '100':
				$message = 'عمليات با موفقيت انجام گرديده است';
				break;
			case '101':
				$message = 'عمليات پرداخت موفق بوده و قبلا  PaymentVerificationتراكنش انجام شده است.';
			break;
			case '102':
				$message = 'تراکنش توسط کاربر لغو شد.';
			break;
		}

		return $message;

	}
}

endif;

new EDD_Sepordeh_Gateway;
