
function round(value, decimals) {
  // console.log(Math.round(value + 'e' + decimals) + 'e-' + decimals);

  var number = parseFloat(Number(Math.round(value + 'e' + decimals) + 'e-' + decimals));
  // console.log(number.toFixed(decimals));
  return number.toFixed(decimals);

  // return Number(Math.round(value + 'e' + decimals) + 'e-' + decimals);
}