<?php
/**
 * Frontend class for SOSP Maps
 */
class SOSP_Maps_Frontend {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_shortcode( 'sosp_maps', array( $this, 'render_map' ) );
        add_shortcode( 'sosp_table', array( $this, 'render_table' ) );
        add_shortcode( 'sosp_admin', array( $this, 'render_admin_form' ) );

        // Frontend form submission (our own action, separate from WP admin)
        add_action( 'admin_post_nopriv_sosp_maps_frontend_save', array( $this, 'handle_frontend_save_group' ) );
        add_action( 'admin_post_sosp_maps_frontend_save', array( $this, 'handle_frontend_save_group' ) );

        // AJAX: URL scraping
        add_action( 'wp_ajax_sosp_maps_scrape_url', array( $this, 'handle_scrape_url' ) );
        add_action( 'wp_ajax_nopriv_sosp_maps_scrape_url', array( $this, 'handle_scrape_url' ) );

        // AJAX: email OTP
        add_action( 'wp_ajax_nopriv_sosp_maps_send_code', array( $this, 'handle_send_code' ) );
        add_action( 'wp_ajax_sosp_maps_send_code', array( $this, 'handle_send_code' ) );
        add_action( 'wp_ajax_nopriv_sosp_maps_verify_code', array( $this, 'handle_verify_code' ) );
        add_action( 'wp_ajax_sosp_maps_verify_code', array( $this, 'handle_verify_code' ) );
        add_action( 'wp_ajax_nopriv_sosp_maps_otp_logout', array( $this, 'handle_logout' ) );
        add_action( 'wp_ajax_sosp_maps_otp_logout', array( $this, 'handle_logout' ) );

        // Delete requests: anyone can request, only the admin (via mailed link) can approve
        add_action( 'wp_ajax_nopriv_sosp_maps_request_delete', array( $this, 'handle_request_delete' ) );
        add_action( 'wp_ajax_sosp_maps_request_delete', array( $this, 'handle_request_delete' ) );
        add_action( 'admin_post_nopriv_sosp_maps_delete_confirm', array( $this, 'handle_delete_confirm' ) );
        add_action( 'admin_post_sosp_maps_delete_confirm', array( $this, 'handle_delete_confirm' ) );
    }

    // -------------------------------------------------------------------------
    // Session / auth helpers
    // -------------------------------------------------------------------------

    private function get_session_token() {
        return isset( $_COOKIE['sosp_auth'] ) ? sanitize_text_field( $_COOKIE['sosp_auth'] ) : null;
    }

    private function get_auth_session() {
        $token = $this->get_session_token();
        if ( ! $token ) {
            return null;
        }
        return get_transient( 'sosp_session_' . $token );
    }

    private function is_authorized_user() {
        $session = $this->get_auth_session();
        return $session && ! empty( $session['email'] );
    }

    private function get_authorized_email() {
        $session = $this->get_auth_session();
        return ( $session && ! empty( $session['email'] ) ) ? $session['email'] : null;
    }

    // -------------------------------------------------------------------------
    // Asset enqueueing
    // -------------------------------------------------------------------------

    public function enqueue_assets() {
        if ( ( function_exists( 'is_front_page' ) && is_front_page() ) || ( is_singular() && has_shortcode( get_post()->post_content, 'sosp_maps' ) ) ) {
            // Leaflet (OpenStreetMap) and marker clustering, bundled locally in assets/lib
            $lib_url = SOSP_MAPS_PLUGIN_URL . 'assets/lib/';
            wp_enqueue_style( 'leaflet', $lib_url . 'leaflet/leaflet.css', array(), '1.9.4' );
            wp_enqueue_style( 'leaflet-markercluster', $lib_url . 'leaflet-markercluster/MarkerCluster.css', array( 'leaflet' ), '1.5.3' );
            wp_enqueue_style( 'leaflet-markercluster-default', $lib_url . 'leaflet-markercluster/MarkerCluster.Default.css', array( 'leaflet-markercluster' ), '1.5.3' );
            wp_enqueue_script( 'leaflet', $lib_url . 'leaflet/leaflet.js', array(), '1.9.4', true );
            wp_enqueue_script( 'leaflet-markercluster', $lib_url . 'leaflet-markercluster/leaflet.markercluster.js', array( 'leaflet' ), '1.5.3', true );

            wp_enqueue_script(
                'sosp-maps-frontend',
                SOSP_MAPS_PLUGIN_URL . 'assets/js/maps-frontend.js',
                array( 'leaflet', 'leaflet-markercluster' ),
                SOSP_MAPS_VERSION,
                true
            );

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

            wp_localize_script( 'sosp-maps-frontend', 'sosp_maps_data', array(
                'groups'     => $this->get_groups_for_map(),
                'plugin_url' => SOSP_MAPS_PLUGIN_URL,
            ) );

            wp_enqueue_style(
                'sosp-maps-frontend',
                SOSP_MAPS_PLUGIN_URL . 'assets/css/maps-frontend.css',
                array( 'leaflet-markercluster-default' ),
                SOSP_MAPS_VERSION
            );
        }
    }

    private function get_groups_for_map() {
        $groups    = SOSP_Maps_DB::get_all_groups();
        $formatted = array();
        foreach ( $groups as $group ) {
            $formatted[] = array(
                'id'      => $group->ID,
                'navn'    => $group->gr_navn,
                'adresse' => $group->gr_add,
                'mail'    => $group->gr_mail,
                'side'    => $group->gr_side,
                'lat'     => floatval( $group->gr_lat ),
                'lng'     => floatval( $group->gr_lng ),
            );
        }
        return $formatted;
    }

    // -------------------------------------------------------------------------
    // Map shortcode
    // -------------------------------------------------------------------------

    public function render_map() {
        $table_page = get_page_by_path( 'gruppeliste' );
        $admin_page = get_page_by_path( 'ret-kortet' );
        $table_url  = $table_page ? get_permalink( $table_page->ID ) : home_url( '/gruppeliste/' );
        $admin_url  = $admin_page ? get_permalink( $admin_page->ID ) : home_url( '/ret-kortet/' );
        return '<div id="sosp-map-canvas" style="width: 100%; height: 600px;"></div>'
             . '<p style="text-align:center;margin-top:10px;">'
             . '<a href="' . esc_url( $table_url ) . '">Vis som tabel</a>'
             . ' &nbsp;|&nbsp; '
             . '<a href="' . esc_url( $admin_url ) . '">Ret kortet her</a>'
             . ' &nbsp;|&nbsp; '
             . '<a href="https://github.com/NKJensen/sosp-internetgruppen/blob/main/README.md" target="_blank">FAQ</a>'
             . '</p>';
    }

    public function render_table() {
        $groups  = SOSP_Maps_DB::get_all_groups();
        $map_url = home_url( '/' );
        ob_start();
        ?>
        <p><a href="<?php echo esc_url( $map_url ); ?>">&larr; Vis som kort</a></p>
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:#f5f5f5;text-align:left;">
                    <th style="padding:8px;border:1px solid #ddd;">Gruppe</th>
                    <th style="padding:8px;border:1px solid #ddd;">Adresse</th>
                    <th style="padding:8px;border:1px solid #ddd;">E-mail</th>
                    <th style="padding:8px;border:1px solid #ddd;">Hjemmeside</th>
                    <th style="padding:8px;border:1px solid #ddd;width:1%;"><span class="screen-reader-text">Slet</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $groups as $group ) : ?>
                <tr>
                    <td style="padding:8px;border:1px solid #ddd;"><?php echo esc_html( $group->gr_navn ); ?></td>
                    <td style="padding:8px;border:1px solid #ddd;"><?php echo esc_html( $group->gr_add ); ?></td>
                    <td style="padding:8px;border:1px solid #ddd;">
                        <?php if ( $group->gr_mail ) : ?>
                            <a href="mailto:<?php echo esc_attr( $group->gr_mail ); ?>"><?php echo esc_html( $group->gr_mail ); ?></a>
                        <?php endif; ?>
                    </td>
                    <td style="padding:8px;border:1px solid #ddd;">
                        <?php if ( $group->gr_side ) : ?>
                            <a href="<?php echo esc_url( $group->gr_side ); ?>" target="_blank"><?php echo esc_html( $group->gr_side ); ?></a>
                        <?php endif; ?>
                    </td>
                    <td style="padding:8px;border:1px solid #ddd;text-align:center;">
                        <button type="button" class="sosp-delete-btn" data-id="<?php echo intval( $group->ID ); ?>"
                                title="Anmod om sletning af <?php echo esc_attr( $group->gr_navn ); ?>"
                                aria-label="Anmod om sletning af <?php echo esc_attr( $group->gr_navn ); ?>"
                                style="background:none;border:none;cursor:pointer;font-size:1.2em;padding:0;">&#128465;</button>
                    </td>
                </tr>
                <tr class="sosp-delete-row" id="sosp-delete-row-<?php echo intval( $group->ID ); ?>" style="display:none;">
                    <td colspan="5" style="padding:12px;border:1px solid #ddd;background:#fff8e1;">
                        <p style="margin:0 0 8px;"><strong>Anmod om sletning af <?php echo esc_html( $group->gr_navn ); ?></strong><br>
                        Gruppen slettes først, når administrator har godkendt anmodningen.</p>
                        <p style="margin:0 0 8px;">
                            <label>Begrundelse (valgfri)<br>
                            <textarea class="sosp-delete-reason" rows="2" maxlength="500" style="width:100%;"></textarea></label>
                        </p>
                        <p style="margin:0 0 8px;">
                            <label>Din e-mail (valgfri, hvis vi skal kunne svare dig)<br>
                            <input type="email" class="sosp-delete-email" maxlength="100" style="width:100%;"></label>
                        </p>
                        <button type="button" class="sosp-delete-send">Send anmodning</button>
                        <button type="button" class="sosp-delete-cancel">Annullér</button>
                        <div class="sosp-delete-status" style="display:none;margin-top:8px;padding:10px;border-radius:4px;"></div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <script>
        (function() {
            var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

            function showStatus(el, msg, ok) {
                el.style.display = 'block';
                el.style.background = ok ? '#d4edda' : '#f8d7da';
                el.style.border = '1px solid ' + (ok ? '#28a745' : '#dc3545');
                el.textContent = msg;
            }

            document.querySelectorAll('.sosp-delete-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var row = document.getElementById('sosp-delete-row-' + btn.getAttribute('data-id'));
                    row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
                });
            });

            document.querySelectorAll('.sosp-delete-row').forEach(function(row) {
                var id      = row.id.replace('sosp-delete-row-', '');
                var sendBtn = row.querySelector('.sosp-delete-send');
                var status  = row.querySelector('.sosp-delete-status');

                row.querySelector('.sosp-delete-cancel').addEventListener('click', function() {
                    row.style.display = 'none';
                });

                sendBtn.addEventListener('click', function() {
                    sendBtn.disabled = true;
                    sendBtn.textContent = 'Sender...';

                    var data = new FormData();
                    data.append('action', 'sosp_maps_request_delete');
                    data.append('group_id', id);
                    data.append('reason', row.querySelector('.sosp-delete-reason').value);
                    data.append('email', row.querySelector('.sosp-delete-email').value);

                    fetch(ajaxUrl, { method: 'POST', body: data })
                        .then(function(r) { return r.json(); })
                        .then(function(res) {
                            if (res.success) {
                                showStatus(status, res.data.message, true);
                                sendBtn.style.display = 'none';
                            } else {
                                showStatus(status, res.data.message, false);
                                sendBtn.disabled = false;
                                sendBtn.textContent = 'Send anmodning';
                            }
                        })
                        .catch(function() {
                            showStatus(status, 'Noget gik galt. Prøv igen senere.', false);
                            sendBtn.disabled = false;
                            sendBtn.textContent = 'Send anmodning';
                        });
                });
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // Admin form shortcode
    // -------------------------------------------------------------------------

    public function render_admin_form() {
        $can_submit = $this->is_authorized_user();
        $email      = $this->get_authorized_email();
        $ajax_url   = admin_url( 'admin-ajax.php' );
        ob_start();
        ?>
        <div id="sosp-admin-form" style="max-width:600px;margin:20px auto;padding:15px;border:1px solid #ddd;background:#fff;">
            <h2>Tilføj / Rediger søspejdergruppe</h2>

            <?php if ( $can_submit ) : ?>

                <div style="padding:10px;background:#d4edda;border:1px solid #28a745;border-radius:4px;margin-bottom:15px;">
                    Logget ind som <strong><?php echo esc_html( $email ); ?></strong>.
                    <button id="sosp-logout-btn" style="margin-left:10px;">Log ud</button>
                </div>

                <?php if ( ! empty( $_GET['sosp_error'] ) ) : ?>
                <div style="padding:10px;background:#f8d7da;border:1px solid #dc3545;border-radius:4px;margin-bottom:15px;">
                    <?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['sosp_error'] ) ) ); ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="sosp_maps_frontend_save">

                    <div id="sosp-url-scraper" style="margin-bottom:30px;padding:15px;background:#f9f9f9;border:1px solid #eee;border-radius:4px;">
                        <h3 style="margin-top:0;">Hent data fra gruppe-URL</h3>
                        <div style="display:flex;gap:10px;margin-bottom:10px;">
                            <input type="url" id="sosp_page_url" placeholder="https://eksempel.dk/gruppe/" style="flex:1;padding:8px;border:1px solid #ddd;border-radius:4px;">
                            <button type="button" id="sosp_fetch_btn" class="button button-secondary" style="white-space:nowrap;">Hent data</button>
                        </div>
                        <div id="sosp_fetch_status" style="display:none;padding:10px;margin:10px 0;border-radius:4px;"></div>
                    </div>

                    <table class="form-table">
                        <tr>
                            <th><label for="gr_navn">Gruppens navn *</label></th>
                            <td><input type="text" id="gr_navn" name="gr_navn" required class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="gr_add">Adresse *</label></th>
                            <td><input type="text" id="gr_add" name="gr_add" required class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="gr_mail">E-mail</label></th>
                            <td><input type="email" id="gr_mail" name="gr_mail" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="gr_side">Hjemmeside URL</label></th>
                            <td><input type="url" id="gr_side" name="gr_side" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="gr_lat">Bredde (latitude)</label></th>
                            <td>
                                <input type="number" id="gr_lat" name="gr_lat" step="0.00000001" class="regular-text">
                                <p class="description">Hvis tomt, udfyldes automatisk fra adressen.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="gr_lng">Længde (longitude)</label></th>
                            <td>
                                <input type="number" id="gr_lng" name="gr_lng" step="0.00000001" class="regular-text">
                                <p class="description">Hvis tomt, udfyldes automatisk fra adressen.</p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary">Gem gruppe</button>
                    </p>
                </form>

            <?php else : ?>

                <div id="sosp-otp-section">
                    <p>Indtast din e-mailadresse for at modtage en engangskode.</p>

                    <div id="sosp-email-step">
                        <div style="display:flex;gap:10px;margin-bottom:10px;">
                            <input type="email" id="sosp-auth-email" placeholder="din@email.dk" style="flex:1;padding:8px;border:1px solid #ddd;border-radius:4px;">
                            <button id="sosp-send-code-btn" class="button button-primary">Send kode</button>
                        </div>
                        <div id="sosp-email-status"></div>
                    </div>

                    <div id="sosp-code-step" style="display:none;margin-top:15px;">
                        <p>Indtast den 6-cifrede kode, der er sendt til <strong id="sosp-sent-to"></strong>:</p>
                        <div style="display:flex;gap:10px;margin-bottom:10px;">
                            <input type="text" id="sosp-auth-code" placeholder="123456" maxlength="6" style="flex:1;padding:8px;border:1px solid #ddd;border-radius:4px;letter-spacing:4px;font-size:1.2em;">
                            <button id="sosp-verify-code-btn" class="button button-primary">Bekræft kode</button>
                        </div>
                        <div id="sosp-code-status"></div>
                        <a href="#" id="sosp-resend-link" style="font-size:0.9em;">Send koden igen</a>
                    </div>
                </div>

            <?php endif; ?>
        </div>

        <script>
        (function() {
            var ajaxUrl = '<?php echo esc_js( $ajax_url ); ?>';
            var verificationId = null;

            function showStatus(el, msg, ok) {
                el.style.display = 'block';
                el.style.padding = '10px';
                el.style.borderRadius = '4px';
                el.style.marginTop = '8px';
                el.style.background = ok ? '#d4edda' : '#f8d7da';
                el.style.border = '1px solid ' + (ok ? '#28a745' : '#dc3545');
                el.textContent = msg;
            }

            <?php if ( ! $can_submit ) : ?>
            var sendBtn    = document.getElementById('sosp-send-code-btn');
            var verifyBtn  = document.getElementById('sosp-verify-code-btn');
            var resendLink = document.getElementById('sosp-resend-link');

            function sendCode() {
                var email = document.getElementById('sosp-auth-email').value;
                var statusEl = document.getElementById('sosp-email-status');
                sendBtn.disabled = true;
                sendBtn.textContent = 'Sender...';

                var data = new FormData();
                data.append('action', 'sosp_maps_send_code');
                data.append('email', email);

                fetch(ajaxUrl, { method: 'POST', body: data })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        sendBtn.disabled = false;
                        sendBtn.textContent = 'Send kode';
                        if (res.success) {
                            verificationId = res.data.verification_id;
                            document.getElementById('sosp-sent-to').textContent = email;
                            document.getElementById('sosp-email-step').style.display = 'none';
                            document.getElementById('sosp-code-step').style.display = 'block';
                            document.getElementById('sosp-auth-code').focus();
                        } else {
                            showStatus(statusEl, res.data.message, false);
                        }
                    });
            }

            sendBtn.addEventListener('click', sendCode);

            resendLink.addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('sosp-email-step').style.display = 'block';
                document.getElementById('sosp-code-step').style.display = 'none';
                document.getElementById('sosp-email-status').style.display = 'none';
            });

            verifyBtn.addEventListener('click', function() {
                var code = document.getElementById('sosp-auth-code').value;
                var statusEl = document.getElementById('sosp-code-status');
                verifyBtn.disabled = true;
                verifyBtn.textContent = 'Bekræfter...';

                var data = new FormData();
                data.append('action', 'sosp_maps_verify_code');
                data.append('verification_id', verificationId);
                data.append('code', code);

                fetch(ajaxUrl, { method: 'POST', body: data })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        verifyBtn.disabled = false;
                        verifyBtn.textContent = 'Bekræft kode';
                        if (res.success) {
                            showStatus(statusEl, 'Godkendt! Siden indlæses...', true);
                            setTimeout(function() { location.reload(); }, 800);
                        } else {
                            showStatus(statusEl, res.data.message, false);
                        }
                    });
            });

            document.getElementById('sosp-auth-code').addEventListener('keydown', function(e) {
                if (e.key === 'Enter') verifyBtn.click();
            });
            <?php endif; ?>

            <?php if ( $can_submit ) : ?>
            var logoutBtn = document.getElementById('sosp-logout-btn');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', function() {
                    var data = new FormData();
                    data.append('action', 'sosp_maps_otp_logout');
                    fetch(ajaxUrl, { method: 'POST', body: data })
                        .then(function() { location.reload(); });
                });
            }
            <?php endif; ?>
        })();
        </script>
        <?php

        // Enqueue form helper for URL scraping (only needed when authorized)
        if ( $can_submit ) {
            wp_enqueue_script(
                'sosp-maps-form',
                SOSP_MAPS_PLUGIN_URL . 'assets/js/form-helper.js',
                array( 'jquery' ),
                SOSP_MAPS_VERSION,
                true
            );
            wp_localize_script( 'sosp-maps-form', 'sosp_maps_form', array(
                'ajax_url' => $ajax_url,
            ) );
        }

        return ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // AJAX: send OTP code
    // -------------------------------------------------------------------------

    public function handle_send_code() {
        $email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';

        if ( ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => 'Ugyldig e-mailadresse.' ) );
        }

        $code            = str_pad( random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
        $verification_id = wp_generate_password( 32, false );

        set_transient( 'sosp_code_' . $verification_id, array(
            'email' => $email,
            'code'  => $code,
        ), 15 * MINUTE_IN_SECONDS );

        $subject = 'Din adgangskode til SOSP Kort';
        $message = "Din engangskode er: $code\n\nKoden er gyldig i 15 minutter.";
        wp_mail( $email, $subject, $message );

        wp_send_json_success( array( 'verification_id' => $verification_id ) );
    }

    // -------------------------------------------------------------------------
    // AJAX: verify OTP code
    // -------------------------------------------------------------------------

    public function handle_verify_code() {
        $verification_id = isset( $_POST['verification_id'] ) ? sanitize_text_field( $_POST['verification_id'] ) : '';
        $code            = isset( $_POST['code'] ) ? sanitize_text_field( $_POST['code'] ) : '';

        $stored = get_transient( 'sosp_code_' . $verification_id );
        if ( ! $stored ) {
            wp_send_json_error( array( 'message' => 'Koden er udløbet eller ugyldig. Prøv igen.' ) );
        }

        if ( $stored['code'] !== $code ) {
            wp_send_json_error( array( 'message' => 'Forkert kode. Prøv igen.' ) );
        }

        delete_transient( 'sosp_code_' . $verification_id );

        $session_token = wp_generate_password( 64, false );
        set_transient( 'sosp_session_' . $session_token, array(
            'email' => $stored['email'],
        ), 24 * HOUR_IN_SECONDS );

        setcookie( 'sosp_auth', $session_token, time() + 24 * HOUR_IN_SECONDS, '/', '', is_ssl(), true );

        wp_send_json_success( array( 'message' => 'Godkendt!' ) );
    }

    // -------------------------------------------------------------------------
    // AJAX: logout
    // -------------------------------------------------------------------------

    public function handle_logout() {
        $token = $this->get_session_token();
        if ( $token ) {
            delete_transient( 'sosp_session_' . $token );
        }
        setcookie( 'sosp_auth', '', time() - 3600, '/', '', is_ssl(), true );
        wp_send_json_success();
    }

    // -------------------------------------------------------------------------
    // AJAX: request deletion of a group (mails an approval link to the admin)
    // -------------------------------------------------------------------------

    public function handle_request_delete() {
        $group_id = isset( $_POST['group_id'] ) ? intval( $_POST['group_id'] ) : 0;
        $reason   = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';
        $email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $reason   = mb_substr( $reason, 0, 500 );

        $group = SOSP_Maps_DB::get_group( $group_id );
        if ( ! $group ) {
            wp_send_json_error( array( 'message' => 'Gruppen findes ikke (måske er den allerede slettet).' ) );
        }

        if ( $email && ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => 'E-mailadressen er ugyldig.' ) );
        }

        // Only one open request per group per day
        if ( get_transient( 'sosp_delreq_group_' . $group_id ) ) {
            wp_send_json_error( array( 'message' => 'Der er allerede sendt en anmodning om at slette denne gruppe. Administrator kigger på den.' ) );
        }

        // Max 5 requests per hour per visitor, so nobody can flood the admin's inbox
        $ip_key = 'sosp_delreq_ip_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '' );
        $count  = (int) get_transient( $ip_key );
        if ( $count >= 5 ) {
            wp_send_json_error( array( 'message' => 'Du har sendt for mange anmodninger. Prøv igen om en time.' ) );
        }

        $token = wp_generate_password( 40, false );
        set_transient( 'sosp_delete_' . $token, array(
            'group_id' => $group_id,
            'reason'   => $reason,
            'email'    => $email,
        ), 7 * DAY_IN_SECONDS );

        $link = add_query_arg( array(
            'action' => 'sosp_maps_delete_confirm',
            'token'  => $token,
        ), admin_url( 'admin-post.php' ) );

        $subject = 'Anmodning om sletning: ' . $group->gr_navn;
        $message = "Nogen har anmodet om at slette en gruppe fra søspejderkortet.\n\n"
                 . 'Gruppe:     ' . $group->gr_navn . "\n"
                 . 'Adresse:    ' . $group->gr_add . "\n"
                 . 'Hjemmeside: ' . $group->gr_side . "\n"
                 . 'E-mail:     ' . $group->gr_mail . "\n\n"
                 . 'Begrundelse: ' . ( $reason !== '' ? $reason : '(ingen)' ) . "\n"
                 . 'Anmodet af:  ' . ( $email !== '' ? $email : '(ikke oplyst)' ) . "\n\n"
                 . "Åbn linket herunder for at se gruppen og godkende sletningen.\n"
                 . "Gruppen slettes først, når du trykker \"Bekræft sletning\" på siden.\n\n"
                 . $link . "\n\n"
                 . "Linket virker i 7 dage. Gør du ingenting, bliver gruppen ikke slettet.\n";

        $headers = array();
        if ( $email ) {
            $headers[] = 'Reply-To: ' . $email;
        }

        if ( ! wp_mail( SOSP_MAPS_ADMIN_EMAIL, $subject, $message, $headers ) ) {
            delete_transient( 'sosp_delete_' . $token );
            wp_send_json_error( array( 'message' => 'Anmodningen kunne ikke sendes. Prøv igen senere.' ) );
        }

        set_transient( 'sosp_delreq_group_' . $group_id, 1, DAY_IN_SECONDS );
        set_transient( $ip_key, $count + 1, HOUR_IN_SECONDS );

        wp_send_json_success( array( 'message' => 'Tak! Anmodningen er sendt til administrator, som skal godkende sletningen.' ) );
    }

    // -------------------------------------------------------------------------
    // Approval page for delete requests (link from the admin mail)
    // GET shows a confirmation page; only the POST from its button deletes,
    // so mail scanners that pre-open links cannot delete anything.
    // -------------------------------------------------------------------------

    public function handle_delete_confirm() {
        $token   = isset( $_REQUEST['token'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', $_REQUEST['token'] ) : '';
        $request = $token ? get_transient( 'sosp_delete_' . $token ) : false;
        $title   = 'Slet gruppe';

        if ( ! $request ) {
            wp_die( '<p>Linket er udløbet eller allerede brugt.</p>', $title, array( 'response' => 410 ) );
        }

        $group = SOSP_Maps_DB::get_group( $request['group_id'] );
        if ( ! $group ) {
            delete_transient( 'sosp_delete_' . $token );
            wp_die( '<p>Gruppen findes ikke længere – den er måske allerede slettet.</p>', $title, array( 'response' => 200 ) );
        }

        if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
            SOSP_Maps_DB::delete_group( $group->ID );
            delete_transient( 'sosp_delete_' . $token );
            delete_transient( 'sosp_delreq_group_' . $group->ID );
            wp_die(
                '<p>Gruppen <strong>' . esc_html( $group->gr_navn ) . '</strong> er slettet fra kortet.</p>'
                . '<p><a href="' . esc_url( home_url( '/' ) ) . '">Gå til kortet</a></p>',
                $title,
                array( 'response' => 200 )
            );
        }

        $html = '<h1>Slet gruppe?</h1>'
              . '<p><strong>' . esc_html( $group->gr_navn ) . '</strong><br>'
              . esc_html( $group->gr_add ) . '<br>'
              . esc_html( $group->gr_side ) . '<br>'
              . esc_html( $group->gr_mail ) . '</p>'
              . '<p>Begrundelse: ' . esc_html( $request['reason'] !== '' ? $request['reason'] : '(ingen)' ) . '<br>'
              . 'Anmodet af: ' . esc_html( $request['email'] !== '' ? $request['email'] : '(ikke oplyst)' ) . '</p>'
              . '<form method="POST" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
              . '<input type="hidden" name="action" value="sosp_maps_delete_confirm">'
              . '<input type="hidden" name="token" value="' . esc_attr( $token ) . '">'
              . '<button type="submit" class="button" style="background:#dc3545;color:#fff;border-color:#dc3545;">Bekræft sletning</button>'
              . '</form>'
              . '<p style="margin-top:15px;">Vil du ikke slette gruppen, så luk bare siden.</p>';

        wp_die( $html, $title, array( 'response' => 200 ) );
    }

    // -------------------------------------------------------------------------
    // Form submission handler
    // -------------------------------------------------------------------------

    public function handle_frontend_save_group() {
        if ( ! $this->is_authorized_user() ) {
            wp_die( 'Du har ikke adgang til at gemme ændringer.' );
        }

        $data = array(
            'gr_navn' => isset( $_POST['gr_navn'] ) ? sanitize_text_field( $_POST['gr_navn'] ) : '',
            'gr_add'  => isset( $_POST['gr_add'] ) ? sanitize_text_field( $_POST['gr_add'] ) : '',
            'gr_mail' => isset( $_POST['gr_mail'] ) ? sanitize_email( $_POST['gr_mail'] ) : '',
            'gr_side' => isset( $_POST['gr_side'] ) ? esc_url_raw( $_POST['gr_side'] ) : '',
            'gr_lat'  => isset( $_POST['gr_lat'] ) ? floatval( $_POST['gr_lat'] ) : 0,
            'gr_lng'  => isset( $_POST['gr_lng'] ) ? floatval( $_POST['gr_lng'] ) : 0,
        );

        if ( isset( $_POST['ID'] ) ) {
            $data['ID'] = intval( $_POST['ID'] );
        }

        if ( empty( $data['gr_lat'] ) || empty( $data['gr_lng'] ) || ( $data['gr_lat'] == 0 && $data['gr_lng'] == 0 ) ) {
            $geo = SOSP_Maps_DB::geocode_address( $data['gr_add'] );
            if ( $geo ) {
                $data['gr_lat'] = $geo['lat'];
                $data['gr_lng'] = $geo['lng'];
            }
        }

        $data['updated_by'] = $this->get_authorized_email();

        $result   = SOSP_Maps_DB::save_group( $data );
        $redirect = wp_get_referer() ? wp_get_referer() : home_url();

        if ( is_wp_error( $result ) ) {
            $redirect = add_query_arg( 'sosp_error', urlencode( $result->get_error_message() ), $redirect );
        }

        wp_redirect( $redirect );
        exit;
    }

    // -------------------------------------------------------------------------
    // AJAX: URL scraping
    // -------------------------------------------------------------------------

    public function handle_scrape_url() {
        $url = isset( $_POST['url'] ) ? esc_url_raw( $_POST['url'] ) : '';

        if ( empty( $url ) ) {
            wp_send_json_error( array( 'message' => 'URL er påkrævet.' ) );
        }

        $data = SOSP_Maps_DB::scrape_group_from_url( $url );

        if ( ! $data ) {
            wp_send_json_error( array( 'message' => 'Kunne ikke hente data fra URL. Kontroller at siden indeholder gruppe-informationer.' ) );
        }

        wp_send_json_success( $data );
    }
}
?>
