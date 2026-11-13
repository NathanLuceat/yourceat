const printButton = document.getElementById('print-curriculum');

if (printButton) {
  printButton.hidden = false;
  printButton.addEventListener('click', () => window.print());
}
