<?php
/**
 * OpenAI client for generating controlled site blueprints.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Tyese_AiSite_OpenAI {
    private $api_key;
    private $model;

    public function __construct( $api_key, $model = 'gpt-5-mini' ) {
        $this->api_key = trim( (string) $api_key );
        $this->model   = sanitize_text_field( $model ?: 'gpt-5-mini' );
    }

    public function has_key() {
        return '' !== $this->api_key;
    }

    public function generate_blueprint( $prompt, $reference_url, $widget_names, $brand_context = '' ) {
        if ( ! $this->has_key() ) {
            return new WP_Error( 'tyese_aisite_missing_key', __( 'Add an OpenAI API key before generating an AI blueprint.', 'tyese-aisite' ) );
        }

        $schema = ( new Tyese_AiSite_Blueprint() )->schema();
        $input  = $this->build_input( $prompt, $reference_url, $widget_names, $brand_context );

        $response = wp_remote_post(
            'https://api.openai.com/v1/responses',
            array(
                'timeout' => 90,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode(
                    array(
                        'model' => $this->model,
                        'input' => $input,
                        'text'  => array(
                            'format' => array(
                                'type'   => 'json_schema',
                                'name'   => 'tyese_aisite_blueprint',
                                'schema' => $schema,
                                'strict' => true,
                            ),
                        ),
                    )
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 > $code || 299 < $code ) {
            $message = $body['error']['message'] ?? __( 'OpenAI request failed.', 'tyese-aisite' );
            return new WP_Error( 'tyese_aisite_openai_error', sanitize_text_field( $message ) );
        }

        $json = $this->extract_output_text( $body );
        if ( ! $json ) {
            return new WP_Error( 'tyese_aisite_empty_response', __( 'OpenAI returned no blueprint content.', 'tyese-aisite' ) );
        }

        $decoded = json_decode( $json, true );
        if ( ! is_array( $decoded ) ) {
            return new WP_Error( 'tyese_aisite_bad_json', __( 'OpenAI returned a blueprint that could not be parsed.', 'tyese-aisite' ) );
        }

        return ( new Tyese_AiSite_Blueprint() )->normalize( $decoded );
    }

    private function build_input( $prompt, $reference_url, $widget_names, $brand_context ) {
        $available_widgets = implode( "\n", array_map( 'sanitize_text_field', (array) $widget_names ) );
        $reference_note    = $reference_url ? 'Reference URL for layout inspiration only: ' . esc_url_raw( $reference_url ) : 'No reference URL provided.';

        return array(
            array(
                'role'    => 'system',
                'content' => array(
                    array(
                        'type' => 'input_text',
                        'text' => 'You are Tyese aiSite, a WordPress Elementor site-planning assistant. Return only a complete JSON blueprint that matches the provided schema. Build with available Elementor and Tyese widgets. Do not copy protected brands, logos, proprietary text, unique images, trademarks, or IP from reference sites. Use references only for generic layout, section order, and UX inspiration. Prefer editable Elementor widgets over HTML. Use the HTML widget only when no safer widget exists.',
                    ),
                ),
            ),
            array(
                'role'    => 'user',
                'content' => array(
                    array(
                        'type' => 'input_text',
                        'text' => "User instructions:\n" . wp_strip_all_tags( $prompt ) . "\n\nBrand context:\n" . wp_strip_all_tags( $brand_context ) . "\n\n" . $reference_note . "\n\nAvailable widgets:\n" . $available_widgets,
                    ),
                ),
            ),
        );
    }

    private function extract_output_text( $body ) {
        if ( isset( $body['output_text'] ) && is_string( $body['output_text'] ) ) {
            return $body['output_text'];
        }

        foreach ( $body['output'] ?? array() as $item ) {
            foreach ( $item['content'] ?? array() as $content ) {
                if ( isset( $content['text'] ) && is_string( $content['text'] ) ) {
                    return $content['text'];
                }
            }
        }

        return '';
    }
}
