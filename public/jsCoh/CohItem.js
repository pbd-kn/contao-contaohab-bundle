class CohItem {
  constructor() {
    this.params = {};
  }

  set(key, value) {
    this.params[key] = value;
    return this;
  }

  setAll(values) {
    if (typeof values === 'object' && values !== null) {
      for (const [key, value] of Object.entries(values)) {
        this.params[key] = value;
      }
    }
    return this;
  }

renderAsHtmlString() {
    const {
        icon,
        iconType = '',
        sensorValue = 0,
        iconSize = 80,
        iconColor,
        textColor,
        title = '',
        description = '',
        gaugeId,
        sensorTitle,
        styles = {}
    } = this.params;

    const titleText = description || title;
    if (iconType === 'gauge') {
        // Gauge-HTML zurückgeben
        return `
            <div class="gauge-container" style="display:flex; align-items:flex-start;">
                <canvas 
                    class="gauge-canvas" 
                    id="${gaugeId || 'gaugeIdDummy'}"
                    width="${iconSize}" 
                    height="${iconSize}" 
                    style="width:${iconSize}px;height:${iconSize}px"
                    data-value="${sensorValue}"
                    data-max="100"
                ></canvas>
                <div class="block-title" style="margin-left:20px; flex-shrink:1;${textColor ? ` color: ${textColor};` : ''}">
                    ${titleText}
                </div>
            </div>
        `;
    } else if (iconType === 'toggle') {
        const preparedTitle = titleText.replace(/(\r\n|\n\r|\r|\n)/g, "<br>");
        const styleParts = [];
        if (iconSize) styleParts.push(`font-size: ${iconSize}px`);
        if (iconColor) styleParts.push(`color: ${iconColor}`);
        for (const [key, value] of Object.entries(styles)) {
            styleParts.push(`${key}: ${value}`);
        }
        const iconStyle = styleParts.length ? ` style="${styleParts.join('; ')}"` : '';
        const iconHtml = this.renderIconHtml(icon, styles, iconSize, iconColor);

        let ret = "";
        ret += "<details>";
        ret += `<summary${textColor ? ` style="color: ${textColor};"` : ''}>${iconHtml} ${sensorTitle}</summary>`;
        ret += "<div>";
        ret += preparedTitle;
        ret += "</div>";
        ret += "</details>";
        return ret;
    } else if (iconType === 'togglestart') {
        const preparedTitle = titleText.replace(/(\r\n|\n\r|\r|\n)/g, "<br>");
        const styleParts = [];
        if (iconSize) styleParts.push(`font-size: ${iconSize}px`);
        if (iconColor) styleParts.push(`color: ${iconColor}`);
        for (const [key, value] of Object.entries(styles)) {
            styleParts.push(`${key}: ${value}`);
        }
        const iconStyle = styleParts.length ? ` style="${styleParts.join('; ')}"` : '';
        const iconHtml = this.renderIconHtml(icon, styles, iconSize, iconColor);
        let ret = "";
        const isOpen = typeof sensorValue === 'string' && sensorValue.toLowerCase().includes('open') ? ' open' : '';
        ret += `<details${isOpen}>`;
        //ret += "<details>";
        ret += `<summary${textColor ? ` style="color: ${textColor};"` : ''}>${iconHtml} ${sensorTitle}</summary>`;
        ret += "<div>";
        return ret;
    } else if (iconType === 'toggleend') {
        let ret = "";
        ret += "</div>";
        ret += "</details>";
        return ret;
    } else {
        // Icon-Fall
        const iconClass = this.params.iconClass ? `bi ${icon} ${this.params.iconClass}` : `bi ${icon}`;
    
        const styleParts = [];
        if (iconSize) styleParts.push(`font-size: ${iconSize}px`);
        if (iconColor) styleParts.push(`color: ${iconColor}`);
        for (const [key, value] of Object.entries(styles)) {
            styleParts.push(`${key}: ${value}`);
        }
        const iconStyle = styleParts.length ? ` style="${styleParts.join('; ')}"` : '';


        const iconHtml = this.renderIconHtml(icon, styles, iconSize, iconColor);
        const preparedTitle = titleText.replace(/(\r\n|\n\r|\r|\n)/g, "<br> iconType $iconType");
    

        return `
            <div class="coh--item" style="display: flex; align-items: flex-start;${textColor ? ` color: ${textColor};` : ''}">
                ${iconHtml}
                <div style="margin-left: 20px;">${preparedTitle}</div>
            </div>
        `;

    }
}
/* erzeugt den code zum einbringen des Icons abhängig vom Iconset der Kennzeichnung vor dem icon bs:   ... */
/* font familien 
   fawesome fas	Font Awesome Solid	gefüllt	Bestandteil von Free & Pro
            far	Font Awesome Regular	dünnere Linien / nicht gefüllt	nur einige Icons in Free, alle in Pro
            fab	Font Awesome Brands	Marken-Logos (Facebook, GitHub etc.)	komplett Free
*/
renderIconHtml(iconSpec, styles = {}, iconSize, iconColor) {
  const [iconSet, iconNameRaw] = (iconSpec || '').split(':');
  const iconName = iconNameRaw?.trim() || '';
  const styleParts = [];

  if (iconSize) styleParts.push(`font-size: ${iconSize}px`);
  if (iconColor) styleParts.push(`color: ${iconColor}`);
  for (const [key, value] of Object.entries(styles)) {
    styleParts.push(`${key}: ${value}`);
  }
  const styleAttr = styleParts.length ? ` style="${styleParts.join('; ')}"` : '';

  switch (iconSet) {
    case 'bs':
        return `<i class="bi ${iconName}"${styleAttr}></i>`;
    case 'f7':
        return `<i class="f7-icons"${styleAttr}>${iconName}</i>`;
    case 'md':
        return `<span class="material-icons"${styleAttr}>${iconName}</span>`;
    case 'fa':
        return `<i class="fa fa-${iconName}"${styleAttr}></i>`;
    case 'fas': // Font Awesome Solid 6.
    case 'fa-solid': // Font Awesome Solid 7.
    case 'far': // Font Awesome Regular
    case 'fa-regular': // Font Awesome Regular 7
    case 'fab': // Font Awesome Brands
    case 'fa-brands': // Font Awesome Brands
        return `<i class="${iconSet} ${iconName}"${styleAttr}></i>`;      
    default:
        return `<i class="${iconSpec}"${styleAttr}></i>`; // fallback
  }
}
}
/*
function checkFontAwesome6() {
  const result = {
    solid: false,
    regular: false,
    brands: false,
    any: false
  };

  // --- 1. CSS-Klassen-Prüfung
  const hasFAClass = Array.from(document.styleSheets).some(sheet => {
    try {
      return Array.from(sheet.cssRules || []).some(rule =>
        rule.selectorText &&
        (rule.selectorText.includes('.fa') || rule.selectorText.includes('.fa-solid'))
      );
    } catch (e) {
      return false; // CORS-Schutz umgehen
    }
  });

  if (!hasFAClass) {
    console.warn('Font Awesome CSS nicht gefunden.');
    return result;
  }

  // --- 2. Moderne Prüfung mit document.fonts
  if (document.fonts && document.fonts.check) {
    result.solid   = document.fonts.check('1em "Font Awesome 6 Free" 900');
    result.regular = document.fonts.check('1em "Font Awesome 6 Free" 400');
    result.brands  = document.fonts.check('1em "Font Awesome 6 Brands"');
    result.any     = result.solid || result.regular || result.brands;
    return result;
  }

  // --- 3. Fallback: DOM-Messung (nur Solid-Test)
  const testEl = document.createElement('i');
  testEl.className = 'fa-solid fa-star';
  testEl.style.position = 'absolute';
  testEl.style.left = '-9999px';
  document.body.appendChild(testEl);

  const w1 = testEl.offsetWidth;
  testEl.className = '';
  const w2 = testEl.offsetWidth;

  document.body.removeChild(testEl);

  result.solid = w1 !== w2;
  result.any   = result.solid;
  return result;
}

// --- Anwendung
const faStatus = checkFontAwesome6();

if (faStatus.any) {
  console.log('✅ Font Awesome 6 geladen:', faStatus);
} else {
  console.log('❌ Font Awesome 6 fehlt:', faStatus);
}




if (document.fonts) {   bootstrap icons
  const biLoaded = document.fonts.check('1em "bootstrap-icons"');
  console.log('Bootstrap Icons geladen?', biLoaded);
}


checkFA6();
*/
