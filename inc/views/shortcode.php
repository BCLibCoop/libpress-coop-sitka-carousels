<div class="sitka-carousel-container">
    <div class="slick-slider <?= end($carousel_class) ?>">
        <?php foreach ($results as $row) : ?>
            <?php
            // Get possible catalogue URL from db
            $catalogue_url = $row['catalogue_url'];

            // If catalogue URL isn't stored, create it
            if (empty($catalogue_url)) {
                $catalogue_url = $catalogue_prefix . sprintf(
                    "/eg/opac/record/%d?locg=%d",
                    $row['bibkey'],
                    $lib_locg,
                );
            } elseif (!(strpos($catalogue_url, 'http') === 0)) {
                // If catalogue URL doesn't have prefix, add it
                $catalogue_url = $catalogue_prefix . $catalogue_url;
            }

            // Build cover URL here so we can change size in the future if needed
            $cover_url = $catalogue_prefix . '/opac/extras/ac/jacket/medium/r/' . $row['bibkey'];

            // Build the HTML to return for the short tag ?>
            <div class="sitka-item">
                <a href="<?= $catalogue_url ?>">
                    <img alt="" src="<?= $cover_url ?>" class="sitka-carousel-image">
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

<script>
    jQuery(document).ready(function(){
        jQuery('.<?= end($carousel_class) ?>').slick({
            slidesToShow: 1,
            slidesToScroll: 1,
            autoplay: true,
            autoplaySpeed: 3000,
            speed: 1000,
            infinite: true,
            pauseOnHover: true,
            accessibility: true,
            fade: <?= $transition === 'fade' ? 'true' : 'false' ?>,
        });
    });
</script>
