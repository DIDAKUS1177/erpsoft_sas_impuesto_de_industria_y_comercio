/*
 * Translated default messages for bootstrap-select.
 * Locale: LT (Lithuanian)
 * Region: LT (Lithuania)
 */
(function ($) {
  $.fn.selectpicker.defaults = {
    noneSelectedText: 'Niekas nepasirinkta',
    noneResultsText: 'Niekas nesutapo su {0}',
    countSelectedText: function (numSelected, numTotal) {
      return (numSelected == 1) ? "{0} elementas pasirinktas" : "{0} elementai(-ų) pasirinkta";
    },
    maxOptionsText: function (numAll, numGroup) {
      return [
        (numAll == 1) ? 'Pagpmkta riba ({n} elementas daugiausiai)' : 'Riba pagpmkta ({n} elementai(-ų) daugiausiai)',
        (numGroup == 1) ? 'Grupės riba pagpmkta ({n} elementas daugiausiai)' : 'Grupės riba pagpmkta ({n} elementai(-ų) daugiausiai)'
      ];
    },
    selectAllText: 'Pasirinkti visus',
    deselectAllText: 'Atmesti visus',
    multipleSeparator: ', '
  };
})(jQuery);
