<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CannyForge_Hreflang_Helpers {
    public static function get_languages() {
        return array(
            'ar' => 'Arabic',
            'cs' => 'Czech',
            'da' => 'Danish',
            'de' => 'German',
            'el' => 'Greek',
            'en' => 'English',
            'es' => 'Spanish',
            'fi' => 'Finnish',
            'fr' => 'French',
            'he' => 'Hebrew',
            'hi' => 'Hindi',
            'hu' => 'Hungarian',
            'id' => 'Indonesian',
            'it' => 'Italian',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'nl' => 'Dutch',
            'no' => 'Norwegian',
            'pl' => 'Polish',
            'pt' => 'Portuguese',
            'pt-BR' => 'Portuguese (Brazil)',
            'ro' => 'Romanian',
            'ru' => 'Russian',
            'sv' => 'Swedish',
            'th' => 'Thai',
            'tr' => 'Turkish',
            'uk' => 'Ukrainian',
            'vi' => 'Vietnamese',
            'zh' => 'Chinese',
            'zh-CN' => 'Chinese (Simplified)',
            'zh-TW' => 'Chinese (Traditional)',
            'es-MX' => 'Spanish (Mexico)',
            'es-AR' => 'Spanish (Argentina)',
            'fr-CA' => 'French (Canada)',
        );
    }

    public static function get_regions() {
        return array(
            ''   => 'None',
            'AE' => 'United Arab Emirates',
            'AR' => 'Argentina',
            'AT' => 'Austria',
            'AU' => 'Australia',
            'BE' => 'Belgium',
            'BR' => 'Brazil',
            'CA' => 'Canada',
            'CH' => 'Switzerland',
            'CL' => 'Chile',
            'CO' => 'Colombia',
            'CN' => 'China',
            'CZ' => 'Czech Republic',
            'DE' => 'Germany',
            'DK' => 'Denmark',
            'EG' => 'Egypt',
            'ES' => 'Spain',
            'FI' => 'Finland',
            'FR' => 'France',
            'GB' => 'United Kingdom',
            'GR' => 'Greece',
            'HK' => 'Hong Kong',
            'HU' => 'Hungary',
            'ID' => 'Indonesia',
            'IE' => 'Ireland',
            'IL' => 'Israel',
            'IN' => 'India',
            'IT' => 'Italy',
            'JP' => 'Japan',
            'KE' => 'Kenya',
            'KR' => 'South Korea',
            'MX' => 'Mexico',
            'MY' => 'Malaysia',
            'NL' => 'Netherlands',
            'NO' => 'Norway',
            'NZ' => 'New Zealand',
            'PH' => 'Philippines',
            'PL' => 'Poland',
            'PT' => 'Portugal',
            'RO' => 'Romania',
            'RU' => 'Russia',
            'SA' => 'Saudi Arabia',
            'SE' => 'Sweden',
            'SG' => 'Singapore',
            'TH' => 'Thailand',
            'TR' => 'Turkey',
            'TW' => 'Taiwan',
            'UA' => 'Ukraine',
            'US' => 'United States',
            'VN' => 'Vietnam',
            'ZA' => 'South Africa',
            'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe',
        );
    }

    public static function sanitize_group( $value ) {
        $value = is_string( $value ) ? wp_unslash( $value ) : '';
        $value = sanitize_title( $value );
        return $value;
    }

    public static function sanitize_language( $value ) {
        $value     = is_string( $value ) ? strtolower( trim( wp_unslash( $value ) ) ) : '';
        $languages = self::get_languages();
        return isset( $languages[ $value ] ) ? $value : '';
    }

    public static function sanitize_region( $value ) {
        $value   = is_string( $value ) ? strtoupper( trim( wp_unslash( $value ) ) ) : '';
        $regions = self::get_regions();
        return isset( $regions[ $value ] ) ? $value : '';
    }

    public static function build_hreflang( $language, $region = '' ) {
        $language = self::sanitize_language( $language );
        $region   = self::sanitize_region( $region );

        if ( empty( $language ) ) {
            return '';
        }

        if ( empty( $region ) ) {
            return $language;
        }

        return $language . '-' . $region;
    }

    public static function esc_xml( $value ) {
        return esc_html( $value );
    }
}
