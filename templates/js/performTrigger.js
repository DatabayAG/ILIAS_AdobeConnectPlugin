$.ajax({
  'type': 'GET',
  "url": "{TRIGGER_TARGET}"
}).done((response) => {
  console.log('ok');
});