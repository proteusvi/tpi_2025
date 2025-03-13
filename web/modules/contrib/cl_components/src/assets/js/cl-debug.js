(function (document, Drupal) {
  const generateHue = (label) => {
    let r = 0;
    let code;
    let index = 0;
    for (char of label) {
      code = label.charCodeAt(index);
      r += code;
      index++;
    }
    return r % 360;
  };
  Drupal.behaviors.debug = {
    attach: function attach(context) {
      const elements = context.getElementsByClassName('cl-component--debug');
      for (el of elements) {
        if (el.style.getPropertyValue('--indicator-color')) {
          // Once set, don't set it again.
          continue;
        }
        const componentName = el.getAttribute('data-cl-component-id') + el.getAttribute('data-cl-component-variant');
        const hue = generateHue(componentName);
        el.style.setProperty('--indicator-color', `hsla(${hue}, 100%, 50%, 0.45)`);
        el.style.setProperty('--indicator-color-hover', `hsl(${hue}, 100%, 50%)`);
      }
    },
  };
})(document, Drupal);
