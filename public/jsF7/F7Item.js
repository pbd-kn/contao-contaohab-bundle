class F7Item {
  constructor() {
    this.params = {};
  }

  set(key, value) {
    this.params[key] = value;
    return this; // Für Kettenaufrufe
  }

  setAll(values) {
    if (typeof values === 'object' && values !== null) {
      for (const [key, value] of Object.entries(values)) {
        this.params[key] = value;
      }
    }
    return this;
  }

  render(wrapper) {
    if (!wrapper) {
      console.error('Kein Wrapper-Element angegeben!');
      return;
    }

    const {
      icon,
      sensorValue = 0,
      iconSize = 80,
      color = '#2196f3',
      title = '',
      description = '',
      gaugeId
    } = this.params;

    const titleText = description || title;

    if (icon === 'gauge') {
      const gaugeSelector = `#${gaugeId || 'gaugeIdDummy'}`;

      // DOM-Elemente erzeugen
      const container = document.createElement('div');
      container.classList.add('f7-gauge-container');

      const gaugeEl = document.createElement('div');
      gaugeEl.classList.add('gauge');
      gaugeEl.id = gaugeId || 'gaugeIdDummy';
      gaugeEl.style.width = `${iconSize}px`;
      gaugeEl.style.height = `${iconSize}px`;
      gaugeEl.style.float = 'left';
      container.appendChild(gaugeEl);

      const titleEl = document.createElement('div');
      titleEl.classList.add('block-title');
      titleEl.textContent = titleText;
      titleEl.style.float = 'left';
      container.appendChild(titleEl);

      const clearDiv = document.createElement('div');
      clearDiv.style.clear = 'both';
      container.appendChild(clearDiv);

      wrapper.appendChild(container);

      // Warten, bis DOM gerendert ist
      setTimeout(() => {
        if (window.F7app?.gauge) {
          window.F7app.gauge.create({
            el: gaugeSelector,
            type: 'circle',
            value: Number(sensorValue) / 100,
            valueText: `${sensorValue}`,
            valueTextColor: color,
            valueFontSize: Number(iconSize) || 40,
            borderColor: color,
            borderWidth: 10
          });
        } else {
          console.error('F7app.gauge nicht verfügbar!');
        }
      }, 0);
    } else {
      // Standard-Icon generieren
      const iconClass = this.params.iconClass ? `f7-icons ${this.params.iconClass}` : 'f7-icons';
      const styleParts = [];
      if (this.params.iconSize) styleParts.push(`font-size: ${this.params.iconSize}px`);
      if (this.params.iconColor) styleParts.push(`color: ${this.params.iconColor}`);
      const iconStyle = styleParts.length ? ` style="${styleParts.join('; ')}"` : '';
      const preparedTitle = titleText.replace(/(\r\n|\n\r|\r|\n)/g, '<br>');
      const html = `
        <div class="f7-item" style="color: ${this.params.color || 'inherit'};">
          <i class="${iconClass}"${iconStyle}>${icon}</i>
          ${preparedTitle}
        </div>
      `;
      wrapper.innerHTML += html;
    }
    /*
    Überprüfung ob fontgsawsom gedladen ist
    function isFontAwesomeLoaded() {
  const testEl = document.createElement('i');
  testEl.className = 'fa-solid fa-gauge';
  testEl.style.display = 'none';
  document.body.appendChild(testEl);

  const style = window.getComputedStyle(testEl);
  const fontFamily = style.getPropertyValue('font-family');

  document.body.removeChild(testEl);

  return fontFamily && fontFamily.toLowerCase().includes('fontawesome');
}

if (isFontAwesomeLoaded()) {
  console.log('✅ Font Awesome ist geladen.');
} else {
  console.warn('❌ Font Awesome ist NICHT geladen!');
}

    } else {
  // Font Awesome Icon generieren
  const iconClass = this.params.iconClass ? `fa-solid ${this.params.iconClass}` : 'fa-solid fa-gauge';
  const styleParts = [];
  if (this.params.iconSize) styleParts.push(`font-size: ${this.params.iconSize}px`);
  if (this.params.iconColor) styleParts.push(`color: ${this.params.iconColor}`);
  const iconStyle = styleParts.length ? ` style="${styleParts.join('; ')}"` : '';
  const preparedTitle = titleText.replace(/(\r\n|\n\r|\r|\n)/g, '<br>');
  const html = `
    <div class="f7-item" style="color: ${this.params.color || 'inherit'};">
      <i class="${iconClass}"${iconStyle}></i>
      ${preparedTitle}
    </div>
  `;
  wrapper.innerHTML += html;
}

    */
  }
}

// Global verfügbar machen
window.F7Item = F7Item;
