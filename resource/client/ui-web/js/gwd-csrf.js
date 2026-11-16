(function () {
  if (typeof window === 'undefined' || !window.jQuery) {
    return;
  }
  var meta = document.querySelector('meta[name="csrf-token"]');
  var token = meta ? meta.getAttribute('content') : '';
  if (!token) {
    return;
  }
  window.jQuery.ajaxPrefilter(function (options, originalOptions, jqXHR) {
    var url = options.url || '';
    if (/^https?:\/\//i.test(url) && url.indexOf(window.location.origin) !== 0) {
      return;
    }
    jqXHR.setRequestHeader('X-CSRF-Token', token);
  });
})();
