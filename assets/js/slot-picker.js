/**
 * WBP Slot Picker — Frontend widget for selecting booking time slots.
 */
(function ($) {
  'use strict';

  var WbpSlotPicker = function (container, options) {
    this.container = $(container);
    this.productId = options.productId || 0;
    this.resourceId = options.resourceId || 0;
    this.currentDate = options.startDate || jQuery.datepicker.parseDate('yy-mm-dd', new Date());
    this.apiRoot = wbp_slot_picker.ajax_url;
    this.nonce = wbp_slot_picker.nonce;
    this.selectedSlot = null;

    this.init();
  };

  WbpSlotPicker.prototype.init = function () {
    var self = this;
    this.renderCalendar();
    this.container.on('click', '.wbp-slot', function () {
      self.selectSlot($(this));
    });
    this.container.on('click', '.wbp-prev-month', function (e) {
      e.preventDefault();
      self.changeMonth(-1);
    });
    this.container.on('click', '.wbp-next-month', function (e) {
      e.preventDefault();
      self.changeMonth(1);
    });
  };

  WbpSlotPicker.prototype.renderCalendar = function () {
    var self = this;
    var dateStr = self.currentDate;
    var apiUrl = self.apiRoot + '/wbp/v1/slots/product/' + self.productId + '?date=' + dateStr;

    self.container.find('.wbp-slots').html('<tr><td colspan="7" class="text-center">' + wbp_slot_picker.i18n.loading + '</td></tr>');

    $.ajax({
      url: apiUrl,
      data: { _wpnonce: self.nonce },
      success: function (response) {
        if (response.data && response.data.slots) {
          self.renderSlots(response.data.slots);
        } else {
          self.container.find('.wbp-slots').html('<tr><td colspan="7" class="text-center">' + wbp_slot_picker.i18n.no_slots + '</td></tr>');
        }
      },
      error: function () {
        self.container.find('.wbp-slots').html('<tr><td colspan="7" class="text-center">' + wbp_slot_picker.i18n.error + '</td></tr>');
      }
    });
  };

  WbpSlotPicker.prototype.renderSlots = function (slots) {
    var html = '';
    if (!slots || slots.length === 0) {
      this.container.find('.wbp-slots').html('<tr><td colspan="7" class="text-center">' + wbp_slot_picker.i18n.no_slots + '</td></tr>');
      return;
    }
    for (var i = 0; i < slots.length; i++) {
      var slot = slots[i];
      var start = new Date(slot.slot_start).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      var end = new Date(slot.slot_end).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      html += '<button type="button" class="wbp-slot" data-id="' + slot.id + '" data-start="' + slot.slot_start + '" data-end="' + slot.slot_end + '">' + start + ' - ' + end + '</button>';
    }
    this.container.find('.wbp-slots').html(html);
    // Re-bind click
    var self = this;
    this.container.off('click', '.wbp-slot').on('click', '.wbp-slot', function () {
      self.selectSlot($(this));
    });
  };

  WbpSlotPicker.prototype.selectSlot = function ($btn) {
    var self = this;
    $('.wbp-slot').removeClass('selected');
    $btn.addClass('selected');
    this.selectedSlot = {
      id: $btn.data('id'),
      start: $btn.data('start'),
      end: $btn.data('end')
    };
    $('#wbp_hidden_slot_start').val(this.selectedSlot.start);
    $('#wbp_hidden_slot_end').val(this.selectedSlot.end);
    // Trigger change event for cart integration
    $(document).trigger('wbp_slot_selected', [this.selectedSlot]);
  };

  WbpSlotPicker.prototype.changeMonth = function (delta) {
    var d = new Date(this.currentDate);
    d.setMonth(d.getMonth() + delta);
    this.currentDate = jQuery.datepicker.formatDate('yy-mm-dd', d);
    this.renderCalendar();
  };

  // Auto-initialize on DOM ready
  $(document).ready(function () {
    $('.wbp-slot-picker').each(function () {
      new WbpSlotPicker(this, {
        productId: $(this).data('product-id') || 0,
        resourceId: $(this).data('resource-id') || 0,
        startDate: $(this).data('start-date') || jQuery.datepicker.formatDate('yy-mm-dd', new Date())
      });
    });
  });

})(jQuery);
