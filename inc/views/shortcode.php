<div class="sitka-carousel-container">
    <div class="sitka-carousel" data-flickity='<?= $flickity_options ?>'>
        <?php foreach ($results as $row) : ?>
            <div class="sitka-item">
                <a href="<?= $row['catalogue_url'] ?>">
                    <img alt="" src="<?= $row['cover_url'] ?>" class="sitka-carousel-image">
                    <img alt="" src="<?= $no_cover ?>" class="sitka-carousel-image sitka-carousel-image-default">
                    <div class="sitka-info">
                        <span class="sitka-title"><?= $row['title'] ?></span>
                        <br />
                        <span class="sitka-author"><?= $row['author'] ?></span>
                    </div>
                </a>
                <div class="sitka-description"><?= $row['description'] ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
