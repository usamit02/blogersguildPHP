var THUMB_SIZE = 100;

fileSelect.onclick = function (evt) {
  evt.preventDefault();
  fileElm.click();
};

fileElm.onchange = function () {
  if (!this.files.length) return;
  var img = new Image();
  img.onload = function () {
    var cnv = document.createElement('canvas');
    var ratio = img.naturalWidth / img.naturalHeight;
    cnv.width = ratio >= 1 ? THUMB_SIZE : THUMB_SIZE / ratio;
    cnv.height = ratio < 1 ? THUMB_SIZE : THUMB_SIZE / ratio;
    var ctx = cnv.getContext('2d');
    ctx.drawImage(img, 0, 0, cnv.width, cnv.height);
    if (cnv.msToBlob) {
      sendToServer(cnv.msToBlob());
    } else {
      cnv.toBlob(sendToServer, 'image/png'); // msToBlobと合わせるためpngに設定
    }
  }
  img.src = URL.createObjectURL(this.files[0]);
}

function sendToServer(blob) {
  // デバッグのため実際には送信せず、サムネイルを画面に表示
  var preview = new Image();
  preview.src = URL.createObjectURL(blob);
  document.body.appendChild(preview);
  return;

  var xml = new XMLHttpRequest();
  xml.open('POST', 'https://hogefuga.net');
  xml.onload = function () {
    console.log('success send to server.');
  }
  xml.onerror = function () {
    console.log('error send to server.');
  }
  var fd = new FormData();
  fd.append('file', blob);
  xml.send(fd);
}