<?php
/**
** A base module for [quiz]
**/

/* Shortcode handler */

suptic_add_shortcode( 'quiz', 'suptic_quiz_shortcode_handler', true );

function suptic_quiz_shortcode_handler( $tag ) {
	if ( ! is_array( $tag ) )
		return '';

	$type = $tag['type'];
	$name = $tag['name'];
	$options = (array) $tag['options'];
	$pipes = $tag['pipes'];

	if ( empty( $name ) )
		return '';

	$atts = '';
	$id_att = '';
	$class_att = '';
	$size_att = '';
	$maxlength_att = '';

	foreach ( $options as $option ) {
		if ( preg_match( '%^id:([-0-9a-zA-Z_]+)$%', $option, $matches ) ) {
			$id_att = $matches[1];

		} elseif ( preg_match( '%^class:([-0-9a-zA-Z_]+)$%', $option, $matches ) ) {
			$class_att .= ' ' . $matches[1];

		} elseif ( preg_match( '%^([0-9]*)[/x]([0-9]*)$%', $option, $matches ) ) {
			$size_att = (int) $matches[1];
			$maxlength_att = (int) $matches[2];
		}
	}

	if ( $id_att )
		$atts .= ' id="' . trim( $id_att ) . '"';

	if ( $class_att )
		$atts .= ' class="' . trim( $class_att ) . '"';

	if ( $size_att )
		$atts .= ' size="' . $size_att . '"';
	else
		$atts .= ' size="40"'; // default size

	if ( $maxlength_att )
		$atts .= ' maxlength="' . $maxlength_att . '"';

	if ( is_a( $pipes, 'SupTic_Pipes' ) && ! $pipes->zero() ) {
		$pipe = $pipes->random_pipe();
		$question = $pipe->before;
		$answer = $pipe->after;
	} else {
		// default quiz
		$question = '1+1=?';
		$answer = '2';
	}

	$answer = suptic_canonicalize( $answer );

	$html = '<span class="suptic-quiz-label">' . esc_html( $question ) . '</span>&nbsp;';
	$html .= '<input type="text" name="' . $name . '"' . $atts . ' />';
	$html .= '<input type="hidden" name="_suptic_quiz_answer_' . $name . '" value="' . wp_hash( $answer, 'suptic_quiz' ) . '" />';

	if ( $validation_error = $_POST['_suptic_validation_errors']['messages'][$name] ) {
		$validation_error = '<span class="suptic-not-valid-tip-no-ajax">'
			. esc_html( $validation_error ) . '</span>';
	} else {
		$validation_error = '';
	}

	$html = '<span class="suptic-form-control-wrap ' . $name . '">' . $html . $validation_error . '</span>';

	return $html;
}


/* Validation filter */

add_filter( 'suptic_validate_quiz', 'suptic_quiz_validation_filter', 10, 2 );

function suptic_quiz_validation_filter( $result, $tag ) {
	$type = $tag['type'];
	$name = $tag['name'];

	$answer = suptic_canonicalize( $_POST[$name] );
	$answer_hash = wp_hash( $answer, 'suptic_quiz' );
	$expected_hash = $_POST['_suptic_quiz_answer_' . $name];
	if ( $answer_hash != $expected_hash ) {
		$result['valid'] = false;
		$result['reason'][$name] = __( "Your answer is not correct.", 'suptic' );
	}

	return $result;
}


/* Ajax echo filter */

add_filter( 'suptic_ajax_onload', 'suptic_quiz_ajax_refill' );
add_filter( 'suptic_ajax_json_echo', 'suptic_quiz_ajax_refill' );

function suptic_quiz_ajax_refill( $items ) {
	global $suptic_form;

	if ( ! is_a( $suptic_form, 'SupTic_Form' ) )
		return $items;

	if ( ! is_array( $items ) )
		return $items;

	$fes = $suptic_form->form_scan_shortcode(
		array( 'type' => 'quiz' ) );

	if ( empty( $fes ) )
		return $items;

	$refill = array();

	foreach ( $fes as $fe ) {
		$fe = apply_filters( 'suptic_form_tag', $fe );

		$name = $fe['name'];
		$pipes = $fe['pipes'];

		if ( empty( $name ) )
			continue;

		if ( is_a( $pipes, 'SupTic_Pipes' ) && ! $pipes->zero() ) {
			$pipe = $pipes->random_pipe();
			$question = $pipe->before;
			$answer = $pipe->after;
		} else {
			// default quiz
			$question = '1+1=?';
			$answer = '2';
		}

		$answer = suptic_canonicalize( $answer );

		$refill[$name] = array( $question, wp_hash( $answer, 'suptic_quiz' ) );
	}

	if ( ! empty( $refill ) )
		$items['quiz'] = $refill;

	return $items;
}

?>