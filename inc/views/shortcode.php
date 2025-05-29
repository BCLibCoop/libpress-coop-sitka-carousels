<div class="sitka-carousel-container">
    <?php if (!empty($results)) : ?>
        <div class="sitka-carousel" data-flickity='<?= $flickity_options ?>'>
            <?php foreach ($results as $index => $row) : ?>
                <div class="carousel-item">
                    <a href="<?= esc_url($row['catalogue_url']) ?>">
                        <div class="carousel-item-cover">
                            <img src="<?= $row['cover_url'] ?>" alt="" class="carousel-item-image" decoding="async" <?= $index == 0 ? '' : 'loading="lazy"' ?>>
                            <img src="<?= $atts['no_cover'] ?>" alt="" class="carousel-item-image carousel-item-image-default" decoding="async">
                        </div>
                        <div class="carousel-item-info">
                            <span class="carousel-item-title"><?= esc_html($row['title']) ?></span>
                            <br>
                            <span class="carousel-item-author"><?= esc_html($row['author']) ?></span>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <!-- Could not find any carousel items to display -->
    <?php endif; ?>
</div>
