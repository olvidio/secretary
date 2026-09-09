(function () {
  var contenedor = document.getElementById('totp-qr');
  if (!contenedor || typeof QRCode === 'undefined') {
    return;
  }
  var uri = contenedor.getAttribute('data-uri') || '';
  if (uri === '') {
    return;
  }
  new QRCode(contenedor, {
    text: uri,
    width: 200,
    height: 200,
    colorDark: '#1a202c',
    colorLight: '#ffffff',
    correctLevel: QRCode.CorrectLevel.M,
  });
})();
