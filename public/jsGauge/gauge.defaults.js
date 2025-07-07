if (window.RadialGauge && RadialGauge.prototype) {
  RadialGauge.prototype.options = RadialGauge.prototype.options || {};
  if (!RadialGauge.__defaultsInitialized) {
    Object.assign(RadialGauge.prototype.options, {
      startAngle: 180,
      ticksAngle: 360,
      minValue: 0,
      maxValue: 100,
      barWidth: 15,
      colorBarProgress: '#00cc00',
      colorBar: 'blue',
      needleType: 'arrow',
      needleWidth: 10,
      needleCircleSize: 1,
      needleCircleOuter: true,
      needleCircleInner: false,
      colorNeedle: 'red',
      colorNeedleEnd: 'red',
      valueBox: false,
      units: '',
      strokeTicks: false,
      colorNumbers: 'transparent',
      colorMajorTicks: 'transparent',
      colorMinorTicks: 'transparent',
      majorTicks: [],
      minorTicks: 0,
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
    RadialGauge.__defaultsInitialized = true;
  }
}
