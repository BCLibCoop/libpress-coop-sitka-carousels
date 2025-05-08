<?php

namespace BCLibCoop\SitkaCarousel;

class SitkaCarouselWidget extends \WP_Widget
{
    /**
     * Sets up a new Recent Posts widget instance.
     *
     * @since 2.8.0
     */
    public function __construct()
    {
        parent::__construct(
            'carousel-sitka',
            'Sitka Carousel',
            [
                'description' => 'Displays the selected Sitka item carousel',
            ]
        );
    }
    /**
     * Outputs the widget
     *
     * @param array $args     Display arguments including 'before_title', 'after_title',
     *                        'before_widget', and 'after_widget'.
     * @param array $instance Settings for the current Recent Posts widget instance.
     */
    public function widget($args, $instance)
    {
        SitkaCarousel::$instance->frontsideEnqueueStylesScripts();

        $instance['transition'] = isset($instance['transition']) ? esc_attr($instance['transition']) : Constants::TRANSITION[0];
        $instance['carousel_id'] = isset($instance['carousel_id']) ? absint($instance['carousel_id']) : 0;

        $widget = SitkaCarousel::$instance->render($instance);

        echo $widget;

        if ($this->is_block_preview() && strpos($widget, '<!-- Could not find') !== false) {
            echo '<code>Unable to find any slides, please check the carousel settings.</code>';
        }
    }

    private function is_block_preview()
    {
        return (defined('IFRAME_REQUEST') && IFRAME_REQUEST && !empty($_GET['legacy-widget-preview'])) || wp_is_rest_endpoint();
    }

    /**
     * Updates instance settings
     *
     * @param array $new_instance New settings for this instance as input by the user via
     *                            WP_Widget::form().
     * @param array $old_instance Old settings for this instance.
     * @return array Updated settings to save.
     */
    public function update($new_instance, $old_instance)
    {
        $instance = $old_instance;
        $instance['transition'] = sanitize_text_field($new_instance['transition'] ?? '');
        $instance['carousel_id'] = (int) ($new_instance['carousel_id'] ?? 0);

        return $instance;
    }

    /**
     * Display the settings form
     *
     * @param array $instance Current settings.
     */
    public function form($instance)
    {
        $carousels = SitkaCarousel::$instance->getOrgCarousels();
        $no_carousels = empty($carousels);
        $transition = isset($instance['transition']) ? esc_attr($instance['transition']) : Constants::TRANSITION[0];
        $carousel_id = isset($instance['carousel_id']) ? absint($instance['carousel_id']) : 0;
        ?>
        <p>
            <label for="<?= $this->get_field_id('transition'); ?>"><?php _e('Carousel Transition Style:'); ?></label>
            <select class="widefat" id="<?= $this->get_field_id('transition'); ?>" name="<?= $this->get_field_name('transition'); ?>">
                <option value="0"><?php _e('&mdash; Select &mdash;'); ?></option>
                <?php foreach (Constants::TRANSITION as $transition_option) : ?>
                    <option value="<?= esc_attr($transition_option); ?>" <?php selected($transition, $transition_option); ?>>
                        <?= esc_html(ucwords($transition_option)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="<?= $this->get_field_id('carousel_id'); ?>"><?php _e('Carousel ID:'); ?></label>
            <select class="widefat" id="<?= $this->get_field_id('carousel_id'); ?>" name="<?= $this->get_field_name('carousel_id'); ?>" <?php disabled($no_carousels) ?>>
                <option value="0"><?php _e('&mdash; Select &mdash;'); ?></option>
                <?php foreach ($carousels as $carousel) : ?>
                    <option value="<?= esc_attr($carousel['carousel_id']); ?>" <?php selected($carousel_id, $carousel['carousel_id']); ?>>
                        <?= esc_html($carousel['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <?php if ($no_carousels) : ?>
            <p class="description">No carousels exist, one must be created in Sitka first</p>
        <?php endif; ?>
        <?php
    }
}
