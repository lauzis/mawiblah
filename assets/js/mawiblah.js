function httpPost(url, headers, data, callback, failCallBack) {

  var xmlhttp = new XMLHttpRequest();   // new HttpRequest instance
  xmlhttp.onreadystatechange = function () {
    if (xmlhttp.readyState === XMLHttpRequest.DONE) { // XMLHttpRequest.DONE == 4
      if (xmlhttp.status === 200) {
        callback(JSON.parse(xmlhttp.response), url, headers);
      } else {
        if (failCallBack) {
          failCallBack();
        }
      }
      clearTimeout(window.requestCancelTimer);
    }
  };
  xmlhttp.open("POST", url, true);
  xmlhttp.setRequestHeader("Content-Type", "application/json;charset=UTF-8");
  xmlhttp.setRequestHeader('X-WP-Nonce', mawiblahNonce.mawiblahNonce);
  if (headers && headers.headers) {
    for (const key of Object.keys(headers.headers)) {
      xmlhttp.setRequestHeader(key, headers.headers[key]);
    }
  }
  xmlhttp.send(JSON.stringify(data));
}


function httpGet(url, headers, callback, failCallBack) {
  var xmlhttp = new XMLHttpRequest();

  xmlhttp.onreadystatechange = function () {
    if (xmlhttp.readyState === XMLHttpRequest.DONE) { // XMLHttpRequest.DONE == 4

      const status = xmlhttp.status;
      if (status === 200) {
        try {
          callback(JSON.parse(xmlhttp.response), url, headers, status);
        } catch (e) {
          console.log(e);
          callback({response: xmlhttp.response}, url, headers, status);
        }

      } else if (status === 204) {
        callback(null, url, headers, status);
      } else if (status === 401) {
        callback(null, url, headers, status);
      } else {
        if (failCallBack) {
          failCallBack(null, url, headers, status);
        }
      }
      clearTimeout(window.requestCancelTimer);
    }
  };

  xmlhttp.open("GET", url, true);
  xmlhttp.setRequestHeader("Content-Type", "application/json;charset=UTF-8");
  console.log(mawiblahNonce.mawiblahNonce);
  xmlhttp.setRequestHeader('X-WP-Nonce', mawiblahNonce.mawiblahNonce);
  if (headers && headers.headers) {
    for (const headersKey of Object.keys(headers.headers)) {
      xmlhttp.setRequestHeader(headersKey, headers.headers[headersKey]);
    }
  }

  xmlhttp.send();
}


function MAWIBLAH_test() {

  console.log("testing..... 0.0.1");
  var url = "/wp-json/mawiblah/v1/test";
  httpGet(url, null, function () {

  }, function () {

  });
}

function MAWIBLAH_getHtmlTemplate(template, preview) {
  var url = "/wp-json/mawiblah/v1/get-html-template";

  const data = {
    template: template
  }
  httpPost(url, null, data, function (data) {
    console.log(data);
    preview.innerHTML = data.template;

  }, function () {

  });
}


function MAWIBLAH_tableActions() {
  var listOfLInks = document.querySelectorAll('.campaign-actions');

  listOfLInks.forEach(function (link) {
    const isEdit = link.classList.contains('link-edit');
    const isDisabled = link.classList.contains('disabled');
    const isSend = link.classList.contains('link-send');
    if (isDisabled) {
      return;
    }
    link.addEventListener('click', function () {

      var type = this.getAttribute('data-type');
      var href = this.getAttribute('data-href');

      var go = confirm("Are you sure?");
      if (go) {
        document.location.href = href;
      }
    });
  });

}


function MAWIBLAH_loadPreview() {
  const template = document.getElementById('template').value;
  const preview = document.getElementById('mawiblah-preview');
  MAWIBLAH_getHtmlTemplate(template, preview);
}


function init() {
  MAWIBLAH_tableActions();

  var templateElement = document.getElementById('template');
  if (templateElement) {
    MAWIBLAH_loadPreview();
    templateElement.addEventListener('change', MAWIBLAH_loadPreview);
  }

  //MAWIBLAH_test();
}


window.addEventListener('load', function () {
  init();
});
