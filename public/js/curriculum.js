const avatar = document.getElementById('curriculum-avatar');

if (avatar) {
  avatar.addEventListener('error', () => { avatar.hidden = true; });
  if (avatar.complete && avatar.naturalWidth === 0) avatar.hidden = true;
}

const printButton = document.getElementById('print-curriculum');

if (printButton) {
  printButton.hidden = false;
  printButton.addEventListener('click', () => window.print());
}
