// Календар във формат дд.мм.гггг; към сървъра отива гггг-мм-дд.
(function () {
  if (!window.flatpickr) return;
  flatpickr.localize(flatpickr.l10ns.bg);
  flatpickr('input.date', {
    dateFormat: 'Y-m-d', altInput: true, altFormat: 'd.m.Y', allowInput: true,
    disableMobile: true, locale: { firstDayOfWeek: 1 },
  });
})();
