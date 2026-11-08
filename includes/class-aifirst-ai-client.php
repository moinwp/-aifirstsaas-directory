<?php
/**
 * AI client — Anthropic and OpenAI adapters that parse raw text into a
 * canonical tool record (as a PHP array).
 *
 * Sends ONLY the pasted text and the schema instruction. No site options,
 * user data, or keys other than the provider's own auth header.
 *
 * @package AIFirst
 * @since   0.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * AIFirst_AI_Client.
 *
 * @since 0.2.0
 */
class AIFirst_AI_Client {

	/**
	 * Provider id: 'anthropic' or 'openai'.
	 *
	 * @since 0.2.0
	 * @var string
	 */
	protected $provider = 'anthropic';

	/**
	 * API key.
	 *
	 * @since 0.2.0
	 * @var string
	 */
	protected $api_key = '';

	/**
	 * Model id.
	 *
	 * @since 0.2.0
	 * @var string
	 */
	protected $model = '';

	/**
	 * Build a client from stored settings + wp-config override.
	 *
	 * @since 0.2.0
	 * @return self
	 */
	public static function from_settings() {
		$settings = get_option( 'aifirst_settings', array() );
		$provider = isset( $settings['provider'] ) ? (string) $settings['provider'] : 'anthropic';
		$model    = isset( $settings['model'] ) ? (string) $settings['model'] : '';

		if ( defined( 'AIFIRST_AI_API_KEY' ) && '' !== (string) AIFIRST_AI_API_KEY ) {
			$key = (string) AIFIRST_AI_API_KEY;
		} else {
			$key = isset( $settings['api_key'] ) ? (string) $settings['api_key'] : '';
		}

		$client           = new self();
		$client->provider = in_array( $provider, array( 'anthropic', 'openai' ), true ) ? $provider : 'anthropic';
		$client->api_key  = $key;
		$client->model    = '' !== $model ? $model : $client->default_model();
		return $client;
	}

	/**
	 * Whether a usable key is configured.
	 *
	 * @since 0.2.0
	 * @return bool
	 */
	public function is_configured() {
		return '' !== $this->api_key;
	}

	/**
	 * Current provider id.
	 *
	 * @since 0.2.0
	 * @return string
	 */
	public function get_provider() {
		return $this->provider;
	}

	/**
	 * Current model id.
	 *
	 * @since 0.2.0
	 * @return string
	 */
	public function get_model() {
		return $this->model;
	}

	/**
	 * Default model per provider.
	 *
	 * @since 0.2.0
	 * @return string
	 */
	protected function default_model() {
		return 'anthropic' === $this->provider ? 'claude-sonnet-5-5' : 'gpt-4o-mini';
	}

	/**
	 * Minimal auth-check call. Returns true or WP_Error.
	 *
	 * @since 0.2.0
	 * @return true|WP_Error
	 */
	public function test_connection() {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'aifirst_no_key', __( 'No API key configured.', 'aifirstsaas' ) );
		}
		$res = $this->call( __( 'Reply with the single word OK.', 'aifirstsaas' ), 20 );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return true;
	}

	/**
	 * Parse raw tool text into a canonical record array.
	 *
	 * @since 0.2.0
	 * @param string $raw_text    Pasted text.
	 * @param string $website_url Optional website hint.
	 * @return array|WP_Error Canonical record array, or WP_Error.
	 */
	public function parse_tool_text( $raw_text, $website_url = '' ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'aifirst_no_key', __( 'No API key configured.', 'aifirstsaas' ) );
		}

		$raw_text = (string) $raw_text;
		if ( '' === trim( $raw_text ) ) {
			return new WP_Error( 'aifirst_empty_input', __( 'No text to parse.', 'aifirstsaas' ) );
		}

		$instruction = $this->build_system_instruction();
		$user        = 'Website: ' . ( '' !== $website_url ? $website_url : '(not provided)' ) . "\n\nTool text:\n" . $raw_text;

		$raw = $this->call( $user, 4000, $instruction );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$json = $this->extract_json( $raw );
		if ( null === $json ) {
			return new WP_Error(
				'aifirst_bad_json',
				__( 'AI response was not valid JSON.', 'aifirstsaas' ),
				array( 'raw' => substr( $raw, 0, 2000 ) )
			);
		}
		return $json;
	}

	/**
	 * System instruction embedded on every parse call.
	 *
	 * @since 0.2.0
	 * @return string
	 */
	protected function build_system_instruction() {
		return 'You are an extraction engine for a SaaS directory. Given raw text about one tool, '
			. 'return ONE JSON object matching this schema and NOTHING ELSE. No markdown, no code fence, no prose. '
			. 'Omit fields you cannot determine from the text — do not invent values. '
			. 'Use null for unknown numbers, "" for unknown strings, [] for unknown arrays. '
			. 'company_size_smb / company_size_mid / company_size_ent are percentages 0–100 '
			. '(your best estimate of where the tool is most used). industry percentages should sum to about 100. '
			. 'tool_features is the richer per-tool join of each AI feature; include every feature you can name, '
			. 'with its plan tier, status, and whether it is native vs third-party. ai_features (the flat name list) '
			. 'is optional — the directory derives it from tool_features automatically. '
			. "\n\nSchema:\n"
			. '{'
			. '"tool":{"tool_id":"string, slug (lowercase, hyphens); derive from title if missing",'
			. '"title":"string","content":"string (HTML allowed)","excerpt":"string (one-line)",'
			. '"categories":["string"],"ai_features":["string"],"status":"draft"},'
			. '"meta":{"company_name":"string","website_url":"string","pricing_url":"string",'
			. '"initials":"string (<=8 chars)","avatar_color":"#RRGGBB","logo_url":"string",'
			. '"wp_org_slug":"string","subcategory":"string","founded_year":null,'
			. '"headquarters":"string",'
			. '"pricing_model":"one of: free, freemium, paid-only, subscription, one-time, bundle, discontinued",'
			. '"has_free_plan":0,'
			. '"starting_price_usd":null,"pricing_note":"string",'
			. '"ai_maturity_score":null,"ai_since":null,'
			. '"ai_type":"one of: native, third-party, both",'
			. '"ai_on_free_plan":0,'
			. '"total_ai_features":0,"native_ai_count":0,"thirdparty_ai_count":0,'
			. '"last_ai_update":"YYYY-MM-DD","last_verified":"YYYY-MM-DD",'
			. '"company_size_smb":0,"company_size_mid":0,"company_size_ent":0,'
			. '"company_group_id":"string (slug of the parent company; shared across sibling tools)",'
			. '"notable_customers":"string","best_for":"string","expert_take":"string",'
			. '"meta_title":"string","meta_description":"string","h1":"string"},'
			. '"plans":[{"plan_name":"string","tier_order":1,"price_usd_monthly":null,"price_usd_annual":null,'
			. '"billing_cycle":"one of: monthly, annual, one-time, free","user_limit":"string",'
			. '"feature_list":"string","notes":"string","is_free":0,"is_most_popular":0}],'
			. '"faq":[{"category_id":"","faq_order":1,"question":"string","answer":"string"}],'
			. '"radar":[{"change_date":"YYYY-MM-DD",'
			. '"change_type":"one of: launch, new-feature, upgraded, deprecated, pricing-change",'
			. '"what_changed":"string","why_it_matters":"string","source_url":"string","version_label":"string",'
			. '"confidence":"one of: low, medium, high","row_status":"one of: draft, verified, published"}],'
			. '"cannot_do":[{"order_index":1,"limitation_text":"string",'
			. '"workaround":"string — how teams work around this limitation, if known"}],'
			. '"relationships":[{"target_tool_id":"string (slug)",'
			. '"relationship_type":"one of: alternative, complement, competitor, integration","notes":"string"}],'
			. '"tool_features":[{"feature_name":"string, human name of the AI feature",'
			. '"feature_slug":"string, derived if omitted",'
			. '"plan":"one of: Free, Starter, Pro, Enterprise, Add-on",'
			. '"status":"one of: stable, beta, alpha, deprecated, coming-soon",'
			. '"is_native":1,"since":"YYYY or YYYY-MM-DD","feature_note":"string"}],'
			. '"maturity_breakdown":[{"order_index":1,"criterion":"string",'
			. '"score_display":"e.g. 2/2 or Yes","score_numeric":null,"score_color":"green|amber|red or hex"}],'
			. '"industries":[{"order_index":1,"name":"string","pct":0}],'
			. '"sentiment":[{"source":"G2 | Capterra | TrustRadius | ...",'
			. '"score":4.5,"review_count":0,"source_url":"string"}],'
			. '"quotes":[{"order_index":1,"quote_text":"string","role":"string — speaker role / context"}],'
			. '"category_meta":[{"term":"category name as used in categories[]",'
			. '"icon":"emoji","intro":"string","what_ai_does_well":"string","whats_immature":"string",'
			. '"honest_observation":"string","most_common_ai":"string","fastest_growing":"string",'
			. '"buyers_checklist":["string", "..."]}],'
			. '"feature_meta":[{"term":"feature name as used in tool_features / ai_features",'
			. '"color":"#RRGGBB","feature_type":"universal|category-specific",'
			. '"description":"string","why_matters":"string",'
			. '"evaluate":["question 1","question 2"],"related_features":["other feature name"],'
			. '"how_it_works_surface":"string","how_it_works_deeper":"string",'
			. '"vendors_dont_tell_you":["string","..."],"buyers_checklist":["string","..."]}]'
			. '}';
	}

	/**
	 * Make a single provider call. Returns the model's text, or WP_Error.
	 *
	 * @since 0.2.0
	 * @param string $user_message User text.
	 * @param int    $max_tokens   Token cap.
	 * @param string $system       System instruction (optional).
	 * @return string|WP_Error
	 */
	protected function call( $user_message, $max_tokens = 1000, $system = '' ) {
		if ( 'anthropic' === $this->provider ) {
			return $this->call_anthropic( $user_message, $max_tokens, $system );
		}
		return $this->call_openai( $user_message, $max_tokens, $system );
	}

	/**
	 * Anthropic Messages API.
	 *
	 * @since 0.2.0
	 * @param string $user_message User text.
	 * @param int    $max_tokens   Token cap.
	 * @param string $system       System instruction.
	 * @return string|WP_Error
	 */
	protected function call_anthropic( $user_message, $max_tokens, $system ) {
		$body = array(
			'model'      => $this->model,
			'max_tokens' => (int) $max_tokens,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => $user_message,
				),
			),
		);
		if ( '' !== $system ) {
			$body['system'] = $system;
		}

		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			array(
				'headers' => array(
					'x-api-key'         => $this->api_key,
					'anthropic-version' => '2023-06-01',
					'content-type'      => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'aifirst_http_error',
				sprintf( /* translators: 1: status, 2: body */ __( 'Anthropic HTTP %1$d: %2$s', 'aifirstsaas' ), $code, substr( $body, 0, 500 ) ),
				array( 'status' => $code )
			);
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || empty( $data['content'][0]['text'] ) ) {
			return new WP_Error( 'aifirst_bad_response', __( 'Unexpected Anthropic response shape.', 'aifirstsaas' ) );
		}
		return (string) $data['content'][0]['text'];
	}

	/**
	 * OpenAI Chat Completions API (JSON mode).
	 *
	 * @since 0.2.0
	 * @param string $user_message User text.
	 * @param int    $max_tokens   Token cap.
	 * @param string $system       System instruction.
	 * @return string|WP_Error
	 */
	protected function call_openai( $user_message, $max_tokens, $system ) {
		$messages = array();
		if ( '' !== $system ) {
			$messages[] = array(
				'role'    => 'system',
				'content' => $system,
			);
		}
		$messages[] = array(
			'role'    => 'user',
			'content' => $user_message,
		);

		$body = array(
			'model'           => $this->model,
			'messages'        => $messages,
			'max_tokens'      => (int) $max_tokens,
			'response_format' => array( 'type' => 'json_object' ),
		);

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'aifirst_http_error',
				sprintf( /* translators: 1: status, 2: body */ __( 'OpenAI HTTP %1$d: %2$s', 'aifirstsaas' ), $code, substr( $raw, 0, 500 ) ),
				array( 'status' => $code )
			);
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) || empty( $data['choices'][0]['message']['content'] ) ) {
			return new WP_Error( 'aifirst_bad_response', __( 'Unexpected OpenAI response shape.', 'aifirstsaas' ) );
		}
		return (string) $data['choices'][0]['message']['content'];
	}

	/**
	 * Extract a JSON object from a model reply, tolerating code fences.
	 *
	 * @since 0.2.0
	 * @param string $text Raw text.
	 * @return array|null
	 */
	protected function extract_json( $text ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return null;
		}

		$text = preg_replace( '/^```(?:json)?\s*/i', '', $text );
		$text = preg_replace( '/```$/', '', $text );
		$text = trim( $text );

		$decoded = json_decode( $text, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		// Last resort: grab between first { and last }.
		$first = strpos( $text, '{' );
		$last  = strrpos( $text, '}' );
		if ( false !== $first && false !== $last && $last > $first ) {
			$candidate = substr( $text, $first, $last - $first + 1 );
			$decoded   = json_decode( $candidate, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return null;
	}
}
