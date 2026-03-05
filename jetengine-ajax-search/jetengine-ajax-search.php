<?php
/**
 * Plugin Name: JetEngine AJAX Experience Search
 * Description: AJAX search widget for JetEngine – searches "experience" CPT by description and country taxonomy with date range filtering.
 * Version: 1.0.0
 * Author: Custom
 * Text Domain: je-ajax-search
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'JE_SEARCH_VERSION', '1.0.0' );
define( 'JE_SEARCH_DIR',     plugin_dir_path( __FILE__ ) );
define( 'JE_SEARCH_URL',     plugin_dir_url( __FILE__ ) );

/* =========================================================
   1. ENQUEUE ASSETS
   ========================================================= */
add_action( 'wp_enqueue_scripts', 'je_search_enqueue' );
function je_search_enqueue() {
    wp_enqueue_style(
        'je-search-css',
        JE_SEARCH_URL . 'assets/search.css',
        [],
        JE_SEARCH_VERSION
    );

    // Flatpickr – lightweight date-range picker (no jQuery needed)
    wp_enqueue_style(
        'flatpickr-css',
        'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
        [],
        '4.6.13'
    );
    wp_enqueue_script(
        'flatpickr-js',
        'https://cdn.jsdelivr.net/npm/flatpickr',
        [],
        '4.6.13',
        true
    );

    wp_enqueue_script(
        'je-search-js',
        JE_SEARCH_URL . 'assets/search.js',
        [ 'flatpickr-js' ],
        JE_SEARCH_VERSION,
        true
    );

    wp_localize_script( 'je-search-js', 'jeSearch', [
        'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
        'nonce'     => wp_create_nonce( 'je_search_nonce' ),
        'perPage'   => 10,
        'i18n'      => [
            'noResults'   => __( 'No experiences found.', 'je-ajax-search' ),
            'loadMore'    => __( 'Load More', 'je-ajax-search' ),
            'loading'     => __( 'Searching…', 'je-ajax-search' ),
            'results'     => __( 'results', 'je-ajax-search' ),
            'clearDate'   => __( 'Clear dates', 'je-ajax-search' ),
        ],
    ] );
}

/* =========================================================
   2. SHORTCODE  [je_experience_search]
   ========================================================= */
add_shortcode( 'je_experience_search', 'je_search_render_shortcode' );
function je_search_render_shortcode( $atts ) {
    ob_start();
    $quick = je_search_quick_countries();
    ?>
    <div class="je-search-wrap" id="je-search-wrap" role="search" aria-label="<?php esc_attr_e( 'Experience search', 'je-ajax-search' ); ?>">

        <!-- ── Quick-select pills ────────────────────────── -->
        <div class="je-search__quick-bar" aria-label="<?php esc_attr_e( 'Popular destinations', 'je-ajax-search' ); ?>">
            <span class="je-search__quick-label"><?php esc_html_e( 'Popular:', 'je-ajax-search' ); ?></span>
            <?php foreach ( $quick as $q ) : ?>
            <button
                type="button"
                class="je-search__quick-pill"
                data-term-id="<?php echo esc_attr( $q['term_id'] ); ?>"
                data-term-name="<?php echo esc_attr( $q['name'] ); ?>"
                data-term-flag="<?php echo esc_attr( $q['flag'] ); ?>"
                aria-label="<?php echo esc_attr( $q['name'] ); ?>"
            >
                <?php if ( $q['flag'] ) : ?>
                    <img src="<?php echo esc_attr( $q['flag'] ); ?>" alt="" class="je-flag" aria-hidden="true">
                <?php else : ?>
                    <span class="je-flag-emoji" aria-hidden="true"><?php echo esc_html( $q['emoji'] ); ?></span>
                <?php endif; ?>
                <?php echo esc_html( $q['name'] ); ?>
            </button>
            <?php endforeach; ?>
        </div>

      

        <!-- ── Main search row ───────────────────────────── -->
        <div class="je-search__row">

            <!-- Country field -->
            <div class="je-search__field je-search__field--country" id="je-country-field">
                <label class="je-search__field-label" for="je-country-input">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    <?php esc_html_e( 'Destination', 'je-ajax-search' ); ?>
                </label>

                <!-- Token display area -->
                <div class="je-country-input-wrap">
                    <div class="je-country-tokens" id="je-country-tokens" aria-live="polite"></div>
                    <input
                        type="text"
                        id="je-country-input"
                        class="je-search__input je-search__input--country"
                        placeholder="<?php esc_attr_e( 'Search destinations…', 'je-ajax-search' ); ?>"
                        autocomplete="off"
                        aria-autocomplete="list"
                        aria-controls="je-country-dropdown"
                        aria-expanded="false"
                    >
                    <button type="button" class="je-country-clear" id="je-country-clear" aria-label="<?php esc_attr_e( 'Clear destination', 'je-ajax-search' ); ?>" hidden>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                <!-- Dropdown -->
                <div class="je-country-dropdown" id="je-country-dropdown" role="listbox" aria-label="<?php esc_attr_e( 'Countries', 'je-ajax-search' ); ?>" hidden>
                    <div class="je-country-dropdown__list" id="je-country-list"></div>
                    <div class="je-country-dropdown__empty" id="je-country-empty" hidden><?php esc_html_e( 'No destinations found.', 'je-ajax-search' ); ?></div>
                </div>
            </div>
    </div>
    
      <!-- ── Quick date periods ────────────────────────── -->
        <div class="je-date-periods" id="je-date-periods" aria-label="<?php esc_attr_e( 'Quick date periods', 'je-ajax-search' ); ?>">
            <button type="button" class="je-period-pill" data-period="weekend" style="display: none !important;"  ><?php esc_html_e( 'This weekend',  'je-ajax-search' ); ?></button>
            <button type="button" class="je-period-pill" data-period="next7"    ><?php esc_html_e( 'Next 7 days',   'je-ajax-search' ); ?></button>
            <button type="button" class="je-period-pill" data-period="next14"  style="display: none;"  ><?php esc_html_e( 'Next 2 weeks',  'je-ajax-search' ); ?></button>
            <button type="button" class="je-period-pill" data-period="nextmonth"><?php esc_html_e( 'Next month',    'je-ajax-search' ); ?></button>
            <button type="button" class="je-period-pill" data-period="next3m"   ><?php esc_html_e( 'Next 3 months', 'je-ajax-search' ); ?></button>
            <button type="button" class="je-period-pill je-period-pill--clear" id="je-period-clear" hidden><?php esc_html_e( 'Clear dates', 'je-ajax-search' ); ?></button>
        </div>
    
     <div class="je-search__row">
            <!-- Date range field (opens dual-month calendar) -->
            <div class="je-search__field je-search__field--date">
                <label class="je-search__field-label" for="je-date-range">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <?php esc_html_e( 'Dates', 'je-ajax-search' ); ?>
                </label>
                <div class="je-date-input-wrap">
                    <input
                        type="text"
                        id="je-date-range"
                        class="je-search__input je-search__input--date"
                        placeholder="<?php esc_attr_e( 'Select travel dates…', 'je-ajax-search' ); ?>"
                        readonly
                        aria-label="<?php esc_attr_e( 'Travel date range', 'je-ajax-search' ); ?>"
                    >
                    <button type="button" class="je-date-clear" id="je-date-clear" aria-label="<?php esc_attr_e( 'Clear dates', 'je-ajax-search' ); ?>" hidden>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
            </div>

            <!-- Search button -->
            <button type="button" class="je-search__btn" id="je-search-btn" aria-label="<?php esc_attr_e( 'Search experiences', 'je-ajax-search' ); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <span><?php esc_html_e( 'Search', 'je-ajax-search' ); ?></span>
            </button>

        </div><!-- /.je-search__row -->

        <!-- ── Results area ───────────────────────────────── -->
        <div class="je-search__results-wrap" id="je-search-results-wrap" aria-live="polite" aria-atomic="false">
            <div class="je-search__results-meta" id="je-results-meta" hidden></div>
            <div class="je-search__results-grid" id="je-results-grid"></div>
            <div class="je-search__load-more-wrap" id="je-load-more-wrap" hidden>
                <button type="button" class="je-search__load-more" id="je-load-more">
                    <?php esc_html_e( 'Load More', 'je-ajax-search' ); ?>
                </button>
            </div>
            <div class="je-search__spinner" id="je-spinner" hidden aria-hidden="true">
                <span class="je-spinner-ring"></span>
            </div>
            <div class="je-search__no-results" id="je-no-results" hidden>
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <p><?php esc_html_e( 'No experiences found. Try adjusting your search.', 'je-ajax-search' ); ?></p>
            </div>
        </div>

    </div><!-- /.je-search-wrap -->

    <!-- Countries data for JS -->
    <script id="je-countries-data" type="application/json"><?php echo wp_json_encode( je_search_get_all_countries() ); ?></script>
    <?php
    return ob_get_clean();
}

/* =========================================================
   3. HELPER – quick-select countries
   ========================================================= */
function je_search_quick_countries() {
    $defaults = [
        'egypt'    => [ 'emoji' => '🇪🇬', 'slug' => 'egypt' ],
        'turkey'   => [ 'emoji' => '🇹🇷', 'slug' => 'turkey' ],
        'greece'   => [ 'emoji' => '🇬🇷', 'slug' => 'greece' ],
        'thailand' => [ 'emoji' => '🇹🇭', 'slug' => 'thailand' ],
    ];

    $output = [];
    foreach ( $defaults as $key => $meta ) {
        $term = get_term_by( 'slug', $meta['slug'], 'country' );
        if ( ! $term || is_wp_error( $term ) ) {
            // Fallback: try by name
            $term = get_term_by( 'name', ucfirst( $key ), 'country' );
        }

        $term_id = $term ? $term->term_id : 0;
        $name    = $term ? $term->name    : ucfirst( $key );
        $flag    = '';

        if ( $term_id ) {
            $flag_raw = get_term_meta( $term_id, 'country_flag', true );
            if ( $flag_raw ) {
                // Accept raw base64 string or data URI
                $flag = ( strpos( $flag_raw, 'data:' ) === 0 )
                    ? $flag_raw
                    : 'data:image/png;base64,' . $flag_raw;
            }
        }

        $output[] = [
            'term_id' => $term_id,
            'name'    => $name,
            'slug'    => $meta['slug'],
            'flag'    => $flag,
            'emoji'   => $meta['emoji'],
        ];
    }

    return $output;
}

/* =========================================================
   4. HELPER – all countries for JS dropdown
   ========================================================= */
function je_search_get_all_countries() {
    $terms = get_terms( [
        'taxonomy'   => 'country',
        'hide_empty' => true,
        'orderby'    => 'name',
        'order'      => 'ASC',
        'number'     => 500,
    ] );

    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        return [];
    }

    $output = [];
    foreach ( $terms as $term ) {
        $flag_raw = get_term_meta( $term->term_id, 'country_flag', true );
        $flag     = '';
        if ( $flag_raw ) {
            $flag = ( strpos( $flag_raw, 'data:' ) === 0 )
                ? $flag_raw
                : 'data:image/png;base64,' . $flag_raw;
        }
        $output[] = [
            'term_id' => $term->term_id,
            'name'    => $term->name,
            'slug'    => $term->slug,
            'flag'    => $flag,
        ];
    }

    return $output;
}

/* =========================================================
   5. AJAX – search experiences
   ========================================================= */
add_action( 'wp_ajax_je_search_experiences',        'je_ajax_search_experiences' );
add_action( 'wp_ajax_nopriv_je_search_experiences', 'je_ajax_search_experiences' );

function je_ajax_search_experiences() {
    check_ajax_referer( 'je_search_nonce', 'nonce' );

    // Accept either a JSON array of IDs (new) or legacy single country_id
    $country_ids_raw = isset( $_POST['country_ids'] ) ? $_POST['country_ids'] : '';
    $country_ids     = [];

    if ( $country_ids_raw !== '' ) {
        $decoded = json_decode( wp_unslash( $country_ids_raw ), true );
        if ( is_array( $decoded ) ) {
            $country_ids = array_map( 'absint', $decoded );
            $country_ids = array_filter( $country_ids ); // remove zeros
            $country_ids = array_values( $country_ids );
        }
    } elseif ( isset( $_POST['country_id'] ) && absint( $_POST['country_id'] ) > 0 ) {
        // Backward-compat with legacy single-country requests
        $country_ids = [ absint( $_POST['country_id'] ) ];
    }

    $date_from  = isset( $_POST['date_from'] )  ? sanitize_text_field( $_POST['date_from'] )  : '';
    $date_to    = isset( $_POST['date_to'] )    ? sanitize_text_field( $_POST['date_to'] )    : '';
    $keyword    = isset( $_POST['keyword'] )    ? sanitize_text_field( $_POST['keyword'] )    : '';
    $page       = isset( $_POST['page'] )       ? max( 1, absint( $_POST['page'] ) )          : 1;
    $per_page   = 10;

    $args = [
        'post_type'      => 'experience',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];

    // ── Keyword search in post_content (description) ──
    if ( $keyword !== '' ) {
        $args['s'] = $keyword;
    }

    // ── Country taxonomy filter (multiple countries → OR match) ──
    if ( ! empty( $country_ids ) ) {
        $args['tax_query'] = [
            [
                'taxonomy' => 'country',
                'field'    => 'term_id',
                'terms'    => $country_ids,
                'operator' => 'IN',
            ],
        ];
    }

    // ── Date range filter via "prices" repeater ──
    // The repeater stores rows with subfields: datestart_, dateend_, price_.
    // We pre-filter to find experience IDs where at least one repeater row
    // has a future end date AND overlaps the user's selected date range.
    if ( $date_from !== '' || $date_to !== '' ) {
        $today_ymd = wp_date( 'Y-m-d' );

        // Lightweight query: get ALL published experience IDs only
        $all_ids_query = new WP_Query( [
            'post_type'      => 'experience',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ] );
        $all_ids = $all_ids_query->posts;

        $matching_ids = [];
        foreach ( $all_ids as $pid ) {
            $repeater = get_post_meta( $pid, 'prices', true );
            if ( ! is_array( $repeater ) || empty( $repeater ) ) {
                continue;
            }

            foreach ( $repeater as $row ) {
                $raw_start = isset( $row['datestart_'] ) ? $row['datestart_'] : '';
                $raw_end   = isset( $row['dateend_'] )   ? $row['dateend_']   : '';

                // Normalize to Y-m-d (JetEngine may store as timestamp or string)
                $row_start = is_numeric( $raw_start ) ? wp_date( 'Y-m-d', (int) $raw_start ) : $raw_start;
                $row_end   = is_numeric( $raw_end )   ? wp_date( 'Y-m-d', (int) $raw_end )   : $raw_end;

                // Must be a future row (end date >= today)
                if ( $row_end === '' || $row_end < $today_ymd ) {
                    continue;
                }

                // Check overlap with user's filter range
                // Overlap logic: row_start <= filter_to AND row_end >= filter_from
                $overlap = true;
                if ( $date_from !== '' && $row_end < $date_from ) {
                    $overlap = false;
                }
                if ( $date_to !== '' && $row_start !== '' && $row_start > $date_to ) {
                    $overlap = false;
                }

                if ( $overlap ) {
                    $matching_ids[] = $pid;
                    break; // one matching row is enough for this post
                }
            }
        }

        // Constrain main query to matched IDs (use [0] for no matches → returns nothing)
        $args['post__in'] = ! empty( $matching_ids ) ? $matching_ids : [ 0 ];
    }

    // ── Run query ──
    $query        = new WP_Query( $args );
    $total        = (int) $query->found_posts;
    $total_pages  = (int) $query->max_num_pages;

    $posts_data = [];
    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();

            $post_id     = get_the_ID();
            $thumb_id    = get_post_thumbnail_id( $post_id );
            $thumb_url   = $thumb_id
                ? wp_get_attachment_image_url( $thumb_id, 'medium' )
                : '';

            // Country terms
            $countries = wp_get_post_terms( $post_id, 'country', [ 'fields' => 'all' ] );
            $country_labels = [];
            foreach ( $countries as $ct ) {
                $ct_flag_raw = get_term_meta( $ct->term_id, 'country_flag', true );
                $ct_flag     = '';
                if ( $ct_flag_raw ) {
                    $ct_flag = ( strpos( $ct_flag_raw, 'data:' ) === 0 )
                        ? $ct_flag_raw
                        : 'data:image/png;base64,' . $ct_flag_raw;
                }
                $country_labels[] = [
                    'name' => $ct->name,
                    'flag' => $ct_flag,
                ];
            }

            // Excerpt from content
            $excerpt = has_excerpt() ? get_the_excerpt() : wp_trim_words( get_the_content(), 20 );

            // JetEngine custom fields (adjust keys as needed)
            $price    = get_post_meta( $post_id, 'experience_price', true );
            $duration = get_post_meta( $post_id, 'experience_duration', true );
            $rating   = get_post_meta( $post_id, 'experience_rating', true );

            // ── "Starting at" price (standalone field) ──
            $starting_price_raw = get_post_meta( $post_id, 'price_-_starting_at', true );
            $starting_price     = $starting_price_raw ? esc_html( $starting_price_raw ) : '';

            // ── "prices" repeater – future dates only ──
            $upcoming_dates  = [];
            $prices_repeater = get_post_meta( $post_id, 'prices', true );
            $today           = wp_date( 'Y-m-d' ); // server today in WP timezone

            if ( is_array( $prices_repeater ) && ! empty( $prices_repeater ) ) {
                foreach ( $prices_repeater as $row ) {
                    $row_price = isset( $row['price_'] )     ? $row['price_']     : '';
                    $raw_start = isset( $row['datestart_'] ) ? $row['datestart_'] : '';
                    $raw_end   = isset( $row['dateend_'] )   ? $row['dateend_']   : '';

                    // JetEngine may store dates as Unix timestamps or Y-m-d strings
                    $start_ymd = is_numeric( $raw_start ) ? wp_date( 'Y-m-d', (int) $raw_start ) : $raw_start;
                    $end_ymd   = is_numeric( $raw_end )   ? wp_date( 'Y-m-d', (int) $raw_end )   : $raw_end;

                    // Skip rows where the end date is in the past
                    if ( $end_ymd === '' || $end_ymd < $today ) {
                        continue;
                    }

                    // Format dates for human-readable display
                    $start_ts    = strtotime( $start_ymd );
                    $end_ts      = strtotime( $end_ymd );
                    $display_from = $start_ts ? wp_date( 'M j, Y', $start_ts ) : '';
                    $display_to   = $end_ts   ? wp_date( 'M j, Y', $end_ts )   : '';

                    $upcoming_dates[] = [
                        'price'     => esc_html( $row_price ),
                        'date_from' => esc_html( $display_from ),
                        'date_to'   => esc_html( $display_to ),
                    ];
                }
            }

            $posts_data[] = [
                'id'             => $post_id,
                'title'          => get_the_title(),
                'permalink'      => get_permalink(),
                'excerpt'        => $excerpt,
                'thumb'          => $thumb_url,
                'date'           => get_the_date( 'M j, Y' ),
                'countries'      => $country_labels,
                'price'          => $price    ? esc_html( $price )    : '',
                'duration'       => $duration ? esc_html( $duration ) : '',
                'rating'         => $rating   ? floatval( $rating )   : 0,
                'starting_price' => $starting_price,
                'upcoming_dates' => $upcoming_dates,
            ];
        }
        wp_reset_postdata();
    }

    wp_send_json_success( [
        'posts'       => $posts_data,
        'total'       => $total,
        'total_pages' => $total_pages,
        'page'        => $page,
    ] );
}


/* =========================================================
   6. JETENGINE WIDGET (Elementor)
   ========================================================= */
add_action( 'elementor/widgets/register', 'je_search_register_elementor_widget' );
function je_search_register_elementor_widget( $widgets_manager ) {
    if ( ! class_exists( '\Elementor\Widget_Base' ) ) return;

    require_once JE_SEARCH_DIR . 'includes/class-je-search-widget.php';
    $widgets_manager->register( new \JE_Search\Widget() );
}
