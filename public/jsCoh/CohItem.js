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
    sensorValue = 0,
    iconSize = 80,
    iconColor,
    textColor,
    title = '',
    description = '',
    gaugeId,
    styles = {}
  } = this.params;

  const titleText = description || title;

  if (icon === 'gauge') {
    // Gauge-HTML zurückgeben
    return `
      <div class="gauge-container" style="display:flex; align-items:center;">
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

/*    const preparedTitle = titleText.replace(/(\r\n|\n\r|\r|\n)/g, '<br>');

    return `
      <div class="coh--item" style="display: flex; align-items: center;${textColor ? ` color: ${textColor};` : ''}">
        <i class="${iconClass}"${iconStyle}></i>
        <div style="margin-left: 20px;">${preparedTitle}</div>
      </div>
    `;
*/
    const iconHtml = this.renderIconHtml(icon, styles, iconSize, iconColor);
    const preparedTitle = titleText.replace(/(\r\n|\n\r|\r|\n)/g, '<br>');

    return `
        <div class="coh--item" style="display: flex; align-items: center;${textColor ? ` color: ${textColor};` : ''}">
          ${iconHtml}
          <div style="margin-left: 20px;">${preparedTitle}</div>
        </div>

      `;

  }
}
/* erzeugt den code zum einbringen des Icons abhängig vom Iconset der Kennzeichnung vor dem icon bs:   ... */
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
    case 'fas': // Font Awesome Solid
    case 'far': // Font Awesome Regular
    case 'fab': // Font Awesome Brands
        return `<i class="${iconSet} fa-${iconName}"${styleAttr}></i>`;      
    default:
        return `<i class="${iconSpec}"${styleAttr}></i>`; // fallback
  }
}
}
