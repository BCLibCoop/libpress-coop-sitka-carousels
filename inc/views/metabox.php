<p>Sitka Carousels can be added to this Highlight by inserting one or more shortcodes into the Highlight text</p>
<p>Shortcodes take the following format:</p>
<code>[sitka_carousel type="adult_fiction" transition="fade"]</code>

<p>Possible values for type:</p>
<ul>
    <?php foreach (BCLibCoop\SitkaCarousel\Constants::TYPE as $index => $type) : ?>
        <li><code><?= $type ?></code><?= $index === 0 ? ' * Default' : '' ?></li>
    <?php endforeach; ?>
</ul>

<p>Possible values for transition:</p>
<ul>
    <?php foreach (BCLibCoop\SitkaCarousel\Constants::TRANSITION as $index => $type) : ?>
        <li><code><?= $type ?></code><?= $index === 0 ? ' * Default' : '' ?></li>
    <?php endforeach; ?>
</ul>

<?php if (!empty($sitka_carousels)) : ?>
    <hr>

    <p>In addition, your library has the following carousels configured in Sitka which may be used with a <code>carousel_id</code> attribute in place of the <code>type</code> attribute</p>
    <table>
        <tr>
            <td>Name</td>
            <td>Carousel ID</td>
        </tr>
        <?php foreach ($sitka_carousels as $sitka_carousel) : ?>
            <tr>
                <td><?= $sitka_carousel['name'] ?></td>
                <td style="text-align: right;"><?= $sitka_carousel['carousel_id'] ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<hr>

<p>More than one carousel shortcode can be added to a Highlight.</p>
