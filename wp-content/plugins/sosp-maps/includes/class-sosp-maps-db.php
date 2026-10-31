<?php
/**
 * Database class for SOSP Maps
 */
class SOSP_Maps_DB {
    
    public static function create_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sosp_groups';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            ID mediumint(9) NOT NULL AUTO_INCREMENT,
            gr_navn varchar(100) NOT NULL,
            gr_side varchar(255) DEFAULT '',
            gr_lat decimal(10, 8) NOT NULL,
            gr_lng decimal(11, 8) NOT NULL,
            gr_add varchar(255) DEFAULT '',
            gr_mail varchar(100) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_by varchar(100) DEFAULT '',
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY  (ID)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        // Add columns to existing tables that predate this schema
        $wpdb->query( "ALTER TABLE $table_name ADD COLUMN IF NOT EXISTS updated_by varchar(100) DEFAULT ''" );
        $wpdb->query( "ALTER TABLE $table_name ADD COLUMN IF NOT EXISTS updated_at datetime DEFAULT NULL" );
    }

    /**
     * Geocode an address using OpenStreetMap Nominatim (limited to Denmark).
     * Returns array with 'lat' and 'lng' on success, or false on failure.
     *
     * Nominatim handles Danish "Vej 1, 8000 Aarhus C" poorly (the district letter
     * and city name often give no hits, and the postcode is only a soft hint).
     * So we try a few query variants and only accept a hit whose postcode matches.
     * As a last resort the group is placed at the centre of its postcode area.
     *
     * Nominatim usage policy: max 1 request/second and an identifying User-Agent.
     * See https://operations.osmfoundation.org/policies/nominatim/
     */
    public static function geocode_address( $address ) {
        $address = trim( (string) $address );
        if ( $address === '' ) {
            return false;
        }

        // Split "Vejnavn 12, 8000 By" into street and postcode
        $postcode = preg_match( '/\b(\d{4})\b/', $address, $m ) ? $m[1] : '';
        $parts    = array_map( 'trim', explode( ',', $address ) );
        $street   = $parts[0];
        if ( count( $parts ) === 1 && $postcode ) {
            $street = trim( strstr( $address, $postcode, true ) );
        }

        $queries = array();
        if ( $street && $postcode ) {
            $queries[] = array( 'q' => $street . ', ' . $postcode );
        }
        $queries[] = array( 'q' => $address );
        if ( $street && $street !== $address ) {
            $queries[] = array( 'q' => $street );
        }
        if ( $postcode ) {
            $queries[] = array( 'postalcode' => $postcode, 'country' => 'dk' );
        }
        $queries = array_unique( $queries, SORT_REGULAR );

        foreach ( array_values( $queries ) as $i => $query ) {
            if ( $i > 0 ) {
                sleep( 1 );
            }
            $hit = self::nominatim_search( $query, $postcode );
            if ( $hit ) {
                return $hit;
            }
        }

        return false;
    }

    /**
     * Run one Nominatim search. Returns the first hit (matching $postcode, if given)
     * as array with 'lat' and 'lng', or false.
     */
    private static function nominatim_search( $query, $postcode ) {
        $url = add_query_arg( array_map( 'rawurlencode', array_merge( $query, array(
            'format'         => 'jsonv2',
            'countrycodes'   => 'dk',
            'addressdetails' => 1,
            'limit'          => 5,
        ) ) ), 'https://nominatim.openstreetmap.org/search' );

        $response = wp_remote_get( $url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent'      => 'sosp-maps/' . SOSP_MAPS_VERSION . ' (' . home_url( '/' ) . ')',
                'Accept-Language' => 'da',
            ),
        ) );
        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return false;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ) );
        if ( empty( $data ) || ! is_array( $data ) ) {
            return false;
        }

        foreach ( $data as $hit ) {
            if ( ! isset( $hit->lat, $hit->lon ) ) {
                continue;
            }
            $hit_postcode = isset( $hit->address->postcode ) ? $hit->address->postcode : '';
            if ( $postcode && $hit_postcode !== $postcode ) {
                continue;
            }
            return array( 'lat' => floatval( $hit->lat ), 'lng' => floatval( $hit->lon ) );
        }

        return false;
    }
    
    /**
     * Get all groups
     */
    public static function get_all_groups() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sosp_groups';
        
        return $wpdb->get_results( "SELECT * FROM $table_name ORDER BY gr_navn" );
    }
    
    /**
     * Get single group by ID
     */
    public static function get_group( $id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sosp_groups';
        
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE ID = %d", $id ) );
    }
    
    /**
     * Normalize a URL to its bare hostname (no www, no scheme, no trailing slash).
     * Used to detect duplicate groups across minor URL variations.
     */
    private static function normalize_url_host( $url ) {
        $url = trim( $url );
        if ( empty( $url ) ) {
            return '';
        }
        if ( ! preg_match( '~^https?://~i', $url ) ) {
            $url = 'https://' . $url;
        }
        $host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
        return preg_replace( '/^www\./', '', $host );
    }

    /**
     * Find an existing group by URL host match.
     * Returns the matching row, or null if none found.
     * Pass $exclude_id to ignore a specific group (useful on updates).
     */
    public static function find_by_url_host( $url, $exclude_id = null ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sosp_groups';
        $host       = self::normalize_url_host( $url );
        if ( empty( $host ) ) {
            return null;
        }
        $groups = $wpdb->get_results( "SELECT ID, gr_navn, gr_side FROM $table_name" );
        foreach ( $groups as $group ) {
            if ( $exclude_id && (int) $group->ID === (int) $exclude_id ) {
                continue;
            }
            if ( self::normalize_url_host( $group->gr_side ) === $host ) {
                return $group;
            }
        }
        return null;
    }

    /**
     * Insert or update group
     */
    public static function save_group( $data ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sosp_groups';
        
        // Define format for each column
        $format = array(
            'gr_navn'    => '%s',
            'gr_side'    => '%s',
            'gr_lat'     => '%f',
            'gr_lng'     => '%f',
            'gr_add'     => '%s',
            'gr_mail'    => '%s',
            'updated_by' => '%s',
            'updated_at' => '%s',
        );

        // Always stamp who changed the record and when
        $data['updated_at'] = current_time( 'mysql' );

        // Duplicate URL check
        if ( ! empty( $data['gr_side'] ) ) {
            $exclude_id = isset( $data['ID'] ) ? $data['ID'] : null;
            $existing   = self::find_by_url_host( $data['gr_side'], $exclude_id );
            if ( $existing ) {
                return new WP_Error(
                    'duplicate_url',
                    sprintf( 'En gruppe med den URL findes allerede: %s (%s)', $existing->gr_navn, $existing->gr_side )
                );
            }
        }

        if ( isset( $data['ID'] ) ) {
            unset( $data['created_at'] );
            // Build format array only for fields being updated
            $update_format = array();
            foreach ( $data as $key => $value ) {
                if ( $key !== 'ID' && isset( $format[ $key ] ) ) {
                    $update_format[ $key ] = $format[ $key ];
                }
            }
            return $wpdb->update( $table_name, $data, array( 'ID' => $data['ID'] ), $update_format, array( '%d' ) );
        } else {
            unset( $data['ID'] );
            // Build format array only for fields being inserted
            $insert_format = array();
            foreach ( $data as $key => $value ) {
                if ( isset( $format[ $key ] ) ) {
                    $insert_format[ $key ] = $format[ $key ];
                }
            }
            return $wpdb->insert( $table_name, $data, $insert_format );
        }
    }
    
    /**
     * Delete group
     */
    public static function delete_group( $id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sosp_groups';
        
        return $wpdb->delete( $table_name, array( 'ID' => $id ), array( '%d' ) );
    }

    /**
     * Scrape group data from a URL.
     * Looks for organization name, address, email in the HTML structure.
     * Returns array with: navn, add, mail, side
     */
    public static function scrape_group_from_url( $url ) {
        if ( empty( $url ) ) {
            return false;
        }

        $response = wp_remote_get( $url, array( 'timeout' => 10 ) );
        if ( is_wp_error( $response ) ) {
            return false;
        }

        $html = wp_remote_retrieve_body( $response );
        if ( empty( $html ) ) {
            return false;
        }

        // Use DOMDocument to parse HTML safely
        $dom = new DOMDocument();
        @$dom->loadHTML( '<?xml encoding="UTF-8">' . $html );
        $xpath = new DOMXPath( $dom );

        $data = array(
            'navn' => '',
            'add' => '',
            'mail' => '',
            'side' => $url,
        );

        // Extract organization name (from <span class="organization">)
        $org_nodes = $xpath->query( "//span[@class='organization']" );
        if ( $org_nodes->length > 0 ) {
            $data['navn'] = trim( $org_nodes->item( 0 )->textContent );
        }

        // Extract address lines
        $address_lines = array();
        
        // Get address line 1
        $addr1 = $xpath->query( "//span[@class='address-line1']" );
        if ( $addr1->length > 0 ) {
            $address_lines[] = trim( $addr1->item( 0 )->textContent );
        }

        // Get postal code and locality
        $postal = $xpath->query( "//span[@class='postal-code']" );
        $locality = $xpath->query( "//span[@class='locality']" );
        if ( $postal->length > 0 && $locality->length > 0 ) {
            $address_lines[] = trim( $postal->item( 0 )->textContent ) . ' ' . trim( $locality->item( 0 )->textContent );
        }

        if ( ! empty( $address_lines ) ) {
            $data['add'] = implode( ', ', $address_lines );
        }

        // Extract email (from mailto link)
        $mailto_links = $xpath->query( "//a[starts-with(@href, 'mailto:')]" );
        if ( $mailto_links->length > 0 ) {
            $href = $mailto_links->item( 0 )->getAttribute( 'href' );
            $email = str_replace( 'mailto:', '', $href );
            $data['mail'] = sanitize_email( $email );
        }

        return ( $data['navn'] || $data['add'] || $data['mail'] ) ? $data : false;
    }
}
?>
