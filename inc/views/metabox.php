<p>Sitka Carousels can be added to this Highlight by inserting one or more shortcodes into the Highlight text</p>
<p>Shortcodes take the following format:</p>
<code>[sitka_carousel carousel_id="48" transition="fade"]</code>

<p>Carousels configured in Sitka (use the ID as the <code>carousel_id</code> attribute)</p>
<p><em>See the <a href="https://help.libraries.coop/libpress/5-features/sitka-carousels/" target="_blank">LibPress Manual</a> for more information</em></p>
<table>
    <thead>
        <tr>
            <th scope="col">Carousel ID</th>
            <th scope="col" style="text-align: left; padding-left: 1em;">Name</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($sitka_carousels)) : ?>
            <?php foreach ($sitka_carousels as $sitka_carousel) : ?>
                <tr>
                    <td><?= $sitka_carousel['carousel_id'] ?></td>
                    <td style="padding-left: 1em;"><?= $sitka_carousel['name'] ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else : ?>
            <tr>
                <td colspan="2">
                    <em>No Carousels Configured</em></tr>
            </td>
        <?php endif; ?>
    </tbody>
</table>

<p>Possible values for <code>transition</code>:</p>
<ul>
    <?php foreach (BCLibCoop\SitkaCarousel\Constants::TRANSITION as $index => $type) : ?>
        <li><code><?= $type ?></code><?= $index === 0 ? ' <em>* Default</em>' : '' ?></li>
    <?php endforeach; ?>
</ul>

<hr>

<p>More than one carousel shortcode can be added to a Highlight.</p>
