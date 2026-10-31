<?php
/**
 * Admin class for SOSP Maps
 */
class SOSP_Maps_Admin {
    
    public function __construct() {
        if ( is_admin() ) {
            add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
            add_action( 'admin_post_sosp_maps_save_group', array( $this, 'handle_save_group' ) );
            add_action( 'admin_init', array( $this, 'handle_delete_group' ) );
        }
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'SOSP Maps',
            'SOSP Maps',
            'manage_options',
            'sosp-maps',
            array( $this, 'display_groups_page' ),
            'dashicons-location',
            30
        );
    }
    
    public function display_groups_page() {
        ?>
        <div class="wrap">
            <h1>Søspejder Grupper - Kort Administration</h1>
            
            <div style="margin: 20px 0;">
                <a href="<?php echo admin_url( 'admin.php?page=sosp-maps&action=new' ); ?>" class="button button-primary">
                    + Tilføj ny gruppe
                </a>
            </div>
            
            <?php
            if ( isset( $_GET['action'] ) && $_GET['action'] === 'new' ) {
                $this->display_form( null );
            } elseif ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['id'] ) ) {
                $group = SOSP_Maps_DB::get_group( intval( $_GET['id'] ) );
                if ( $group ) {
                    $this->display_form( $group );
                }
            } else {
                $this->display_groups_table();
            }
            ?>
        </div>
        <?php
    }
    
    private function display_groups_table() {
        $groups = SOSP_Maps_DB::get_all_groups();
        
        echo '<table class="wp-list-table widefat striped">';
        echo '<thead><tr>';
        echo '<th>ID</th>';
        echo '<th>Gruppens navn</th>';
        echo '<th>Adresse</th>';
        echo '<th>E-mail</th>';
        echo '<th>Hjemmeside</th>';
        echo '<th>Koordinater</th>';
        echo '<th>Sidst rettet af</th>';
        echo '<th>Handlinger</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ( $groups as $group ) {
            echo '<tr>';
            echo '<td>' . esc_html( $group->ID ) . '</td>';
            echo '<td>' . esc_html( $group->gr_navn ) . '</td>';
            echo '<td>' . esc_html( $group->gr_add ) . '</td>';
            echo '<td>' . esc_html( $group->gr_mail ) . '</td>';
            echo '<td><a href="' . esc_url( $group->gr_side ) . '" target="_blank">' . esc_html( $group->gr_side ) . '</a></td>';
            echo '<td>' . esc_html( $group->gr_lat ) . ', ' . esc_html( $group->gr_lng ) . '</td>';
            $updated_by = ! empty( $group->updated_by ) ? esc_html( $group->updated_by ) : '—';
            $updated_at = ! empty( $group->updated_at ) ? esc_html( date_i18n( 'd/m/Y H:i', strtotime( $group->updated_at ) ) ) : '';
            echo '<td>' . $updated_by . ( $updated_at ? '<br><small>' . $updated_at . '</small>' : '' ) . '</td>';
            echo '<td>';
            echo '<a href="' . admin_url( 'admin.php?page=sosp-maps&action=edit&id=' . $group->ID ) . '" class="button button-small">Ret</a> ';
            echo '<a href="' . wp_nonce_url( admin_url( 'admin.php?page=sosp-maps&sosp_maps_action=delete&id=' . $group->ID ), 'sosp_maps_delete_' . $group->ID ) . '" class="button button-small button-link-delete" onclick="return confirm(\'Er du sikker?\')">Slet</a>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }
    
    private function display_form( $group = null ) {
        $id = $group ? $group->ID : '';
        $navn = $group ? $group->gr_navn : '';
        $adresse = $group ? $group->gr_add : '';
        $mail = $group ? $group->gr_mail : '';
        $side = $group ? $group->gr_side : '';
        $lat = $group ? $group->gr_lat : '';
        $lng = $group ? $group->gr_lng : '';
        $title = $group ? 'Rediger gruppe' : 'Tilføj ny gruppe';
        
        // Enqueue form helper script and styles
        wp_enqueue_script(
            'sosp-maps-form',
            SOSP_MAPS_PLUGIN_URL . 'assets/js/form-helper.js',
            array( 'jquery' ),
            SOSP_MAPS_VERSION,
            true
        );
        wp_localize_script( 'sosp-maps-form', 'sosp_maps_form', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
        ) );
        wp_enqueue_style(
            'sosp-maps-frontend',
            SOSP_MAPS_PLUGIN_URL . 'assets/css/maps-frontend.css',
            array(),
            SOSP_MAPS_VERSION
        );
        
        ?>
        <div style="max-width: 500px;">
            <h2><?php echo esc_html( $title ); ?></h2>
            
            <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'sosp_maps_save_group' ); ?>
                <input type="hidden" name="action" value="sosp_maps_save_group">
                <?php if ( $id ) : ?>
                    <input type="hidden" name="ID" value="<?php echo esc_attr( $id ); ?>">
                <?php endif; ?>
                
                <!-- URL Scraping Section -->
                <div id="sosp-url-scraper" style="margin-bottom:30px;padding:15px;background:#f9f9f9;border:1px solid #eee;border-radius:4px;">
                    <h3 style="margin-top:0;">Eller hent data fra gruppe-URL</h3>
                    <p style="margin:10px 0;">Indsæt URL til søspejdergruppens webside og klik "Hent data" for automatisk at udfylde felterne.</p>
                    <div style="display:flex;gap:10px;margin-bottom:10px;">
                        <input type="url" id="sosp_page_url" placeholder="https://eksempel.dk/gruppe/" style="flex:1;padding:8px;border:1px solid #ddd;border-radius:4px;">
                        <button type="button" id="sosp_fetch_btn" class="button button-secondary" style="white-space:nowrap;">Hent data</button>
                    </div>
                    <div id="sosp_fetch_status" style="display:none;padding:10px;margin:10px 0;border-radius:4px;"></div>
                </div>
                
                <table class="form-table">
                    <tr>
                        <th><label for="gr_navn">Gruppens navn *</label></th>
                        <td><input type="text" id="gr_navn" name="gr_navn" value="<?php echo esc_attr( $navn ); ?>" required class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="gr_add">Adresse *</label></th>
                        <td><input type="text" id="gr_add" name="gr_add" value="<?php echo esc_attr( $adresse ); ?>" required class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="gr_mail">E-mail</label></th>
                        <td><input type="email" id="gr_mail" name="gr_mail" value="<?php echo esc_attr( $mail ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="gr_side">Hjemmeside URL</label></th>
                        <td><input type="url" id="gr_side" name="gr_side" value="<?php echo esc_attr( $side ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="gr_lat">Bredde (latitude)</label></th>
                        <td>
                            <input type="number" id="gr_lat" name="gr_lat" value="<?php echo esc_attr( $lat ); ?>" step="0.00000001" class="regular-text">
                            <p class="description">Hvis tomt, udfyldes automatisk fra adressen.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gr_lng">Længde (longitude)</label></th>
                        <td>
                            <input type="number" id="gr_lng" name="gr_lng" value="<?php echo esc_attr( $lng ); ?>" step="0.00000001" class="regular-text">
                            <p class="description">Hvis tomt, udfyldes automatisk fra adressen.</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">Gem gruppe</button>
                    <a href="<?php echo admin_url( 'admin.php?page=sosp-maps' ); ?>" class="button">Annullér</a>
                </p>
            </form>
            
            <div style="margin-top: 30px; padding: 15px; background: #f5f5f5; border-radius: 4px;">
                <p><strong>Tip:</strong> Du kan finde koordinater ved at:</p>
                <ol>
                    <li>Gå til <a href="https://www.openstreetmap.org" target="_blank">OpenStreetMap</a></li>
                    <li>Søge efter adressen</li>
                    <li>Højreklikke på stedet og vælge "Vis adresse" — koordinaterne vises i venstre side</li>
                </ol>
            </div>
        </div>
        <?php
    }
    
    public function handle_save_group() {
        if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'sosp_maps_save_group' ) ) {
            wp_die( 'Sikkerhedstjek fejlede' );
        }
        
        $data = array(
            'gr_navn' => sanitize_text_field( $_POST['gr_navn'] ),
            'gr_add'  => sanitize_text_field( $_POST['gr_add'] ),
            'gr_mail' => sanitize_email( $_POST['gr_mail'] ),
            'gr_side' => esc_url_raw( $_POST['gr_side'] ),
            'gr_lat'  => floatval( $_POST['gr_lat'] ),
            'gr_lng'  => floatval( $_POST['gr_lng'] ),
        );
        
        if ( isset( $_POST['ID'] ) ) {
            $data['ID'] = intval( $_POST['ID'] );
        }

        // If coordinates are missing or zero, attempt server-side geocoding from the address
        if ( empty( $data['gr_lat'] ) || empty( $data['gr_lng'] ) || ( $data['gr_lat'] == 0 && $data['gr_lng'] == 0 ) ) {
            $geo = SOSP_Maps_DB::geocode_address( $data['gr_add'] );
            if ( $geo ) {
                $data['gr_lat'] = $geo['lat'];
                $data['gr_lng'] = $geo['lng'];
            }
        }
        
        SOSP_Maps_DB::save_group( $data );
        
        wp_redirect( admin_url( 'admin.php?page=sosp-maps' ) );
        exit;
    }
    
    public function handle_delete_group() {
        if ( ! isset( $_GET['sosp_maps_action'] ) || $_GET['sosp_maps_action'] !== 'delete' ) {
            return;
        }
        
        if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['id'] ) || ! isset( $_GET['_wpnonce'] ) ) {
            wp_die( 'Sikkerhedstjek fejlede' );
        }
        
        $id = intval( $_GET['id'] );
        
        if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'sosp_maps_delete_' . $id ) ) {
            wp_die( 'Sikkerhedstjek fejlede' );
        }
        
        SOSP_Maps_DB::delete_group( $id );
        
        wp_redirect( admin_url( 'admin.php?page=sosp-maps' ) );
        exit;
    }
}
?>
