<?php
/**
 * Frivilligbørsen - "Vi søger", "Jeg søger" and "Jeg tilbyder" posts.
 *
 * Posting requires the same e-mail/one-time-code login as "Ret kortet".
 * The author's contact address is never shown; visitors write through a
 * contact form and the site forwards the message.
 */
class SOSP_Frivillig {

    const DB_VERSION   = '1';
    const MAX_MONTHS   = 36;
    const DEFAULT_MONTHS = 3;
    const PAGE_OPTION  = 'sosp_friv_pages';

    public static $types = array(
        'vi-soeger'    => 'Vi søger',
        'jeg-soeger'   => 'Jeg søger',
        'jeg-tilbyder' => 'Jeg tilbyder',
    );

    public static $regions = array(
        'Nordjylland',
        'Midtjylland',
        'Sydjylland',
        'Fyn',
        'Sjælland',
        'Hovedstaden',
        'Bornholm',
        'Hele landet',
    );

    public static $durations = array( 1, 2, 3, 6, 9, 12, 18, 24, 36 );

    public function __construct() {
        add_action( 'init', array( __CLASS__, 'maybe_install' ) );
        add_shortcode( 'sosp_frivillig', array( $this, 'render_shortcode' ) );

        // Own posts (logged in)
        add_action( 'admin_post_nopriv_sosp_friv_save', array( $this, 'handle_save' ) );
        add_action( 'admin_post_sosp_friv_save', array( $this, 'handle_save' ) );
        add_action( 'admin_post_nopriv_sosp_friv_own', array( $this, 'handle_own_action' ) );
        add_action( 'admin_post_sosp_friv_own', array( $this, 'handle_own_action' ) );

        // Contact form and delete requests (anyone)
        add_action( 'wp_ajax_nopriv_sosp_friv_contact', array( $this, 'handle_contact' ) );
        add_action( 'wp_ajax_sosp_friv_contact', array( $this, 'handle_contact' ) );
        add_action( 'wp_ajax_nopriv_sosp_friv_request_delete', array( $this, 'handle_request_delete' ) );
        add_action( 'wp_ajax_sosp_friv_request_delete', array( $this, 'handle_request_delete' ) );

        // Links from e-mails (GET shows a page, only its POST button acts)
        add_action( 'admin_post_nopriv_sosp_friv_delete_confirm', array( $this, 'handle_delete_confirm' ) );
        add_action( 'admin_post_sosp_friv_delete_confirm', array( $this, 'handle_delete_confirm' ) );
        add_action( 'admin_post_nopriv_sosp_friv_extend', array( $this, 'handle_extend_link' ) );
        add_action( 'admin_post_sosp_friv_extend', array( $this, 'handle_extend_link' ) );

        // Daily reminder before posts expire
        add_action( 'sosp_friv_daily', array( __CLASS__, 'send_expiry_reminders' ) );
        add_action( 'init', array( __CLASS__, 'schedule_cron' ) );
    }

    // -------------------------------------------------------------------------
    // Install: table + pages (runs once per DB_VERSION, also on plain file upload)
    // -------------------------------------------------------------------------

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'sosp_frivillig';
    }

    public static function maybe_install() {
        if ( get_option( 'sosp_friv_db_version' ) === self::DB_VERSION ) {
            return;
        }
        self::create_table();
        self::create_pages();
        update_option( 'sosp_friv_db_version', self::DB_VERSION );
    }

    public static function create_table() {
        global $wpdb;
        $table           = self::table();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            type varchar(20) NOT NULL,
            titel varchar(150) NOT NULL,
            navn varchar(100) DEFAULT '' NOT NULL,
            gruppe_id mediumint(9) DEFAULT NULL,
            landsdel varchar(30) NOT NULL,
            by_navn varchar(100) DEFAULT '' NOT NULL,
            beskrivelse text NOT NULL,
            kontakt varchar(100) NOT NULL,
            kontakt_type varchar(10) DEFAULT 'email' NOT NULL,
            varighed_mdr tinyint(3) unsigned DEFAULT 3 NOT NULL,
            reminded tinyint(1) DEFAULT 0 NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime DEFAULT NULL,
            expires_at datetime NOT NULL,
            PRIMARY KEY  (ID),
            KEY type_expires (type,expires_at),
            KEY kontakt (kontakt)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function create_pages() {
        $pages = get_option( self::PAGE_OPTION, array() );

        $parent = isset( $pages['oversigt'] ) ? get_post( $pages['oversigt'] ) : null;
        if ( ! $parent || $parent->post_status === 'trash' ) {
            $existing = get_page_by_path( 'frivilligboersen' );
            $pages['oversigt'] = $existing ? $existing->ID : wp_insert_post( array(
                'post_title'   => 'Frivilligbørsen',
                'post_name'    => 'frivilligboersen',
                'post_content' => '<!-- wp:shortcode -->[sosp_frivillig]<!-- /wp:shortcode -->',
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ) );
        }

        foreach ( self::$types as $slug => $title ) {
            $page = isset( $pages[ $slug ] ) ? get_post( $pages[ $slug ] ) : null;
            if ( $page && $page->post_status !== 'trash' ) {
                continue;
            }
            $existing = get_page_by_path( 'frivilligboersen/' . $slug );
            $pages[ $slug ] = $existing ? $existing->ID : wp_insert_post( array(
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_parent'  => $pages['oversigt'],
                'post_content' => '<!-- wp:shortcode -->[sosp_frivillig type="' . $slug . '"]<!-- /wp:shortcode -->',
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ) );
        }

        update_option( self::PAGE_OPTION, $pages );
    }

    public static function page_url( $key ) {
        $pages = get_option( self::PAGE_OPTION, array() );
        if ( ! empty( $pages[ $key ] ) && get_post_status( $pages[ $key ] ) === 'publish' ) {
            return get_permalink( $pages[ $key ] );
        }
        return home_url( $key === 'oversigt' ? '/frivilligboersen/' : '/frivilligboersen/' . $key . '/' );
    }

    public static function schedule_cron() {
        if ( ! wp_next_scheduled( 'sosp_friv_daily' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'sosp_friv_daily' );
        }
    }

    // -------------------------------------------------------------------------
    // Data helpers
    // -------------------------------------------------------------------------

    private static function now() {
        return current_time( 'mysql' );
    }

    private static function expiry_from_now( $months ) {
        return gmdate( 'Y-m-d H:i:s', strtotime( '+' . intval( $months ) . ' months', current_time( 'timestamp' ) ) );
    }

    public static function get_post( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE ID = %d', $id ) );
    }

    /**
     * Active posts of one type (or all types), newest first.
     * A region filter also includes posts for "Hele landet".
     */
    public static function get_active( $type = null, $region = '', $limit = 0 ) {
        global $wpdb;
        $where = array( 'expires_at > %s' );
        $args  = array( self::now() );
        if ( $type ) {
            $where[] = 'type = %s';
            $args[]  = $type;
        }
        if ( $region ) {
            $where[] = '(landsdel = %s OR landsdel = %s)';
            $args[]  = $region;
            $args[]  = 'Hele landet';
        }
        $sql = 'SELECT * FROM ' . self::table() . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY created_at DESC';
        if ( $limit ) {
            $sql .= ' LIMIT ' . intval( $limit );
        }
        return $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
    }

    public static function get_own( $email, $type = null ) {
        global $wpdb;
        $sql  = 'SELECT * FROM ' . self::table() . ' WHERE kontakt = %s';
        $args = array( strtolower( $email ) );
        if ( $type ) {
            $sql   .= ' AND type = %s';
            $args[] = $type;
        }
        return $wpdb->get_results( $wpdb->prepare( $sql . ' ORDER BY created_at DESC', $args ) );
    }

    private static function group_names() {
        $names = array();
        foreach ( SOSP_Maps_DB::get_all_groups() as $g ) {
            $names[ $g->ID ] = $g->gr_navn;
        }
        return $names;
    }

    private static function is_owner( $post, $email ) {
        return $post && $email && strtolower( $post->kontakt ) === strtolower( $email );
    }

    /**
     * Per-session token against cross-site form posts (the login is a plain cookie).
     */
    private static function form_token() {
        $cookie = isset( $_COOKIE['sosp_auth'] ) ? $_COOKIE['sosp_auth'] : '';
        return substr( wp_hash( 'sosp_friv|' . $cookie ), 0, 20 );
    }

    private static function check_form_token() {
        $sent = isset( $_POST['sosp_token'] ) ? (string) $_POST['sosp_token'] : '';
        if ( ! hash_equals( self::form_token(), $sent ) ) {
            wp_die( 'Formularen er udløbet. Gå tilbage, genindlæs siden og prøv igen.', 'Frivilligbørsen', array( 'response' => 403 ) );
        }
    }

    /**
     * Max $max actions per hour per visitor IP for $what.
     * Returns false when the limit is reached, otherwise counts the action.
     */
    private static function rate_limit( $what, $max ) {
        $key   = 'sosp_friv_rl_' . $what . '_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '' );
        $count = (int) get_transient( $key );
        if ( $count >= $max ) {
            return false;
        }
        set_transient( $key, $count + 1, HOUR_IN_SECONDS );
        return true;
    }

    private static function post_heading( $post, $groups = null ) {
        $who = $post->navn;
        if ( $post->type === 'vi-soeger' && $post->gruppe_id ) {
            $groups = $groups ? $groups : self::group_names();
            $who    = isset( $groups[ $post->gruppe_id ] ) ? $groups[ $post->gruppe_id ] : '';
        }
        return array( $post->titel, $who );
    }

    private static function format_date( $mysql ) {
        return date_i18n( 'j. F Y', strtotime( $mysql ) );
    }

    // -------------------------------------------------------------------------
    // Shortcode [sosp_frivillig] (overview) / [sosp_frivillig type="..."]
    // -------------------------------------------------------------------------

    public function render_shortcode( $atts ) {
        $atts = shortcode_atts( array( 'type' => '' ), $atts );
        $type = isset( self::$types[ $atts['type'] ] ) ? $atts['type'] : '';

        wp_enqueue_style( 'sosp-frivillig', SOSP_MAPS_PLUGIN_URL . 'assets/css/frivillig.css', array(), SOSP_MAPS_VERSION );
        wp_enqueue_script( 'sosp-frivillig', SOSP_MAPS_PLUGIN_URL . 'assets/js/frivillig.js', array(), SOSP_MAPS_VERSION, true );
        wp_localize_script( 'sosp-frivillig', 'sosp_friv', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );

        return $type ? $this->render_type_page( $type ) : $this->render_overview();
    }

    private function render_notice() {
        $messages = array(
            'gemt'      => 'Dit opslag er gemt.',
            'slettet'   => 'Dit opslag er slettet.',
            'forlaenget'=> 'Dit opslag er forlænget.',
        );
        $key = isset( $_GET['friv_msg'] ) ? sanitize_key( $_GET['friv_msg'] ) : '';
        if ( isset( $messages[ $key ] ) ) {
            return '<div class="sosp-friv-notice">' . esc_html( $messages[ $key ] ) . '</div>';
        }
        if ( ! empty( $_GET['friv_error'] ) ) {
            return '<div class="sosp-friv-notice sosp-friv-error">' . esc_html( sanitize_text_field( wp_unslash( $_GET['friv_error'] ) ) ) . '</div>';
        }
        return '';
    }

    private function render_overview() {
        $groups = self::group_names();
        ob_start();
        echo $this->render_notice();
        ?>
        <div class="sosp-friv">
            <p class="sosp-friv-intro">Frivilligbørsen samler søspejdere, der søger hjælp, og frivillige, der søger en opgave eller vil tilbyde deres kompetencer.</p>
            <div class="sosp-friv-overview">
                <?php foreach ( self::$types as $slug => $title ) :
                    $posts = self::get_active( $slug, '', 5 ); ?>
                    <section class="sosp-friv-col">
                        <h2><a href="<?php echo esc_url( self::page_url( $slug ) ); ?>"><?php echo esc_html( $title ); ?></a></h2>
                        <?php if ( ! $posts ) : ?>
                            <p class="sosp-friv-empty">Ingen opslag endnu.</p>
                        <?php else : ?>
                            <ul>
                                <?php foreach ( $posts as $post ) :
                                    list( $heading, $who ) = self::post_heading( $post, $groups ); ?>
                                    <li>
                                        <a href="<?php echo esc_url( self::page_url( $slug ) . '#opslag-' . $post->ID ); ?>"><?php echo esc_html( $heading ); ?></a>
                                        <span><?php echo esc_html( implode( ' · ', array_filter( array( $who, $post->landsdel ) ) ) ); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <p><a class="sosp-friv-more" href="<?php echo esc_url( self::page_url( $slug ) ); ?>">Se alle og opret opslag &rarr;</a></p>
                    </section>
                <?php endforeach; ?>
            </div>
            <?php echo $this->render_own_section( null ); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_type_page( $type ) {
        $email  = SOSP_Maps_Frontend::current_email();
        $region = isset( $_GET['landsdel'] ) && in_array( wp_unslash( $_GET['landsdel'] ), self::$regions, true ) ? wp_unslash( $_GET['landsdel'] ) : '';
        $posts  = self::get_active( $type, $region );
        $groups = self::group_names();

        $edit = null;
        if ( $email && ! empty( $_GET['rediger'] ) ) {
            $candidate = self::get_post( intval( $_GET['rediger'] ) );
            if ( self::is_owner( $candidate, $email ) && $candidate->type === $type ) {
                $edit = $candidate;
            }
        }

        ob_start();
        echo $this->render_notice();
        ?>
        <div class="sosp-friv">
            <p><a href="<?php echo esc_url( self::page_url( 'oversigt' ) ); ?>">&larr; Frivilligbørsen</a></p>

            <form method="GET" class="sosp-friv-filter">
                <label>Landsdel
                    <select name="landsdel" onchange="this.form.submit()">
                        <option value="">Alle</option>
                        <?php foreach ( self::$regions as $r ) : ?>
                            <option <?php selected( $region, $r ); ?>><?php echo esc_html( $r ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <noscript><button type="submit">Filtrér</button></noscript>
                <a class="sosp-friv-button" href="#opret">Opret opslag</a>
            </form>

            <?php if ( ! $posts ) : ?>
                <p class="sosp-friv-empty">Der er ingen opslag<?php echo $region ? ' i ' . esc_html( $region ) : ''; ?> lige nu.</p>
            <?php endif; ?>

            <?php foreach ( $posts as $post ) {
                echo $this->render_card( $post, $groups );
            } ?>

            <section id="opret" class="sosp-friv-box">
                <h2><?php echo $edit ? 'Ret opslag' : 'Opret opslag under &quot;' . esc_html( self::$types[ $type ] ) . '&quot;'; ?></h2>
                <?php if ( $email ) : ?>
                    <?php echo SOSP_Maps_Frontend::render_session_bar( $email ); ?>
                    <?php echo $this->render_form( $type, $edit, $groups ); ?>
                <?php else : ?>
                    <?php echo SOSP_Maps_Frontend::render_login_box( 'Log ind med din e-mail for at oprette eller rette opslag. Du får en engangskode på mail. Din e-mail bliver ikke vist på siden.' ); ?>
                <?php endif; ?>
            </section>

            <?php echo $this->render_own_section( $type ); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_card( $post, $groups ) {
        list( $heading, $who ) = self::post_heading( $post, $groups );
        $place = trim( $post->landsdel . ( $post->by_navn ? ', ' . $post->by_navn : '' ) );
        ob_start();
        ?>
        <article class="sosp-friv-card" id="opslag-<?php echo intval( $post->ID ); ?>">
            <div class="sosp-friv-meta"><?php echo esc_html( self::$types[ $post->type ] . ' · ' . $place ); ?></div>
            <h3><?php echo esc_html( $heading ); ?></h3>
            <?php if ( $who ) : ?>
                <p class="sosp-friv-who"><?php echo esc_html( $who ); ?></p>
            <?php endif; ?>
            <?php if ( $post->beskrivelse !== '' ) : ?>
                <div class="sosp-friv-desc"><?php echo nl2br( esc_html( $post->beskrivelse ) ); ?></div>
            <?php endif; ?>
            <div class="sosp-friv-foot">
                <span>Oprettet <?php echo esc_html( self::format_date( $post->created_at ) ); ?></span>
                <span class="sosp-friv-actions">
                    <button type="button" class="sosp-friv-button" data-toggle="kontakt-<?php echo intval( $post->ID ); ?>">Kontakt</button>
                    <button type="button" class="sosp-friv-trash" data-toggle="slet-<?php echo intval( $post->ID ); ?>"
                            title="Anmod om at fjerne opslaget" aria-label="Anmod om at fjerne opslaget">&#128465;</button>
                </span>
            </div>

            <form class="sosp-friv-panel sosp-friv-contact" id="kontakt-<?php echo intval( $post->ID ); ?>" data-action="sosp_friv_contact" hidden>
                <input type="hidden" name="post_id" value="<?php echo intval( $post->ID ); ?>">
                <p><label>Dit navn *<input type="text" name="navn" maxlength="100" required></label></p>
                <p><label>Din e-mail * (så du kan få svar)<input type="email" name="email" maxlength="100" required></label></p>
                <p><label>Besked *<textarea name="besked" rows="4" maxlength="2000" required></textarea></label></p>
                <p class="sosp-friv-hp" aria-hidden="true"><label>Lad dette felt være tomt<input type="text" name="website" tabindex="-1" autocomplete="off"></label></p>
                <p class="sosp-friv-small">Beskeden sendes til den, der har lavet opslaget. Deres e-mail vises ikke.</p>
                <button type="submit">Send besked</button>
                <div class="sosp-friv-status" hidden></div>
            </form>

            <form class="sosp-friv-panel" id="slet-<?php echo intval( $post->ID ); ?>" data-action="sosp_friv_request_delete" hidden>
                <input type="hidden" name="post_id" value="<?php echo intval( $post->ID ); ?>">
                <p><strong>Anmod om at fjerne opslaget</strong><br>Opslaget fjernes først, når administrator har godkendt det.</p>
                <p><label>Begrundelse (valgfri)<textarea name="reason" rows="2" maxlength="500"></textarea></label></p>
                <p><label>Din e-mail (valgfri)<input type="email" name="email" maxlength="100"></label></p>
                <button type="submit">Send anmodning</button>
                <div class="sosp-friv-status" hidden></div>
            </form>
        </article>
        <?php
        return ob_get_clean();
    }

    private function render_form( $type, $edit, $groups ) {
        $v = array(
            'titel'        => $edit ? $edit->titel : '',
            'navn'         => $edit ? $edit->navn : '',
            'gruppe_id'    => $edit ? (int) $edit->gruppe_id : 0,
            'landsdel'     => $edit ? $edit->landsdel : '',
            'by_navn'      => $edit ? $edit->by_navn : '',
            'beskrivelse'  => $edit ? $edit->beskrivelse : '',
            'varighed_mdr' => $edit ? (int) $edit->varighed_mdr : self::DEFAULT_MONTHS,
        );
        $title_label = array(
            'vi-soeger'    => array( 'Hvad søger I? *', 'Fx Minileder i Horsens' ),
            'jeg-soeger'   => array( 'Funktion eller opgave *', 'Fx kasserer, bådsmand eller hjælp til sommerlejr' ),
            'jeg-tilbyder' => array( 'Hvad tilbyder du? *', 'Fx Ledertræning, Facilitering' ),
        );
        ob_start();
        ?>
        <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sosp-friv-form">
            <input type="hidden" name="action" value="sosp_friv_save">
            <input type="hidden" name="type" value="<?php echo esc_attr( $type ); ?>">
            <input type="hidden" name="sosp_token" value="<?php echo esc_attr( self::form_token() ); ?>">
            <?php if ( $edit ) : ?>
                <input type="hidden" name="ID" value="<?php echo intval( $edit->ID ); ?>">
            <?php endif; ?>

            <p><label><?php echo esc_html( $title_label[ $type ][0] ); ?>
                <input type="text" name="titel" maxlength="150" required value="<?php echo esc_attr( $v['titel'] ); ?>" placeholder="<?php echo esc_attr( $title_label[ $type ][1] ); ?>"></label></p>

            <?php if ( $type === 'vi-soeger' ) : ?>
                <p><label>Gruppe
                    <select name="gruppe_id">
                        <option value="0">– Vælg gruppe –</option>
                        <?php foreach ( $groups as $id => $name ) : ?>
                            <option value="<?php echo intval( $id ); ?>" <?php selected( $v['gruppe_id'], $id ); ?>><?php echo esc_html( $name ); ?></option>
                        <?php endforeach; ?>
                    </select></label></p>
            <?php else : ?>
                <p><label>Dit navn *
                    <input type="text" name="navn" maxlength="100" required value="<?php echo esc_attr( $v['navn'] ); ?>"></label></p>
            <?php endif; ?>

            <div class="sosp-friv-row">
                <p><label>Landsdel *
                    <select name="landsdel" required>
                        <option value="">– Vælg –</option>
                        <?php foreach ( self::$regions as $r ) : ?>
                            <option <?php selected( $v['landsdel'], $r ); ?>><?php echo esc_html( $r ); ?></option>
                        <?php endforeach; ?>
                    </select></label></p>
                <p><label>By (valgfri)
                    <input type="text" name="by_navn" maxlength="100" value="<?php echo esc_attr( $v['by_navn'] ); ?>"></label></p>
            </div>

            <p><label>Beskrivelse
                <textarea name="beskrivelse" rows="5" maxlength="3000"><?php echo esc_textarea( $v['beskrivelse'] ); ?></textarea></label></p>

            <p><label>Hvor længe skal opslaget vises?
                <select name="varighed_mdr">
                    <?php foreach ( self::$durations as $m ) : ?>
                        <option value="<?php echo intval( $m ); ?>" <?php selected( $v['varighed_mdr'], $m ); ?>><?php echo intval( $m ) . ( $m === 1 ? ' måned' : ' måneder' ); ?></option>
                    <?php endforeach; ?>
                </select></label></p>
            <?php if ( $edit ) : ?>
                <p class="sosp-friv-small">Når du gemmer, gælder den valgte periode fra i dag.</p>
            <?php endif; ?>

            <button type="submit" class="sosp-friv-button"><?php echo $edit ? 'Gem ændringer' : 'Opret opslag'; ?></button>
            <?php if ( $edit ) : ?>
                <a href="<?php echo esc_url( self::page_url( $type ) . '#mine' ); ?>">Annullér</a>
            <?php endif; ?>
        </form>
        <?php
        return ob_get_clean();
    }

    private function render_own_section( $type ) {
        $email = SOSP_Maps_Frontend::current_email();
        if ( ! $email ) {
            return '';
        }
        $posts = self::get_own( $email, $type );
        if ( ! $posts ) {
            return '';
        }
        $groups = self::group_names();
        $now    = self::now();
        ob_start();
        ?>
        <section id="mine" class="sosp-friv-box">
            <h2>Mine opslag</h2>
            <table class="sosp-friv-own">
                <thead><tr><th>Opslag</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ( $posts as $post ) :
                    list( $heading ) = self::post_heading( $post, $groups );
                    $active = $post->expires_at > $now; ?>
                    <tr>
                        <td><strong><?php echo esc_html( $heading ); ?></strong><br>
                            <span class="sosp-friv-small"><?php echo esc_html( self::$types[ $post->type ] . ' · ' . $post->landsdel ); ?></span></td>
                        <td><?php echo $active
                            ? 'Vises til ' . esc_html( self::format_date( $post->expires_at ) )
                            : '<strong>Udløbet</strong> ' . esc_html( self::format_date( $post->expires_at ) ); ?></td>
                        <td class="sosp-friv-own-actions">
                            <a href="<?php echo esc_url( add_query_arg( 'rediger', $post->ID, self::page_url( $post->type ) ) . '#opret' ); ?>">Ret</a>
                            <?php foreach ( array( 'forlaeng' => 'Forlæng', 'slet' => 'Slet' ) as $do => $label ) : ?>
                                <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                                      <?php if ( $do === 'slet' ) : ?>onsubmit="return confirm('Slet opslaget?');"<?php endif; ?>>
                                    <input type="hidden" name="action" value="sosp_friv_own">
                                    <input type="hidden" name="do" value="<?php echo esc_attr( $do ); ?>">
                                    <input type="hidden" name="ID" value="<?php echo intval( $post->ID ); ?>">
                                    <input type="hidden" name="sosp_token" value="<?php echo esc_attr( self::form_token() ); ?>">
                                    <button type="submit" class="sosp-friv-link"><?php echo esc_html( $label ); ?></button>
                                </form>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="sosp-friv-small">"Forlæng" viser opslaget i den samme periode igen, regnet fra i dag.</p>
        </section>
        <?php
        return ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // Create / update own post
    // -------------------------------------------------------------------------

    public function handle_save() {
        $email = SOSP_Maps_Frontend::current_email();
        if ( ! $email ) {
            wp_die( 'Du skal være logget ind for at oprette opslag.', 'Frivilligbørsen', array( 'response' => 403 ) );
        }
        self::check_form_token();

        $type = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : '';
        if ( ! isset( self::$types[ $type ] ) ) {
            wp_die( 'Ukendt type.', 'Frivilligbørsen', array( 'response' => 400 ) );
        }
        $back = self::page_url( $type );

        $field = function ( $name, $max, $textarea = false ) {
            $raw = isset( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : '';
            $val = $textarea ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
            return mb_substr( $val, 0, $max );
        };

        $months = isset( $_POST['varighed_mdr'] ) ? intval( $_POST['varighed_mdr'] ) : self::DEFAULT_MONTHS;
        $months = max( 1, min( self::MAX_MONTHS, $months ) );

        $data = array(
            'type'         => $type,
            'titel'        => $field( 'titel', 150 ),
            'navn'         => $type === 'vi-soeger' ? '' : $field( 'navn', 100 ),
            'gruppe_id'    => $type === 'vi-soeger' && ! empty( $_POST['gruppe_id'] ) ? intval( $_POST['gruppe_id'] ) : null,
            'landsdel'     => isset( $_POST['landsdel'] ) ? wp_unslash( $_POST['landsdel'] ) : '',
            'by_navn'      => $field( 'by_navn', 100 ),
            'beskrivelse'  => $field( 'beskrivelse', 3000, true ),
            'varighed_mdr' => $months,
            'expires_at'   => self::expiry_from_now( $months ),
            'reminded'     => 0,
        );

        $error = '';
        if ( $data['titel'] === '' ) {
            $error = 'Udfyld overskriften.';
        } elseif ( $type !== 'vi-soeger' && $data['navn'] === '' ) {
            $error = 'Udfyld dit navn.';
        } elseif ( ! in_array( $data['landsdel'], self::$regions, true ) ) {
            $error = 'Vælg en landsdel.';
        }

        global $wpdb;
        $id = isset( $_POST['ID'] ) ? intval( $_POST['ID'] ) : 0;

        if ( ! $error && $id ) {
            $existing = self::get_post( $id );
            if ( ! self::is_owner( $existing, $email ) ) {
                $error = 'Du kan kun rette dine egne opslag.';
            } else {
                $data['updated_at'] = self::now();
                $wpdb->update( self::table(), $data, array( 'ID' => $id ) );
            }
        } elseif ( ! $error ) {
            // Max 10 new posts per visitor per hour
            if ( ! self::rate_limit( 'save', 10 ) ) {
                $error = 'Du har oprettet mange opslag på kort tid. Prøv igen om en time.';
            } else {
                $data['kontakt']      = strtolower( $email );
                $data['kontakt_type'] = 'email';
                $data['created_at']   = self::now();
                $wpdb->insert( self::table(), $data );
                $id = $wpdb->insert_id;
            }
        }

        if ( $error ) {
            wp_safe_redirect( add_query_arg( 'friv_error', rawurlencode( $error ), $back ) . '#opret' );
            exit;
        }
        wp_safe_redirect( add_query_arg( 'friv_msg', 'gemt', $back ) . '#opslag-' . $id );
        exit;
    }

    // -------------------------------------------------------------------------
    // Extend / delete own post
    // -------------------------------------------------------------------------

    public function handle_own_action() {
        $email = SOSP_Maps_Frontend::current_email();
        if ( ! $email ) {
            wp_die( 'Du skal være logget ind.', 'Frivilligbørsen', array( 'response' => 403 ) );
        }
        self::check_form_token();

        $post = self::get_post( isset( $_POST['ID'] ) ? intval( $_POST['ID'] ) : 0 );
        if ( ! self::is_owner( $post, $email ) ) {
            wp_die( 'Du kan kun ændre dine egne opslag.', 'Frivilligbørsen', array( 'response' => 403 ) );
        }

        global $wpdb;
        $do   = isset( $_POST['do'] ) ? sanitize_key( $_POST['do'] ) : '';
        $back = self::page_url( $post->type );

        if ( $do === 'slet' ) {
            $wpdb->delete( self::table(), array( 'ID' => $post->ID ), array( '%d' ) );
            wp_safe_redirect( add_query_arg( 'friv_msg', 'slettet', $back ) . '#mine' );
            exit;
        }
        if ( $do === 'forlaeng' ) {
            self::extend( $post );
            wp_safe_redirect( add_query_arg( 'friv_msg', 'forlaenget', $back ) . '#mine' );
            exit;
        }
        wp_die( 'Ukendt handling.', 'Frivilligbørsen', array( 'response' => 400 ) );
    }

    private static function extend( $post ) {
        global $wpdb;
        $wpdb->update( self::table(), array(
            'expires_at' => self::expiry_from_now( $post->varighed_mdr ),
            'reminded'   => 0,
            'updated_at' => self::now(),
        ), array( 'ID' => $post->ID ) );
    }

    // -------------------------------------------------------------------------
    // AJAX: contact form (forwards the message, never reveals the address)
    // -------------------------------------------------------------------------

    public function handle_contact() {
        // Bots fill the hidden field; pretend success so they learn nothing
        if ( ! empty( $_POST['website'] ) ) {
            wp_send_json_success( array( 'message' => 'Tak! Din besked er sendt.' ) );
        }

        $post   = self::get_post( isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0 );
        $navn   = isset( $_POST['navn'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['navn'] ) ), 0, 100 ) : '';
        $email  = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $besked = isset( $_POST['besked'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['besked'] ) ), 0, 2000 ) : '';

        if ( ! $post || $post->expires_at <= self::now() ) {
            wp_send_json_error( array( 'message' => 'Opslaget findes ikke længere.' ) );
        }
        if ( $navn === '' || $besked === '' || ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => 'Udfyld navn, en gyldig e-mail og en besked.' ) );
        }
        if ( ! self::rate_limit( 'contact', 5 ) ) {
            wp_send_json_error( array( 'message' => 'Du har sendt mange beskeder på kort tid. Prøv igen om en time.' ) );
        }
        if ( $post->kontakt_type !== 'email' ) {
            wp_send_json_error( array( 'message' => 'Beskeden kunne ikke sendes.' ) );
        }

        list( $heading ) = self::post_heading( $post );
        $subject = 'Svar på dit opslag i Frivilligbørsen: ' . $heading;
        $message = $navn . ' har skrevet til dig om dit opslag "' . $heading . '" i Frivilligbørsen:' . "\n\n"
                 . $besked . "\n\n"
                 . '---' . "\n"
                 . 'Svar direkte på denne mail for at skrive til ' . $navn . ' (' . $email . ').' . "\n"
                 . 'Din egen e-mail er ikke blevet vist for afsenderen.' . "\n\n"
                 . 'Se og ret dine opslag: ' . self::page_url( $post->type ) . '#mine' . "\n";

        if ( ! wp_mail( $post->kontakt, $subject, $message, array( 'Reply-To: ' . $navn . ' <' . $email . '>' ) ) ) {
            wp_send_json_error( array( 'message' => 'Beskeden kunne ikke sendes. Prøv igen senere.' ) );
        }
        wp_send_json_success( array( 'message' => 'Tak! Din besked er sendt.' ) );
    }

    // -------------------------------------------------------------------------
    // Delete requests for other people's posts (admin approves via mail link)
    // -------------------------------------------------------------------------

    public function handle_request_delete() {
        $post   = self::get_post( isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0 );
        $reason = isset( $_POST['reason'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ), 0, 500 ) : '';
        $email  = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

        if ( ! $post ) {
            wp_send_json_error( array( 'message' => 'Opslaget findes ikke (måske er det allerede fjernet).' ) );
        }
        if ( $email && ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => 'E-mailadressen er ugyldig.' ) );
        }
        if ( get_transient( 'sosp_friv_delreq_' . $post->ID ) ) {
            wp_send_json_error( array( 'message' => 'Der er allerede sendt en anmodning om dette opslag. Administrator kigger på den.' ) );
        }
        if ( ! self::rate_limit( 'delreq', 5 ) ) {
            wp_send_json_error( array( 'message' => 'Du har sendt for mange anmodninger. Prøv igen om en time.' ) );
        }

        $token = wp_generate_password( 40, false );
        set_transient( 'sosp_friv_delete_' . $token, array(
            'post_id' => $post->ID,
            'reason'  => $reason,
            'email'   => $email,
        ), 7 * DAY_IN_SECONDS );

        $link = add_query_arg( array( 'action' => 'sosp_friv_delete_confirm', 'token' => $token ), admin_url( 'admin-post.php' ) );
        list( $heading, $who ) = self::post_heading( $post );

        $subject = 'Anmodning om at fjerne opslag: ' . $heading;
        $message = "Nogen har anmodet om at fjerne et opslag fra Frivilligbørsen.\n\n"
                 . 'Opslag:   ' . self::$types[ $post->type ] . ' – ' . $heading . "\n"
                 . 'Af:       ' . $who . ' (' . $post->kontakt . ")\n"
                 . 'Område:   ' . $post->landsdel . ( $post->by_navn ? ', ' . $post->by_navn : '' ) . "\n\n"
                 . 'Begrundelse: ' . ( $reason !== '' ? $reason : '(ingen)' ) . "\n"
                 . 'Anmodet af:  ' . ( $email !== '' ? $email : '(ikke oplyst)' ) . "\n\n"
                 . "Åbn linket for at se opslaget og godkende. Det fjernes først, når du trykker \"Bekræft sletning\".\n\n"
                 . $link . "\n\n"
                 . "Linket virker i 7 dage. Gør du ingenting, bliver opslaget ikke fjernet.\n";

        $headers = $email ? array( 'Reply-To: ' . $email ) : array();
        if ( ! wp_mail( SOSP_MAPS_ADMIN_EMAIL, $subject, $message, $headers ) ) {
            delete_transient( 'sosp_friv_delete_' . $token );
            wp_send_json_error( array( 'message' => 'Anmodningen kunne ikke sendes. Prøv igen senere.' ) );
        }

        set_transient( 'sosp_friv_delreq_' . $post->ID, 1, DAY_IN_SECONDS );
        wp_send_json_success( array( 'message' => 'Tak! Anmodningen er sendt til administrator.' ) );
    }

    public function handle_delete_confirm() {
        $token   = isset( $_REQUEST['token'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', $_REQUEST['token'] ) : '';
        $request = $token ? get_transient( 'sosp_friv_delete_' . $token ) : false;
        $title   = 'Fjern opslag';

        if ( ! $request ) {
            wp_die( '<p>Linket er udløbet eller allerede brugt.</p>', $title, array( 'response' => 410 ) );
        }
        $post = self::get_post( $request['post_id'] );
        if ( ! $post ) {
            delete_transient( 'sosp_friv_delete_' . $token );
            wp_die( '<p>Opslaget findes ikke længere – det er måske allerede fjernet.</p>', $title, array( 'response' => 200 ) );
        }
        list( $heading, $who ) = self::post_heading( $post );

        if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
            global $wpdb;
            $wpdb->delete( self::table(), array( 'ID' => $post->ID ), array( '%d' ) );
            delete_transient( 'sosp_friv_delete_' . $token );
            delete_transient( 'sosp_friv_delreq_' . $post->ID );
            wp_die(
                '<p>Opslaget <strong>' . esc_html( $heading ) . '</strong> er fjernet.</p>'
                . '<p><a href="' . esc_url( self::page_url( 'oversigt' ) ) . '">Gå til Frivilligbørsen</a></p>',
                $title,
                array( 'response' => 200 )
            );
        }

        $html = '<h1>Fjern opslag?</h1>'
              . '<p>' . esc_html( self::$types[ $post->type ] ) . '<br><strong>' . esc_html( $heading ) . '</strong><br>'
              . esc_html( $who ) . ' (' . esc_html( $post->kontakt ) . ')<br>'
              . esc_html( $post->landsdel . ( $post->by_navn ? ', ' . $post->by_navn : '' ) ) . '</p>'
              . '<p>' . nl2br( esc_html( $post->beskrivelse ) ) . '</p>'
              . '<p>Begrundelse: ' . esc_html( $request['reason'] !== '' ? $request['reason'] : '(ingen)' ) . '<br>'
              . 'Anmodet af: ' . esc_html( $request['email'] !== '' ? $request['email'] : '(ikke oplyst)' ) . '</p>'
              . self::confirm_button( 'sosp_friv_delete_confirm', $token, 'Bekræft sletning', '#dc3545' )
              . '<p style="margin-top:15px;">Vil du ikke fjerne opslaget, så luk bare siden.</p>';
        wp_die( $html, $title, array( 'response' => 200 ) );
    }

    private static function confirm_button( $action, $token, $label, $color ) {
        return '<form method="POST" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
             . '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">'
             . '<input type="hidden" name="token" value="' . esc_attr( $token ) . '">'
             . '<button type="submit" class="button" style="background:' . $color . ';color:#fff;border-color:' . $color . ';">' . esc_html( $label ) . '</button>'
             . '</form>';
    }

    // -------------------------------------------------------------------------
    // Expiry reminders (daily cron) and one-click extend link
    // -------------------------------------------------------------------------

    public static function send_expiry_reminders() {
        global $wpdb;
        $now  = self::now();
        $soon = gmdate( 'Y-m-d H:i:s', strtotime( '+7 days', current_time( 'timestamp' ) ) );
        $posts = $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . " WHERE reminded = 0 AND kontakt_type = 'email' AND expires_at > %s AND expires_at <= %s",
            $now,
            $soon
        ) );

        foreach ( $posts as $post ) {
            $token = wp_generate_password( 40, false );
            set_transient( 'sosp_friv_extend_' . $token, $post->ID, 30 * DAY_IN_SECONDS );
            $link = add_query_arg( array( 'action' => 'sosp_friv_extend', 'token' => $token ), admin_url( 'admin-post.php' ) );
            list( $heading ) = self::post_heading( $post );

            $subject = 'Dit opslag i Frivilligbørsen udløber snart';
            $message = 'Dit opslag "' . $heading . '" under "' . self::$types[ $post->type ] . '" holder op med at blive vist '
                     . self::format_date( $post->expires_at ) . ".\n\n"
                     . 'Vil du have det vist i ' . $post->varighed_mdr . ' måned(er) mere, så åbn linket og tryk "Forlæng":' . "\n\n"
                     . $link . "\n\n"
                     . "Gør du ingenting, bliver opslaget skjult. Du kan altid forlænge det senere under \"Mine opslag\".\n";

            wp_mail( $post->kontakt, $subject, $message );
            $wpdb->update( self::table(), array( 'reminded' => 1 ), array( 'ID' => $post->ID ) );
        }
    }

    public function handle_extend_link() {
        $token   = isset( $_REQUEST['token'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', $_REQUEST['token'] ) : '';
        $post_id = $token ? get_transient( 'sosp_friv_extend_' . $token ) : false;
        $post    = $post_id ? self::get_post( $post_id ) : null;
        $title   = 'Forlæng opslag';

        if ( ! $post ) {
            wp_die( '<p>Linket er udløbet, eller opslaget findes ikke længere.</p>', $title, array( 'response' => 410 ) );
        }
        list( $heading ) = self::post_heading( $post );

        if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
            self::extend( $post );
            delete_transient( 'sosp_friv_extend_' . $token );
            $post = self::get_post( $post->ID );
            wp_die(
                '<p>Opslaget <strong>' . esc_html( $heading ) . '</strong> vises nu til ' . esc_html( self::format_date( $post->expires_at ) ) . '.</p>'
                . '<p><a href="' . esc_url( self::page_url( $post->type ) ) . '">Gå til ' . esc_html( self::$types[ $post->type ] ) . '</a></p>',
                $title,
                array( 'response' => 200 )
            );
        }

        wp_die(
            '<h1>Forlæng opslag?</h1>'
            . '<p><strong>' . esc_html( $heading ) . '</strong><br>Vises nu til ' . esc_html( self::format_date( $post->expires_at ) )
            . '. Forlænges med ' . intval( $post->varighed_mdr ) . ' måned(er) fra i dag.</p>'
            . self::confirm_button( 'sosp_friv_extend', $token, 'Forlæng', '#0073aa' ),
            $title,
            array( 'response' => 200 )
        );
    }
}
