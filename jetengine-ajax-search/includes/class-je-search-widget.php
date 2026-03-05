<?php
/**
 * Elementor Widget – JE Experience Search
 */

namespace JE_Search;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) exit;

class Widget extends Widget_Base {

    public function get_name()  { return 'je-experience-search'; }
    public function get_title() { return __( 'Experience Search', 'je-ajax-search' ); }
    public function get_icon()  { return 'eicon-search'; }
    public function get_categories() { return [ 'general', 'jetengine' ]; }
    public function get_keywords() { return [ 'search', 'experience', 'country', 'ajax', 'filter' ]; }

    protected function register_controls() {
        // ── Content ──────────────────────────────────────
        $this->start_controls_section( 'section_content', [
            'label' => __( 'Search Settings', 'je-ajax-search' ),
        ] );

        $this->add_control( 'show_quick_select', [
            'label'        => __( 'Show Quick-Select Pills', 'je-ajax-search' ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __( 'Show', 'je-ajax-search' ),
            'label_off'    => __( 'Hide', 'je-ajax-search' ),
            'return_value' => 'yes',
            'default'      => 'yes',
        ] );

        $this->add_control( 'results_heading', [
            'label'     => __( 'Results Section Heading', 'je-ajax-search' ),
            'type'      => Controls_Manager::TEXT,
            'default'   => __( 'Experiences', 'je-ajax-search' ),
        ] );

        $this->add_control( 'per_page', [
            'label'   => __( 'Posts Per Page', 'je-ajax-search' ),
            'type'    => Controls_Manager::NUMBER,
            'default' => 10,
            'min'     => 1,
            'max'     => 50,
        ] );

        $this->end_controls_section();

        // ── Style ─────────────────────────────────────────
        $this->start_controls_section( 'section_style', [
            'label' => __( 'Style', 'je-ajax-search' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'accent_color', [
            'label'     => __( 'Accent Color', 'je-ajax-search' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#1a56db',
            'selectors' => [
                '{{WRAPPER}} .je-search-wrap' => '--je-accent: {{VALUE}};',
            ],
        ] );

        $this->add_control( 'card_radius', [
            'label'      => __( 'Card Border Radius', 'je-ajax-search' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px' ],
            'range'      => [ 'px' => [ 'min' => 0, 'max' => 32 ] ],
            'default'    => [ 'size' => 12, 'unit' => 'px' ],
            'selectors'  => [
                '{{WRAPPER}} .je-result-card' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ] );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        // Pass per_page to JS via inline data
        $per_page = ! empty( $settings['per_page'] ) ? (int) $settings['per_page'] : 10;

        echo '<div data-je-per-page="' . esc_attr( $per_page ) . '">';
        echo do_shortcode( '[je_experience_search]' );
        echo '</div>';
    }

    protected function content_template() {
        ?>
        <div class="je-search-wrap je-elementor-preview">
            <p style="text-align:center;padding:24px;opacity:.6;">
                <?php echo esc_html__( '⚑ Experience Search Widget — preview in frontend.', 'je-ajax-search' ); ?>
            </p>
        </div>
        <?php
    }
}
