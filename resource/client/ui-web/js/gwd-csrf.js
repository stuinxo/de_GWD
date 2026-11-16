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
    try {
      var parsed = new URL(url, window.location.origin);
      if (parsed.origin !== window.location.origin) {
        return;
      }
    } catch (e) {
      if (/^https?:\/\//i.test(url)) {
        return;
      }
    }
    jqXHR.setRequestHeader('X-CSRF-Token', token);
  });
})();
