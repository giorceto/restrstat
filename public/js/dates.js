// Календар във формат дд.мм.гггг; към сървъра отива гггг-мм-дд. Формите .autosubmit се пращат при всяка промяна.
(function () {
  const submit = (el) => { const f = el.closest('form.autosubmit'); if (f) f.requestSubmit ? f.requestSubmit() : f.submit(); };
  if (window.flatpickr) {
    flatpickr.localize(flatpickr.l10ns.bg);
    document.querySelectorAll('input.date').forEach((el) => {
      let start = el.value;
      flatpickr(el, {
        dateFormat: 'Y-m-d', altInput: true, altFormat: 'd.m.Y', allowInput: true,
        disableMobile: true, locale: { firstDayOfWeek: 1 },
        onClose: () => { if (el.value !== start) { start = el.value; submit(el); } },
      });
    });
  }
  document.querySelectorAll('form.autosubmit').forEach((f) => {
    f.querySelectorAll('select, input[type=radio]').forEach((el) => el.addEventListener('change', () => submit(el)));
  });
})();
