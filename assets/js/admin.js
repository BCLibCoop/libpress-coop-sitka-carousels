jQuery(document).ready(function ($) {
  $('input:radio[name=recheck_period]').on('click', function (event) {
    // reset
    $('#run-messages').html('');

    let period_checked = $('input:radio[name=recheck_period]:checked').val();

    if ($(period_checked) !== undefined) {
      $('#controls-submit').removeClass('disabled').val('Ready to run.');
    }
  });

  $('#controls-submit').on('click', function (event) {
    event.preventDefault();

    const period_checked = $('input:radio[name=recheck_period]').filter(':checked').val();

    const data = {
      action: 'coop_sitka_carousels_control_callback',
      mode: 'single',
      recheck_period: period_checked,
      _ajax_nonce: coop_sitka_carousels.nonce,
    };

    // Give user cue not to click again
    $('#controls-submit').addClass('disabled').val('Working...');

    $.post(window.ajaxurl, data, function (response) {
      if (response.success == true) {
        // Provide status message
        $('#run-messages').html('This can take a few minutes for the average library. Please wait...');

        $('#controls-submit').val('Check scheduled');

        console.log('Carousel run has been scheduled in a few minutes. Check again for next cron run for results.');
      } else {
        // Provide status message
        $('#run-messages').html('<strong>Something went wrong with scheduling the check, please try again.</strong>');

        $('#controls-submit').removeClass('disabled').val('Ready to run.');
      }
    });
  });
});
