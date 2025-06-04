/*
hilfsklasse zum erzeugen der f7 Icons
*/

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
    return this; // Für Kettenaufrufe
  }

  render() {
    // Icon-Attribute zusammenstellen bzw. funktion rufen
    const iconAttrs = [];
    // Icon-Name
    const iconName = this.params.icon || '';

    // Optionaler: zusätzliche CSS-Klassen für das Icon
    const iconClass = this.params.iconClass ? `f7-icons ${this.params.iconClass}` : 'f7-icons';
    iconAttrs.push(`class="${iconClass}"`);

    // Optional: Style-Attribute für Icon (z. B. Größe, Farbe)
    const styleParts = [];
    if (this.params.iconSize) styleParts.push(`font-size: ${this.params.iconSize}px`);
    if (this.params.iconColor) styleParts.push(`color: ${this.params.iconColor}`);
    if (styleParts.length) iconAttrs.push(`style="${styleParts.join('; ')}"`);

    if (iconName = 'gauge') { 
//          { sensorId: 'IQbattery_94_battery_stateOfCharge', icon: 'gauge', iconSize: 80, color: 'purple' },
         
       const renderstr = '
         gaugeContainer = document.createElement("div")
            gauge = document.createElement("div")
                gauge.classList.add("gauge")
                gauge.style.float = "left"
                gauge.id = "gauge-${this.params.chartId}-${this.params.index}";
              // Hier feste Größe setzen:
                gauge.style.width = ${this.params.iconSize}'px';
                gauge.style.height = ${this.params.iconSize}'px';
                gaugeContainer.appendChild(gauge);
            title = document.createElement('div')
                title.classList.add('block-title');
                title.style.float = 'left';
                title.textContent = ${this.params.title || ''}
                gaugeContainer.appendChild(title);
                const clearDiv = document.createElement('div');
                    clearDiv.style.clear = 'both';
                    gaugeContainer.appendChild(clearDiv);
                gaugeContainer.appendChild(clearDiv);
            F7app.gauge.create({
              el: "gauge-${this.params.chartId}-${this.params.index}",
              type: "circle",
              value: (sensor.sensorValue || 0) / 100,
              valueText: `${sensor.sensorValue ?? 0}%`,
              valueTextColor: config.color || '#2196f3',
              borderColor: config.color || '#2196f3',
              borderWidth: 10,
              labeltext: 'halloLabel'
            });
        `;
        return renderstr;
      } else {// standard icon generierern
      // HTML generieren
        return `
          <div class="f7-item" style="color: ${this.params.color || 'inherit'};">
            <i ${iconAttrs.join(' ')}>${iconName}</i>
            ${this.params.title || ''}
            ${this.params.description || ''}
          <br>hallo<br>
          </div>
        `;
      }
    }
  }
}
// Globale Nutzung ermöglichen
window.F7Item = F7Item; // damit das funktioniert 
/*
<script>
  document.addEventListener('DOMContentLoaded', function() {
    const item = new F7Item()
      .set('title', 'Telefon')
      .set('icon', 'phone')
      .set('iconSize', 24)
      .set('iconColor', 'blue')
      .set('description', 'Rufe uns an.');

    document.getElementById('features-container').innerHTML = item.render();
  });
</script>
.setAll({
      title: 'Support',
      icon: 'phone',
      iconSize: 28,
      iconColor: 'red',
      description: 'Rufe uns an!',
      color: '#444'
    });

beispiel für verwendung
<div id="features-container"></div>

<script src="{{ asset('bundles/contaohab/js/F7Item.js') }}"></script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Mehrere Items anlegen
    const items = [
      new F7Item()
        .set('title', 'Kontakt')
        .set('description', 'Rufe uns an')
        .set('icon', 'phone')
        .set('iconSize', 24)
        .set('iconColor', 'blue'),

      new F7Item()
        .set('title', 'Favoriten')
        .set('description', 'Speichere deine Favoriten')
        .set('icon', 'heart_fill')
        .set('iconSize', 24)
        .set('iconColor', 'red'),

      new F7Item()
        .set('title', 'Standort')
        .set('description', 'Hier findest du uns')
        .set('icon', 'location')
        .set('iconSize', 24)
        .set('iconColor', 'green')
    ];

    // Alle Items rendern und in den Container einfügen
    const container = document.getElementById('features-container');
    container.innerHTML = items.map(item => item.render()).join('');
  });
</script>
*/