<div class="sitka-carousel-container">
    <?php if (!empty($results)) : ?>
        <div class="sitka-carousel" data-flickity='<?= $flickity_options ?>'>
            <?php foreach ($results as $row) : ?>
                <div class="carousel-item">
                    <a href="<?= $row['catalogue_url'] ?>">
                        <div class="carousel-item-cover">
                            <img alt="" src="<?= $row['cover_url'] ?>" class="carousel-item-image">
                            <img alt="" src="<?= $attr['no_cover'] ?>" class="carousel-item-image carousel-item-image-default">
                        </div>
                        <div class="carousel-item-info">
                            <span class="carousel-item-title"><?= $row['title'] ?></span>
                            <br />
                            <span class="carousel-item-author"><?= $row['author'] ?></span>
                        </div>
                    </a>
                    <?php /* <div class="carousel-item-description"><?= $row['description'] ?></div> */ ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <!-- Could not find any carousel items to display -->
    <?php endif; ?>
</div>
