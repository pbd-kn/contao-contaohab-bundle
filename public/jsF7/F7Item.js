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

    const { icon, sensorValue = 0, iconSize = 80, color = '#2196f3', title = '' } = this.params;
    const titleText = `${this.params.title || ''}: ${this.params.sensorValue ?? ''} ${this.params.sensorEinheit ?? ''}`.trim();
    if (icon === 'gauge') {

        const gaugeId = this.params.gaugeId || 'gaugeIdDummy';
        const gaugeSelector = `#${gaugeId}`; 
        // Nutzt die Template-String-Syntax von JavaScript.
    
        // DOM-Elemente erzeugen
        const container = document.createElement('div');
        container.classList.add('f7-gauge-container');

        const gaugeEl = document.createElement('div');
            gaugeEl.classList.add('gauge');
            gaugeEl.id = gaugeId;
            gaugeEl.style.width = `${this.params.iconSize}px`;
            gaugeEl.style.height = `${this.params.iconSize}px`;
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
            value: sensorValue / 100,
            valueText: `${sensorValue}`,
            valueTextColor: color,
            borderColor: color,
            borderWidth: 10,
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

const html = `
  <div class="f7-item" style="color: ${this.params.color || 'inherit'};">
    <i class="${iconClass}"${iconStyle}>${icon}</i>
    ${titleText}
  </div>
`;
      wrapper.innerHTML += html;
    }
  }
}

// Global verfügbar machen
window.F7Item = F7Item;
