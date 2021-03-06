<?php
/**
 * Core component classes.
 *
 * @package BuddyPress
 * @subpackage Core
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Represents an email that will be sent to member(s).
 *
 * @since 2.4.0
 */
class BP_Email {
	/**
	 * The WordPress Post object containing the email text and customisations.
	 *
	 * @since 2.4.0
	 *
	 * @var WP_Post
	 */
	protected $post_obj = null;

	/**
	 * Send from this address.
	 *
	 * @since 2.4.0
	 *
	 * @var string
	 */
	protected $from = '';

	/**
	 * Send to this address.
	 *
	 * @since 2.4.0
	 *
	 * @var string[]
	 */
	protected $to = array();

	/**
	 * CC to this address.
	 *
	 * @since 2.4.0
	 *
	 * @var string[]
	 */
	protected $cc = array();

	/**
	 * BCC to this address.
	 *
	 * @since 2.4.0
	 *
	 * @var string[]
	 */
	protected $bcc = array();

	/**
	 * Email subject.
	 *
	 * @since 2.4.0
	 *
	 * @var string
	 */
	protected $subject = '';

	/**
	 * Email body.
	 *
	 * @since 2.4.0
	 *
	 * @var string
	 */
	protected $body = '';

	/**
	 * Alternate email body.
	 *
	 * Assumed to be plain text.
	 *
	 * @since 2.4.0
	 *
	 * @var string
	 */
	protected $body_plaintext = '';

	/**
	 * Token names and replacement values for this email.
	 *
	 * @since 2.4.0
	 *
	 * @var array Key/value pairs of token name/value (strings).
	 */
	protected $tokens = array();

	/**
	 * Email headers.
	 *
	 * @since 2.4.0
	 *
	 * @var array Key/value pairs of header name/values (strings).
	 */
	protected $headers = array();

	/**
	 * Constructor
	 *
	 * @since 2.4.0
	 */
	public function __construct() {

		/**
		 * Fires inside __construct() method for BP_Email class.
		 *
		 * @since 2.4.0
		 *
		 * @param BP_Email $this Current instance of the email type class.
		 */
		do_action( 'bp_email', $this );
	}


	/**
	 * Psuedo setters/getters.
	 */

	/**
	 * Set the email's "from" address.
	 *
	 * @since 2.4.0
	 *
	 * @param string $email_address
	 * @return BP_Email
	 */
	public function from( $email_address ) {
		if ( is_email( $email_address ) ) {
			$email_address = sanitize_email( $email_address );
		} else {
			$email_address = '';
		}

		$this->from = apply_filters( 'bp_email_set_from', $email_address, $this );

		return $this;
	}

	/**
	 * Set the email's "to" address.
	 *
	 * @since 2.4.0
	 *
	 * @param array|string $email_addresses
	 * @return BP_Email
	 */
	public function to( $email_addresses ) {
		if ( ! is_array( $email_addresses ) ) {
			$email_addresses = (array) $email_addresses;
		}

		$email_addresses = array_filter( $email_addresses, 'is_email' );
		$email_addresses = array_unique( array_map( 'sanitize_email', $email_addresses ) );
		$this->to        = apply_filters( 'bp_email_set_to', $email_addresses, $this );

		return $this;
	}

	/**
	 * Set the email's "cc" address.
	 *
	 * @since 2.4.0
	 *
	 * @param array|string $email_addresses
	 * @return BP_Email
	 */
	public function cc( $email_addresses ) {
		if ( ! is_array( $email_addresses ) ) {
			$email_addresses = (array) $email_addresses;
		}

		$email_addresses = array_filter( $email_addresses, 'is_email' );
		$email_addresses = array_unique( array_map( 'sanitize_email', $email_addresses ) );
		$this->cc        = apply_filters( 'bp_email_set_cc', $email_addresses, $this );

		return $this;
	}

	/**
	 * Set the email's "bcc" address.
	 *
	 * @since 2.4.0
	 *
	 * @param array|string $email_addresses
	 * @return BP_Email
	 */
	public function bcc( $email_addresses ) {
		if ( ! is_array( $email_addresses ) ) {
			$email_addresses = (array) $email_addresses;
		}

		$email_addresses = array_filter( $email_addresses, 'is_email' );
		$email_addresses = array_unique( array_map( 'sanitize_email', $email_addresses ) );
		$this->bcc       = apply_filters( 'bp_email_set_bcc', $email_addresses, $this );

		return $this;
	}

	/**
	 * Set the email subject.
	 *
	 * @since 2.4.0
	 *
	 * @param string $subject
	 * @return BP_Email
	 */
	public function subject( $subject ) {
		$subject       = sanitize_text_field( $subject );
		$this->subject = apply_filters( 'bp_email_set_subject', $subject, $this );

		return $this;
	}

	/**
	 * Set the email body.
	 *
	 * @since 2.4.0
	 *
	 * @param string $html Email body. Assumed to be HTML.
	 * @param string $plaintext Optional. Plain text version for HTML messages (multipart).
	 * @return BP_Email
	 */
	public function body( $html, $plaintext = '' ) {
		$html       = sanitize_text_field( $html );
		$plaintext  = sanitize_text_field( $plaintext );

		$this->body           = apply_filters( 'bp_email_set_body', $html, $this );
		$this->body_plaintext = apply_filters( 'bp_email_set_body_plaintext', $plaintext, $this );

		return $this;
	}

	/**
	 * Set token names and replacement values for this email.
	 *
	 * In templates, tokens are inserted with a Handlebars-like syntax, e.g. `{{token_name}}`.
	 * { and } are reserved characters. There's no need to specify these brackets in your token names.
	 *
	 * @since 2.4.0
	 *
	 * @param array $tokens Key/value pairs of token name/value (strings).
	 * @return BP_Email
	 */
	public function tokens( array $tokens ) {

		// Wrap token name in {{brackets}}.
		foreach ( $tokens as $name => $value ) {
			$tokens[ $name ] = '{{' . str_replace( array( '{', '}' ), '', $value ) . '}}';
		}

		$this->tokens = apply_filters( 'bp_email_set_tokens', $tokens, $this );

		return $this;
	}

	/**
	 * Set email headers.
	 *
	 * Does NOT let you override to/from, etc. Use the methods provided to set those.
	 *
	 * @since 2.4.0
	 *
	 * @param array $headers Key/value pairs of heade name/values (strings).
	 * @return BP_Email
	 */
	public function headers( array $headers ) {
		$new_headers = array();

		for ( $headers as $name => $content ) {
			$content = str_replace( ':', '', $content );
			$name    = str_replace( ':', '', $name );

			$new_headers[ $name ] = $content;
		}

		$this->headers = apply_filters( 'bp_email_set_headers', $new_headers, $headers, $this );

		return $this;
	}

	/**
	 * Getter function to expose object properties.
	 *
	 * Unlike most other methods in this class, this one is not chainable.
	 *
	 * @since 2.4.0
	 * @param string $property Name of property to accss.
	 * @return mixed Returns null if property does not exist, otherwise the value.
	 */
	public function get( $property ) {
		if ( ! property_exists( $this, $property ) ) {
			return null;
		}

		$retval = apply_filters( "bp_email_get_{$property}", $this->$property, $this );
		return apply_filters( 'bp_email_get_property', $retval, $property, $this );
	}


	/**
	 * Sanitisation and validation logic.
	 */

	/**
	 * Check that we'd be able to send this email.
	 *
	 * Unlike most other methods in this class, this one is not chainable.
	 *
	 * @since 2.4.0
	 *
	 * @return bool|WP_Error Returns true if validation succesful, else a descriptive WP_Error.
	 */
	public function validate() {
		$retval = true;

		// BCC, CC, and token properties are optional.
		if ( ! $this->get( 'from' ) || ! $this->get( 'to' ) ||	! $this->get( 'subject' ) || ! $this->get( 'body' ) ) {
			$retval = new WP_Error( 'missing_parameter', __CLASS__, $this );
		}

		return apply_filters( 'bp_email_validate', $retval, $this );
	}
}

/*

$email = bp_get_email( 'new_user' );
// subject + body set via WP_Post, but methods to override.
$email->to( 'example@djpaul.com' );
$email->bcc( 'your@mom.com' );
$email->tokens( $some_kv_array );
	$email->validate();


$email_provider->send( $email->validate()->get_text(), 'html/plaintext' )
->get_subject()
*/


