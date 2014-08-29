<?php 



/**
* Allow for the admin to enter a payment received.
*
* 
*/
class SI_Admin_Payment extends SI_Controller {
	const PAYMENT_METHOD = 'Admin Payment';
	const PAYMENT_SLUG = 'admin_payment';
	
	public function get_payment_method() {
		return self::PAYMENT_METHOD;
	}

	public function get_slug() {
		return self::PAYMENT_SLUG;
	}

	public static function init() {

		if ( is_admin() ) {
			// Meta boxes
			add_action( 'admin_init', array( __CLASS__, 'register_meta_boxes' ), 100 );
		}

		// AJAX
		add_filter( 'wp_ajax_sa_admin_payment', array( __CLASS__, 'ajax_admin_payment' ) );
	}

	public static function create_admin_payment( $invoice_id = 0, $amount = '0.00', $number = '', $date = '', $notes = '' ) {
		$invoice = SI_Invoice::get_instance( $invoice_id );
		// create new payment
		$payment_id = SI_Payment::new_payment( array(
				'payment_method' => self::get_payment_method(),
				'invoice' => $invoice_id,
				'amount' => $amount,
				'transaction_id' => $number,
				'data' => array(
					'amount' => $amount,
					'check_number' => $number,
					'date' => $date,
					'notes' => $notes
				),
			), SI_Payment::STATUS_COMPLETE );
		if ( !$payment_id ) {
			return FALSE;
		}

		do_action( 'admin_payment', $payment_id, $invoice );
	}


	/////////////////
	// Meta boxes //
	/////////////////

	/**
	 * Regsiter meta boxes for invoice editing.
	 *
	 * @return
	 */
	public static function register_meta_boxes() {
		// invoice specific
		$args = array(
			'si_invoice_payment' => array(
				'title' => si__( 'Admin Payment' ),
				'show_callback' => array( __CLASS__, 'show_payment_view' ),
				'save_callback' => array( __CLASS__, 'save_admin_payment' ),
				'context' => 'normal',
				'priority' => 'low'
			)
		);
		do_action( 'sprout_meta_box', $args, SI_Invoice::POST_TYPE );
	}

	public static function show_payment_view( $post, $metabox ) {
		if ( $post->post_status == 'auto-draft' ) {
			printf( '<p>%s</p>', si__( 'Save this invoice before adding any payments.' ) );
			return;
		}
		$invoice = SI_Invoice::get_instance( $post->ID );
		self::load_view( 'admin/meta-boxes/invoices/payments', array(
				'id' => $post->ID,
				'post' => $post,
				'invoice' => $invoice,
				'fields' => self::payment_fields( $invoice )
			), FALSE );
	}

	public static function payment_fields( SI_Invoice $invoice ) {
		$fields = array(
			'payment_amount' => array(
				'type' => 'text',
				'weight' => 1,
				'label' => self::__( 'Amount' ),
				'attributes' => array(
					//'autocomplete' => 'off',
				)
			),
			'payment_transaction_id' => array(
				'type' => 'text',
				'weight' => 5,
				'label' => self::__( 'ID' ),
				'attributes' => array(
					//'autocomplete' => 'off',
				)
			),
			'payment_date' => array(
				'type' => 'date',
				'weight' => 10,
				'label' => self::__( 'Date Received' ),
				'attributes' => array(
					'autocomplete' => 'off',
				)
			),
			'payment_notes' => array(
				'type' => 'textarea',
				'weight' => 15,
				'label' => self::__( 'Notes' ),
				'attributes' => array(
					//'autocomplete' => 'off',
				)
			),
			'invoice_id' => array(
				'type' => 'hidden',
				'value' => $invoice->get_id(),
				'weight' => 10000
			),
			'payments_nonce' => array(
				'type' => 'hidden',
				'value' => wp_create_nonce( SI_Controller::NONCE ),
				'weight' => 10001
			)
		);

		$fields = apply_filters( 'si_admin_payment_fields_fields', $fields );
		uasort( $fields, array( __CLASS__, 'sort_by_weight' ) );
		return $fields;
	}

	
	public static function save_admin_payment( $post_id, $post, $callback_args, $invoice_id = NULL ) {
		$invoice = SI_Invoice::get_instance( $post_id );
		$amount = ( isset( $_REQUEST['sa_metabox_payment_amount'] ) ) ? $_REQUEST['sa_metabox_payment_amount'] : 0 ;
		$number = ( isset( $_REQUEST['sa_metabox_payment_transaction_id'] ) ) ? $_REQUEST['sa_metabox_payment_transaction_id'] : '' ;
		$date = ( isset( $_REQUEST['sa_metabox_payment_date'] ) ) ? $_REQUEST['sa_metabox_payment_date'] : '' ;
		$notes = ( isset( $_REQUEST['sa_metabox_payment_notes'] ) ) ? $_REQUEST['sa_metabox_payment_notes'] : '' ;

		if ( !$amount ) {
			return FALSE;
		}

		self::create_admin_payment( $invoice->get_id(), $amount, $number, $date, $notes );
	}

	public static function ajax_admin_payment() {
		// form maybe be serialized
		if ( isset( $_REQUEST['serialized_fields'] ) ) {
			foreach ( $_REQUEST['serialized_fields'] as $key => $data ) {
				if ( strpos( $data['name'], '[]' ) !== false ) {
					$_REQUEST[str_replace('[]', '', $data['name'])][] = $data['value'];
				}
				else {
					$_REQUEST[$data['name']] = $data['value'];
				}
			}
		}
		if ( !isset( $_REQUEST['sa_metabox_payments_nonce'] ) )
			self::ajax_fail( 'Forget something?' );

		$nonce = $_REQUEST['sa_metabox_payments_nonce'];
		if ( !wp_verify_nonce( $nonce, SI_Controller::NONCE ) )
			self::ajax_fail( 'Not going to fall for it!' );

		if ( !isset( $_REQUEST['sa_metabox_invoice_id'] ) )
			self::ajax_fail( 'Forget something?' );

		if ( get_post_type( $_REQUEST['sa_metabox_invoice_id'] ) != SI_Invoice::POST_TYPE ) {
			return;
		}

		$amount = ( isset( $_REQUEST['sa_metabox_payment_amount'] ) ) ? $_REQUEST['sa_metabox_payment_amount'] : 0 ;
		$number = ( isset( $_REQUEST['sa_metabox_payment_transaction_id'] ) ) ? $_REQUEST['sa_metabox_payment_transaction_id'] : '' ;
		$date = ( isset( $_REQUEST['sa_metabox_payment_date'] ) ) ? $_REQUEST['sa_metabox_payment_date'] : '' ;
		$notes = ( isset( $_REQUEST['sa_metabox_payment_notes'] ) ) ? $_REQUEST['sa_metabox_payment_notes'] : '' ;

		if ( !$amount ) {
			return FALSE;
		}

		self::create_admin_payment( $_REQUEST['sa_metabox_invoice_id'], $amount, $number, $date, $notes );

		header( 'Content-type: application/json' );
		if ( SA_DEV ) header( 'Access-Control-Allow-Origin: *' );
		echo json_encode( array( 'response' => si__('Payment Added') ) );
		exit();
	}

}
SI_Admin_Payment::init(); // Since it's not a registered payment processor, init it when file is loaded.
