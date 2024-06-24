(function($, root) {
  function ilCrsGrpTriggerHandler() {
    $.ajax({
      "url": "{TRIGGER_TARGET}"
    }).done((response) => {
      console.log('ok');
    });
  }
  root.ilCrsGrpTriggerHandler = ilCrsGrpTriggerHandler;
})(jQuery, window);