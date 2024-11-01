<?php

add_action( 'init', 'suptic_init_switch', 11 );

function suptic_init_switch() {
	if ( 'GET' == $_SERVER['REQUEST_METHOD'] && 1 == (int) $_GET['_suptic_is_ajax_call'] ) {
		suptic_ajax_onload();
		exit();
	} elseif ( 'POST' == $_SERVER['REQUEST_METHOD'] && 1 == (int) $_POST['_suptic_is_ajax_call'] ) {
		suptic_ajax_json_echo();
		exit();
	} else {
		if ( 'create_ticket' == $_POST['_suptic_action'] ) {
			suptic_control_create_ticket();
		} elseif ( 'add_message' == $_POST['_suptic_action'] ) {
			suptic_control_add_message();
		} elseif ( 'close_or_reopen_ticket' == $_POST['_suptic_action'] ) {
			suptic_control_close_or_reopen_ticket();
		}
	}
}

function suptic_ajax_onload() {
	global $suptic_form;

	$echo = '';

	if ( isset( $_GET['_suptic_form_id'] ) ) {
		$id = (int) $_GET['_suptic_form_id'];

		if ( $suptic_form = suptic_get_form( $id ) ) {
			$items = apply_filters( 'suptic_ajax_onload', array() );
			$suptic_form = null;
		}
	}

	$echo = suptic_json( $items );

	if ( $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest' ) {
		@header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		echo $echo;
	}
}

function suptic_ajax_json_echo() {
	global $suptic_form;

	$echo = '';

	if ( ! $suptic_form = suptic_get_form( $_POST['_suptic_form_id'] ) )
		return false;

	if ( ! suptic_check_referer( 'suptic-form-' . $suptic_form->id . '-create-ticket' ) ) {
		$_POST['_suptic_form_response'] = array(
			'id' => $suptic_form->id, 'ok' => false,
			'message' => __( "Please submit again.", 'suptic' ) );

		$suptic_form = null;
		return;
	}

	$validation = $suptic_form->validate();

	$items = array(
		'captcha' => null );

	$items = apply_filters( 'suptic_ajax_json_echo', $items );

	if ( ! $validation['valid'] ) { // Validation error occured
		$invalids = array();
		foreach ( $validation['reason'] as $name => $reason ) {
			$invalids[] = array(
				'into' => 'span.suptic-form-control-wrap.' . $name,
				'message' => $reason );
		}

		$items['message'] = __( "Validation errors occurred. Please confirm the fields and submit it again.", 'suptic' );
		$items['invalids'] = $invalids;

	} elseif ( ! $suptic_form->accepted() ) { // Not accepted terms
		$items['message'] = __( "Please accept the terms to proceed.", 'suptic' );

	} elseif ( $suptic_form->akismet() ) { // Spam!
		$items['message'] = __( "Problem occurred. Please try later or contact administrator by other way.", 'suptic' );
		$items['spam'] = true;

	} elseif ( $ticket = $suptic_form->create_ticket_and_first_message() ) {
		$access_key = $ticket->access_key;

		$redirect_to = add_query_arg(
			array( 'ticket' => $ticket->id, 'accesskey' => $access_key ) );

		$items['message'] = __( "Your ticket is now open. Redirecting to the ticket page.",
			'suptic' );
		$items['redirect'] = $redirect_to;

	} else {
		$items['error'] = true;
		$items['message'] = __( "Problem occurred. Please try later or contact administrator by other way.", 'suptic' );
	}

	$suptic_form = null;

	$echo = suptic_json( $items );

	if ( $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest' ) {
		@header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		echo $echo;
	} else {
		@header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		echo '<textarea>' . $echo . '</textarea>';
	}
}

function suptic_control_create_ticket() {
	global $suptic_form;

	if ( ! $suptic_form = suptic_get_form( $_POST['_suptic_form_id'] ) )
		return false;

	if ( ! suptic_check_referer( 'suptic-form-' . $suptic_form->id . '-create-ticket' ) ) {
		$_POST['_suptic_form_response'] = array(
			'id' => $suptic_form->id, 'ok' => false,
			'message' => __( "Please submit again.", 'suptic' ) );

		$suptic_form = null;
		return;
	}

	$validation = $suptic_form->validate();

	if ( ! $validation['valid'] ) {
		$_POST['_suptic_validation_errors'] =
			array( 'id' => $suptic_form->id, 'messages' => $validation['reason'] );

		$suptic_form = null;
		return;
	}

	if ( ! $suptic_form->accepted() ) { // Not accepted terms
		$_POST['_suptic_form_response'] = array(
			'id' => $suptic_form->id, 'ok' => false,
			'message' => __( "Please accept the terms to proceed.", 'suptic' ) );

		$suptic_form = null;
		return;
	}

	if ( $suptic_form->akismet() ) { // Spam!
		$_POST['_suptic_form_response'] = array(
			'id' => $suptic_form->id, 'ok' => false, 'spam' => true,
			'message' => __( "Problem occurred. Please try later or contact administrator by other way.", 'suptic' ) );

		$suptic_form = null;
		return;
	}

	if ( $ticket = $suptic_form->create_ticket_and_first_message() ) {
		$access_key = $ticket->access_key;

		$redirect_to = add_query_arg(
			array( 'ticket' => $ticket->id, 'accesskey' => $access_key ) );
	} else {
		$redirect_to = add_query_arg( 'message', 'failed_to_create_ticket' );
	}

	$suptic_form = null;

	wp_redirect( $redirect_to );
	exit();
}

function suptic_control_add_message() {
	if ( ! $ticket = suptic_get_ticket( $_POST['_suptic_ticket_id'] ) )
		return false;

	if ( ! suptic_check_referer( 'suptic-ticket-' . $ticket->id . '-add-message' ) ) {
		$_POST['_suptic_form_response'] = array(
			'id' => $ticket->id, 'ok' => false,
			'message' => __( "Please submit again.", 'suptic' ) );

		return;
	}

	$message = trim( $_POST['message-body'] );

	if ( empty( $message ) ) {
		$_POST['_suptic_form_response'] = array(
			'id' => $ticket->id, 'ok' => false,
			'message' => __( "Please input message.", 'suptic' ) );

		return;
	}

	if ( $message = $ticket->create_message( array( 'message_body' => $message ) ) ) {

		if ( $message->is_admin_reply() )
			$ticket->set_status( 'admin_replied' );
		else
			$ticket->set_status( 'waiting_reply' );

		do_action_ref_array( 'suptic_control_add_message', array( &$ticket, &$message ) );
	}

	$redirect_to = add_query_arg( array() );

	wp_redirect( $redirect_to );
	exit();
}

function suptic_control_close_or_reopen_ticket() {
	if ( ! $ticket = suptic_get_ticket( $_POST['_suptic_ticket_id'] ) )
		return false;

	if ( ! suptic_check_referer( 'suptic-ticket-' . $ticket->id . '-close-or-reopen' ) ) {
		$_POST['_suptic_form_response'] = array(
			'id' => $ticket->id, 'ok' => false,
			'message' => __( "Please submit again.", 'suptic' ) );

		return;
	}

	if ( isset( $_POST['suptic-close-ticket'] ) ) {
		$ticket->set_status( 'closed' );
	} elseif ( isset( $_POST['suptic-reopen-ticket'] ) ) {
		$ticket->set_status( 'new' );
	}

	$redirect_to = add_query_arg( array() );

	wp_redirect( $redirect_to );
	exit();
}

add_filter( 'the_content', 'suptic_content_filter', 12 ); // After wpautop and shortcodes

function suptic_content_filter( $content ) {
	$page_id = get_the_ID();

	if ( isset( $_GET['ticket'] ) && $ticket = suptic_get_ticket( (int) $_GET['ticket'] ) ) {
		if ( ! $ticket->accessible() )
			return $content . "\n\n" . '<p>' . esc_html( __( "You are not allowed to see this ticket.", 'suptic' ) ) . '</p>';

		if ( ! $form = suptic_get_form( $ticket->form_id ) )
			return $content;

		if ( $ticket->page_id == $page_id || $form->page_id == $page_id )
			return $content . "\n\n" . $ticket->render();
	} else {
		if ( ! $form = suptic_get_form_for_page( $page_id ) )
			return $content;

		if ( $form->get_meta( 'no_acceptance' ) ) {
			$note = '<p class="alert">' . __( 'Temporary halt acceptance of new tickets.', 'suptic' ) . '</p>';
			return $content . "\n\n" . $note;
		}

		if ( $form->get_meta( 'registered_only' ) && ! is_user_logged_in() ) {
			$note = '<p class="alert">' . sprintf( __( 'You must be <a href="%1$s" title="Log in">logged in</a> to open a ticket.', 'suptic' ), wp_login_url( get_permalink() ) ) . '</p>';
			return $content . "\n\n" . $note;
		}

		return $content . "\n\n" . $form->render();
	}

	return $content;
}

function suptic_check_referer( $action = -1, $query_arg = '_wpnonce' ) {
	return isset( $_REQUEST[$query_arg] )
		? wp_verify_nonce( $_REQUEST[$query_arg], $action ) : false;
}

add_action( 'wp_head', 'suptic_head' );

function suptic_head() {
	// Cached?
	if ( SUPTIC_LOAD_JS 
	&& defined( 'SUPTIC_CACHE' ) && SUPTIC_CACHE == true
	&& defined( 'WP_CACHE' ) && WP_CACHE == true ) :
?>
<script type="text/javascript">
//<![CDATA[
var _suptic = { cached: 1 };
//]]>
</script>
<?php
	endif;
}

if ( SUPTIC_LOAD_JS )
	add_action( 'wp_print_scripts', 'suptic_enqueue_scripts' );

function suptic_enqueue_scripts() {
	if ( ! suptic_is_form_page() )
		return;

	$in_footer = true;
	if ( 'header' === SUPTIC_LOAD_JS )
		$in_footer = false;

	wp_enqueue_script( 'suptic', suptic_plugin_url( 'scripts.js' ),
		array( 'jquery', 'jquery-form' ), SUPTIC_VERSION, $in_footer );
}

if ( SUPTIC_LOAD_CSS )
	add_action( 'wp_print_styles', 'suptic_enqueue_styles' );

function suptic_enqueue_styles() {
	if ( ! suptic_is_form_page() )
		return;

	wp_enqueue_style( 'suptic', suptic_plugin_url( 'styles.css' ),
		array(), SUPTIC_VERSION, 'all' );
}

function suptic_is_form_page() {
	global $wpdb;

	$table = suptic_db_table( 'forms' );
	$query = "SELECT DISTINCT page_id FROM $table ORDER BY page_id";
	$form_pages = $wpdb->get_col( $query );

	return is_page( $form_pages );
}

?>