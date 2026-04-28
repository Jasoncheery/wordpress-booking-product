/**
 * WP Bookable Products - Admin Script
 */
(function($) {
  'use strict';

  $(document).ready(function() {

    // Booking action buttons (confirm/cancel).
    $(document).on('click', '.wbp-bookings-list [data-action]', function(e) {
      e.preventDefault();
      var $btn = $(this);
      var id = $btn.data('id');
      var action = $btn.data('action');

      if (!confirm(wbp_params.i18n.confirm_action || 'Are you sure?')) return;

      $.ajax({
        url: wbp_params.ajax_url,
        data: {
          action: 'wbp_admin_booking_action',
          booking_id: id,
          action_type: action,
          _wpnonce: wbp_params.nonce
        },
        success: function(response) {
          location.reload();
        },
        error: function() {
          alert(wbp_params.i18n.error || 'An error occurred.');
        }
      });
    });

    // Resource slot bulk creation.
    $('#wbp_create_slots_btn').on('click', function() {
      var resource = $('#wbp_resource_select').val();
      var start = $('#wbp_slot_start_date').val();
      var end = $('#wbp_slot_end_date').val();

      if (!resource || !start || !end) {
        alert('Please fill in all fields.');
        return;
      }

      $.post(ajaxurl, {
        action: 'wbp_create_availability_slots',
        resource_id: resource,
        start_date: start,
        end_date: end,
        interval: $('#wbp_slot_interval').val() || 60,
        max_bookings: $('#wbp_max_bookings').val() || 1,
        _wpnonce: wbp_params.nonce
      }, function(response) {
        location.reload();
      });
    });

  });
})(jQuery);
