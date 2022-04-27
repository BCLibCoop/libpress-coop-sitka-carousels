<div class="wrap">
  <h1 class="wp-heading-inline">Sitka Carousel Controls - <?= get_bloginfo('blogname') ?></h1>
  <hr class="wp-header-end">

  <?php if ($shortname && $shortname !== 'NA') : ?>
    <p>
      Last full run: <input type="text" id="last_checked" name="last_checked" disabled value="<?= $last_checked ?>">
    </p>

    <div class="sitka-carousel-controls">
      <form>
        <h4>Set re-check period:</h4>
        <div class="sitka-carousel-radios">
          <input type="radio" id="last_one" name="recheck_period" value="1">Last month<br>
          <input type="radio" id="last_two" name="recheck_period" value="2">2 months ago<br>
          <input type="radio" id="last_four" name="recheck_period" value="4">4 months ago<br>
        </div>
        <br />
        <?php submit_button('Select a period.', 'primary large disabled', 'controls-submit', false) ?>
      </form>

      <p id="run-messages"><?= $run_message ?></p>
    </div>

    <hr>
    <h2>Carousel Search Links</h2>
    <p>
      Use these links to perform a similar search to the automated checker for each type of carousel to see if
      an item should be showing in the carousel or not.
    </p>

    <ul>
      <?php foreach (BCLibCoop\SitkaCarousel\Constants::TYPE as $carousel_type) : ?>
            <?php $link = sprintf(
                "%s/opac/extras/opensearch/1.1/%s/html/?searchTerms=%s&searchSort=create_date&count=25",
                $opensearch_url,
                $shortname,
                urlencode(BCLibCoop\SitkaCarousel\Constants::SEARCH[$carousel_type] . " create_date($date_checked)")
            ); ?>
        <li>
          <a href="<?= $link ?>" target="_blank"><?= $carousel_type ?></a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php elseif (is_super_admin()) : ?>
    <h3>No Sitka Carousels shortname set for this site</h3>
    <p>
      Set a shortname <a href="<?= network_admin_url('sites.php?page=sitka-libraries') ?>">here</a>
      to allow carousel runs.
    </p>
  <?php else : ?>
    <h3>Sitka Carosel Not Enabled</h3>
    <p>
      This site is not set up to fetch carosel data from a Sitka catalogue. If you believe this is an error,
      please open a <a href="https://bc.libraries.coop/support/">support ticket</a>.
    </p>
  <?php endif; ?>
</div>
