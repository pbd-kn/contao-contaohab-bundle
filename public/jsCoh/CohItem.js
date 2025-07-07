class CohItem {
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
        container.classList.add('gauge-container');
        container.style.display = 'flex';
        container.style.alignItems = 'center';
        
        const gaugeCanvas = document.createElement('canvas');
        gaugeCanvas.classList.add('gauge-canvas');
        gaugeCanvas.id = gaugeId || 'gaugeIdDummy';
        gaugeCanvas.style.width = `${iconSize}px`;
        gaugeCanvas.style.height = `${iconSize}px`;
        gaugeCanvas.width = iconSize;
        gaugeCanvas.height = iconSize;
        gaugeCanvas.dataset.value = sensorValue;
        gaugeCanvas.dataset.max = 100;
        container.appendChild(gaugeCanvas);

        const titleEl = document.createElement('div');
        titleEl.style.marginLeft = '20px'; // z. B. 10px Abstand
        titleEl.style.flexShrink = '1';
        
        titleEl.classList.add('block-title');
        titleEl.textContent = titleText;
        container.appendChild(titleEl);


        wrapper.appendChild(container);

        // Initialisierung RadialGauge (canvas-gauges)
        setTimeout(() => {
        const canvas = document.querySelector(gaugeSelector);
        if (!canvas) return;

        const gauge = new RadialGauge({
            renderTo: canvas,
            width: iconSize,
            height: iconSize,
            minValue: 0,
            maxValue: 100,
            //value: 30,
            value: Number(sensorValue),           
            startAngle: 180,
            ticksAngle: 360,
            tickWidth: 1,          // ⬅️ Dickere Skalenstriche
            tickLength: 0,
            // Ring-Farben und -Breite
            barWidth: 15,                   // ⬅️ Ring dicker machen
            colorBarProgress: '#00cc00',
            colorBar: 'blue',
            // Zeiger
            needleType: 'arrow',
            needleWidth: 10,          // breite Zeiger
            needleCircleSize: 1,
            needleCircleOuter: true,
            needleCircleInner: false,
            colorNeedle: 'red',
            colorNeedleEnd: 'red',
            // Skala
            //majorTicks: ['0','10','20','30','40','50','60','70','80','90','100'],
            //minorTicks: 2,
            strokeTicks: false,
            colorNumbers: 'transparent',
            colorMajorTicks: 'transparent',
            colorMinorTicks: 'transparent',
            // Anzeige
            units: '',
            valueBox: false,
            // Hintergrund entfernen
            colorPlate: 'transparent',
            borders: false,
            borderOuterWidth: 0,
            borderMiddleWidth: 0,
            borderInnerWidth: 0,
            borderShadowWidth: 0,
            highlights: [],
            animation: false,
            highDpiSupport: true    
        });
        gauge.draw();
        }, 0);
    } else {

        // Klasse für das Icon, z. B. "bi cloud_sun"
        const iconClass = this.params.iconClass? `bi ${icon} ${this.params.iconClass}`: `bi ${icon}`;

        // Styles vorbereiten (font-size, color)
        const styleParts = [];
        if (this.params.iconSize) styleParts.push(`font-size: ${this.params.iconSize}px`);
        if (this.params.iconColor) styleParts.push(`color: ${this.params.iconColor}`);
        // Falls zusätzliche Styles definiert sind:
        if (this.params.styles) {
            for (const [key, value] of Object.entries(this.params.styles)) {
                styleParts.push(`${key}: ${value}`);
            }
        }
        const iconStyle = styleParts.length ? ` style="${styleParts.join('; ')}"` : '';

        // Zeilenumbrüche im Titel umwandeln
        const preparedTitle = titleText.replace(/(\r\n|\n\r|\r|\n)/g, '<br>');


const html = `
  <div class="coh--item" style="display: flex; align-items: center; color: ${this.params.color || 'inherit'};">
    <i class="${iconClass}"${iconStyle}></i>
    <div style="margin-left: 20px;">${preparedTitle}</div>
  </div>
`;
        

        // In DOM einfügen
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


