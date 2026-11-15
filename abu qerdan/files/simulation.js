(() => {
  const $ = id => document.getElementById(id);
  const t = (text, values) => window.FieldShiftI18n?.t(text, values) || text;
  const plotPoints = { TL: [150, 60], MT: [460, 56], TR: [770, 52], ML: [144, 186], CE: [461, 181], MR: [778, 176], BL: [138, 312], MB: [460, 306], BR: [785, 300] };
  const plotShapes = [["P1", "TL", "MT", "CE", "ML"], ["P2", "MT", "TR", "MR", "CE"], ["P3", "ML", "CE", "MB", "BL"], ["P4", "CE", "MR", "BR", "MB"]];
  const historyKey = "fieldShiftChatHistory";
  const safe = value => String(value ?? "").replace(/[&<>"']/g, character => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
  })[character]);

  let data = null;
  let zone = "monufia";
  let plots = {};
  let selectedPlot = "P1";
  let mapView = "NDVI";
  let simulationTimer = null;
  let latestResult = null;
  let latestMultiResults = null;
  let activePlans = [];
  let currentPlan = null;
  let conversationId = sessionStorage.getItem("fieldShiftConversationId") || "";

  function getFormConfig() {
    return {
      season: $("season").value,
      availableMm: $("water").value === "" ? null : Number($("water").value),
      temperatureChange: Number($("temp").value),
      rainfallChange: Number($("rain").value),
      soil: $("soil").value,
      area: Number($("area").value) || 10,
    };
  }

  function profile(crop) {
    if (!data) return null;
    return data.profile(zone, crop, $("season").value) || data.profile(null, crop, $("season").value);
  }

  function selectedCrops() {
    return Object.entries(plots).filter(([, crop]) => crop && profile(crop))
      .sort(([left], [right]) => left.localeCompare(right));
  }

  function color(value) {
    if (value === null || !Number.isFinite(value)) return "rgba(30,167,255,.14)";
    if (mapView === "NDVI") {
      const hue = Math.max(0, Math.min(120, (value - 0.25) * 240));
      return `hsla(${hue},70%,38%,.9)`;
    }
    if (mapView === "Irrigation") {
      const light = Math.max(24, Math.min(55, 55 - value / 18));
      return `hsla(195,70%,${light}%,.9)`;
    }
    if (mapView === "Temperature") {
      const hue = Math.max(0, Math.min(60, 60 - value * 2));
      return `hsla(${hue},75%,45%,.9)`;
    }
    return `hsla(38,60%,${Math.max(28, Math.min(60, 60 - value / 8))}%,.9)`;
  }

  function pointList(keys) {
    return keys.map(key => plotPoints[key].join(",")).join(" ");
  }

  function drawMap() {
    if (!$("plots")) return;
    $("plots").innerHTML = plotShapes.map(([id, ...keys]) => {
      const crop = plots[id];
      const record = crop ? profile(crop) : null;
      const metric = record ? mapView === "NDVI" ? record.ndvi :
        mapView === "Irrigation" ? record.irrigationMm :
          mapView === "Temperature" ? record.temperature : record.cropEtcMm : null;
      const centerX = (plotPoints[keys[0]][0] + plotPoints[keys[2]][0]) / 2;
      const centerY = (plotPoints[keys[0]][1] + plotPoints[keys[2]][1]) / 2;
      const unit = mapView === "NDVI" ? t("NDVI {value}", { value: window.FieldShift.format(metric, 3) }) :
        `${window.FieldShift.format(metric)} ${mapView === "Irrigation" || mapView === "ETc" ? "mm" : "°C"}`;
      return `<g data-plot="${id}" tabindex="0" role="button" aria-label="${safe(t("Select plot {plot}", { plot: id }))}">
        <polygon class="plot" points="${pointList(keys)}" fill="${color(metric)}"/>
        <polygon points="${pointList(keys)}" fill="url(#rows)" pointer-events="none"/>
        <polygon points="${pointList(keys)}" fill="none" stroke="${id === selectedPlot ? "#ffc83d" : "#25b8f0"}" stroke-width="${id === selectedPlot ? 4 : 2.5}" pointer-events="none"/>
        <rect x="${centerX - 56}" y="${centerY - 20}" width="112" height="40" rx="9" fill="#06121f" stroke="${id === selectedPlot ? "#ffc83d" : "rgba(255,255,255,.15)"}"/>
        <text x="${centerX}" y="${centerY - 3}" text-anchor="middle" fill="${id === selectedPlot ? "#ffc83d" : "#fff"}" font-size="13" font-weight="600">${id} · ${safe(crop ? window.FieldShift.displayCrop(crop) : t("Unassigned"))}</text>
        <text x="${centerX}" y="${centerY + 13}" text-anchor="middle" fill="#8fa3bd" font-size="10">${record ? safe(unit) : t("No crop assigned")}</text></g>`;
    }).join("");

    $("plots").querySelectorAll("[data-plot]").forEach(group => {
      group.addEventListener("click", () => {
        selectedPlot = group.dataset.plot;
        renderOverview();
      });
      group.addEventListener("keydown", event => {
        if (event.key === "Enter" || event.key === " ") {
          event.preventDefault();
          selectedPlot = group.dataset.plot;
          renderOverview();
        }
      });
    });
  }

  // ==========================================
  // DYNAMIC VISUAL FIELD GENERATOR (SVG)
  // ==========================================
  function generateFieldVisualSvg(visualStatus, cropName, waterSatisfactionPct, tempC) {
    const isHealthy = visualStatus === "healthy";
    const isModerate = visualStatus === "moderate";
    const isWilted = visualStatus === "wilted";
    const isArid = visualStatus === "arid_dry";

    // Sky colors
    const skyTop = isArid || isWilted ? "#3d2a1b" : "#17344f";
    const skyBottom = isArid || isWilted ? "#69492f" : "#28527a";

    // Soil colors
    const soilTop = isHealthy ? "#2b1c11" : isModerate ? "#382717" : isWilted ? "#4a3520" : "#63472c";
    const soilBottom = isHealthy ? "#150d07" : isModerate ? "#1c1209" : isWilted ? "#26190d" : "#3b2a1a";

    // Plant foliage colors
    const plantColor = isHealthy ? "#2ecc71" : isModerate ? "#7dbb42" : isWilted ? "#d4ac0d" : "#9e7d38";
    const plantColorShadow = isHealthy ? "#219653" : isModerate ? "#5e8e2f" : isWilted ? "#b7950b" : "#7d622c";
    const plantDroop = isWilted || isArid ? 10 : 0; // leaf droop angle

    // Soil Cracks for Arid/Drought state
    const crackPaths = isArid ? `
      <path d="M120 180 Q150 195 180 185 T230 205" stroke="#2b1a0d" stroke-width="2.5" fill="none" opacity=".8"/>
      <path d="M165 190 Q170 215 155 230" stroke="#2b1a0d" stroke-width="2" fill="none" opacity=".8"/>
      <path d="M380 175 Q410 185 440 178 T490 195" stroke="#2b1a0d" stroke-width="2.5" fill="none" opacity=".8"/>
      <path d="M425 182 Q440 205 430 225" stroke="#2b1a0d" stroke-width="2" fill="none" opacity=".8"/>
      <path d="M260 210 Q290 220 320 215" stroke="#2b1a0d" stroke-width="2" fill="none" opacity=".7"/>
    ` : isWilted ? `
      <path d="M140 190 Q170 200 200 195" stroke="#2b1a0d" stroke-width="1.8" fill="none" opacity=".5"/>
      <path d="M400 185 Q430 195 460 190" stroke="#2b1a0d" stroke-width="1.8" fill="none" opacity=".5"/>
    ` : "";

    // Generate crop rows with perspective
    let plantRows = "";
    const rowConfigs = [
      { y: 140, scale: 0.55, count: 14, spacing: 42, startX: 18 },
      { y: 165, scale: 0.75, count: 11, spacing: 54, startX: 25 },
      { y: 195, scale: 1.0, count: 9, spacing: 66, startX: 32 },
    ];

    rowConfigs.forEach(row => {
      for (let i = 0; i < row.count; i++) {
        // Skip some plants if arid/drought (sparse / stunted)
        if (isArid && (i % 3 === 2)) continue;
        if (isModerate && (i % 5 === 4)) continue;

        const x = row.startX + i * row.spacing;
        const stemHeight = 36 * row.scale;
        const leafWidth = 14 * row.scale;
        const leafHeight = (isWilted ? 18 : 22) * row.scale;
        const droopY = plantDroop * row.scale;

        plantRows += `
          <g transform="translate(${x}, ${row.y})">
            <!-- Stem -->
            <line x1="0" y1="0" x2="0" y2="${-stemHeight}" stroke="${plantColorShadow}" stroke-width="${3 * row.scale}"/>
            <!-- Leaves Left -->
            <path d="M0 ${-stemHeight * 0.4} Q${-leafWidth * 1.5} ${-stemHeight * 0.6 + droopY} ${-leafWidth} ${-stemHeight * 0.3 + droopY} Q${-leafWidth * 0.5} ${-stemHeight * 0.3} 0 ${-stemHeight * 0.2}" fill="${plantColor}"/>
            <!-- Leaves Right -->
            <path d="M0 ${-stemHeight * 0.5} Q${leafWidth * 1.5} ${-stemHeight * 0.7 + droopY} ${leafWidth} ${-stemHeight * 0.4 + droopY} Q${leafWidth * 0.5} ${-stemHeight * 0.4} 0 ${-stemHeight * 0.3}" fill="${plantColor}"/>
            <!-- Top Canopy / Spikes -->
            <circle cx="0" cy="${-stemHeight - 2}" r="${isHealthy ? 7 * row.scale : 4 * row.scale}" fill="${plantColor}"/>
            ${isHealthy ? `<circle cx="${3 * row.scale}" cy="${-stemHeight - 4}" r="${4 * row.scale}" fill="#3ddc84" opacity=".8"/>` : ""}
          </g>
        `;
      }
    });

    // Sun / Heat Haze
    const sunColor = isArid || isWilted ? "#ff914d" : "#ffc83d";
    const sunGlow = isArid || isWilted ? "rgba(255, 145, 77, .35)" : "rgba(255, 200, 61, .25)";

    return `
      <svg viewBox="0 0 600 240" xmlns="http://www.w3.org/2000/svg" style="border-radius:14px; background:#040a14;">
        <defs>
          <linearGradient id="sky-${visualStatus}" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="${skyTop}"/>
            <stop offset="100%" stop-color="${skyBottom}"/>
          </linearGradient>
          <linearGradient id="soil-${visualStatus}" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="${soilTop}"/>
            <stop offset="100%" stop-color="${soilBottom}"/>
          </linearGradient>
          <filter id="glow-${visualStatus}">
            <feGaussianBlur stdDeviation="6" result="blur"/>
            <feComposite in="SourceGraphic" in2="blur" operator="over"/>
          </filter>
        </defs>

        <!-- Sky -->
        <rect x="0" y="0" width="600" height="135" fill="url(#sky-${visualStatus})"/>

        <!-- Sun / Weather -->
        <circle cx="510" cy="45" r="32" fill="${sunColor}" opacity=".85" filter="url(#glow-${visualStatus})"/>
        <circle cx="510" cy="45" r="22" fill="#fff" opacity=".6"/>

        <!-- Distant Horizon -->
        <ellipse cx="300" cy="135" rx="340" ry="20" fill="${soilTop}" opacity=".9"/>

        <!-- Soil Bed -->
        <rect x="0" y="130" width="600" height="110" fill="url(#soil-${visualStatus})"/>

        <!-- Furrow Perspective Lines -->
        <line x1="0" y1="145" x2="600" y2="145" stroke="rgba(0,0,0,.25)" stroke-width="1.5"/>
        <line x1="0" y1="172" x2="600" y2="172" stroke="rgba(0,0,0,.3)" stroke-width="2"/>
        <line x1="0" y1="205" x2="600" y2="205" stroke="rgba(0,0,0,.35)" stroke-width="2.5"/>

        <!-- Soil Cracks (if drought) -->
        ${crackPaths}

        <!-- Crop Plants -->
        ${plantRows}

        <!-- Subtle Moisture Sheen if Healthy -->
        ${isHealthy ? `<rect x="0" y="130" width="600" height="110" fill="rgba(37,184,240,.04)" pointer-events="none"/>` : ""}

        <!-- Status Tag Inset -->
        <g transform="translate(18, 22)">
          <rect x="0" y="0" width="160" height="28" rx="8" fill="rgba(2,10,23,.75)" stroke="rgba(255,255,255,.15)"/>
          <circle cx="14" cy="14" r="5" fill="${isHealthy ? "#3ddc84" : isModerate ? "#ffc83d" : isWilted ? "#ff914d" : "#ff5d5d"}"/>
          <text x="26" y="18" fill="#fff" font-size="11" font-weight="600" font-family="system-ui">${safe(cropName)} · ${waterSatisfactionPct}% Water</text>
        </g>
      </svg>
    `;
  }

  // ==========================================
  // RENDER SIMULATION RESULTS (MODE A)
  // ==========================================
  function renderModeAResult(simResult) {
    const isAr = window.FieldShiftI18n?.language() === "ar";
    const water = simResult.waterBalance;
    const isDeficit = water.balanceM3 !== null && water.balanceM3 < 0;

    const svgField = generateFieldVisualSvg(
      simResult.visualStatus,
      simResult.cropDisplay,
      water.satisfactionPct,
      currentPlan?.expectedConditions?.meanTempC || 20
    );

    $("res").innerHTML = `
      <div class="serif sky" style="font-size:18px; margin-top:16px">${t("Historical profile + explicit scenario adjustments")}</div>
      <div class="h" style="font-size:30px; margin-bottom:16px">
        <span>${t("Simulation results")} · ${safe(simResult.planName)}</span>
        <small class="sky">${t("Local calculation complete")}</small>
      </div>

      <!-- Field Visualizer Box (Requirement 5) -->
      <div class="field-visualizer-box">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
          <span style="font-size:16px; font-weight:600; color:#fff">🌱 ${t("Field Condition")} — ${safe(simResult.cropDisplay)} (${simResult.areaHectares} ha)</span>
          <span class="plan-badge">${safe(simResult.overallRating)}</span>
        </div>
        ${svgField}
        <div class="field-hud">
          <div class="field-hud-status status--${simResult.visualStatus}">
            <i></i><span>${safe(simResult.growthConditionLabel)}</span>
          </div>
          <div style="font-size:13px; color:var(--mut)">
            ${t("Water Status")}: <b style="color:${isDeficit ? "#ff5d5d" : "#3ddc84"}">${safe(water.statusText)}</b>
          </div>
          <div style="font-size:13px; color:var(--sky)">
            ${t("Suitability Score")}: <b>${simResult.suitabilityScore}%</b>
          </div>
        </div>
      </div>

      <!-- Analysis and Recommendations (Requirement 6) -->
      <div class="verdict" style="border-color:${isDeficit ? "var(--bad)" : "var(--sky)"}; margin-top:18px;">
        <b>${safe(water.statusText)}</b><br>
        <p style="margin-top:6px; font-size:14px; line-height:1.5">${safe(simResult.fieldExplanation)}</p>
      </div>

      <div class="two" style="margin-top:18px;">
        <div class="card">
          <div class="h">💧 ${t("Water balance · whole farm")}</div>
          <div class="r"><span>${t("Estimated net irrigation demand")}</span><b>${window.FieldShift.format(water.demandM3)} m³</b></div>
          <div class="r"><span>${t("Available irrigation")}</span><b>${water.availableM3 === null ? t("Not provided") : `${window.FieldShift.format(water.availableM3)} m³`}</b></div>
          <div class="r"><span>${t("Net balance")}</span><b style="color:${isDeficit ? "#ff5d5d" : "#3ddc84"}">${water.balanceM3 === null ? t("Not calculated") : `${water.balanceM3 >= 0 ? "+" : ""}${window.FieldShift.format(water.balanceM3)} m³`}</b></div>
          <div class="r"><span>${t("Satisfaction")}</span><b>${water.satisfactionPct}%</b></div>
        </div>

        <div class="card">
          <div class="h">🌾 ${t("Estimated Production")}</div>
          <div class="r"><span>${t("Estimated Yield")}</span><b>${window.FieldShift.format(simResult.estimatedYield.estimatedT_Ha, 2)} t/ha</b></div>
          <div class="r"><span>${t("Total Harvest Projection")}</span><b>~${window.FieldShift.format(simResult.estimatedYield.totalTons)} tons</b></div>
          <div class="r"><span>${t("Historical Baseline")}</span><b>${window.FieldShift.format(simResult.estimatedYield.baselineT_Ha, 2)} t/ha</b></div>
          <small class="mut" style="font-size:11px; margin-top:8px; display:block">${safe(simResult.estimatedYield.citation)}</small>
        </div>
      </div>

      <!-- In-Depth Assessment Report -->
      <div class="card" style="margin-top:18px;">
        <div class="h">📋 ${t("Simulation Analysis & Recommendations")}</div>
        
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:16px; margin-top:12px;">
          <div>
            <h4 style="color:#3ddc84; font-size:14px; margin-bottom:6px">✔ ${t("Positive Factors")}</h4>
            <ul style="font-size:13px; color:#d1e2f6; padding-inline-start:18px; list-style:disc; line-height:1.6">
              ${simResult.positiveFactors.map(f => `<li>${safe(f)}</li>`).join("")}
            </ul>
          </div>

          <div>
            <h4 style="color:#ff914d; font-size:14px; margin-bottom:6px">⚠️ ${t("Negative Factors")}</h4>
            <ul style="font-size:13px; color:#d1e2f6; padding-inline-start:18px; list-style:disc; line-height:1.6">
              ${simResult.negativeFactors.length ? simResult.negativeFactors.map(f => `<li>${safe(f)}</li>`).join("") : `<li>${t("No critical bottlenecks under current inputs.")}</li>`}
            </ul>
          </div>
        </div>

        <div style="margin-top:16px; padding-top:14px; border-top:1px solid rgba(255,255,255,.08)">
          <h4 style="color:var(--sky); font-size:14px; margin-bottom:6px">💡 ${t("Recommended Improvements")}</h4>
          <ul style="font-size:13px; color:#d1e2f6; padding-inline-start:18px; list-style:circle; line-height:1.6">
            ${simResult.recommendedImprovements.map(r => `<li>${safe(r)}</li>`).join("")}
          </ul>
        </div>

        <p class="mut" style="font-size:12px; margin-top:16px; line-height:1.5">
          ${safe(simResult.scientificDisclaimer)}
        </p>
      </div>
    `;

    latestResult = simResult;
    localStorage.setItem("fieldShiftSimulationResult", JSON.stringify(simResult));
  }

  // ==========================================
  // RENDER SIMULATION RESULTS (MODE B - COMPARE ALL)
  // ==========================================
  function renderModeBResults(multiResults) {
    const isAr = window.FieldShiftI18n?.language() === "ar";
    latestMultiResults = multiResults;

    let cardsHtml = multiResults.map(sim => {
      const isDeficit = sim.waterBalance.balanceM3 !== null && sim.waterBalance.balanceM3 < 0;
      const svgField = generateFieldVisualSvg(
        sim.visualStatus,
        sim.cropDisplay,
        sim.waterBalance.satisfactionPct,
        20
      );

      return `
        <div class="multi-field-card ${sim.planId === currentPlan?.id ? "cur-active" : ""}">
          <h3>
            <span>${safe(sim.planName)}</span>
            <span class="plan-badge">${sim.suitabilityScore}%</span>
          </h3>
          <div style="margin-bottom:10px;">${svgField}</div>
          <div class="field-hud-status status--${sim.visualStatus}" style="margin-bottom:8px">
            <i></i><span>${safe(sim.growthConditionLabel)}</span>
          </div>
          <div class="r"><span>${t("Crop")}:</span><b>${safe(sim.cropDisplay)}</b></div>
          <div class="r"><span>${t("Net Irrigation")}:</span><b>${window.FieldShift.format(sim.waterBalance.demandM3)} m³</b></div>
          <div class="r"><span>${t("Balance")}:</span><b style="color:${isDeficit ? "#ff5d5d" : "#3ddc84"}">${sim.waterBalance.balanceM3 === null ? t("Not calculated") : `${sim.waterBalance.balanceM3 >= 0 ? "+" : ""}${window.FieldShift.format(sim.waterBalance.balanceM3)} m³`}</b></div>
          <div class="r"><span>${t("Estimated Production")}:</span><b style="color:#3ddc84">${window.FieldShift.format(sim.estimatedYield.estimatedT_Ha, 2)} t/ha</b></div>
          <p style="font-size:12px; color:var(--mut); margin-top:8px; line-height:1.4">${safe(sim.fieldExplanation)}</p>
        </div>
      `;
    }).join("");

    $("res").innerHTML = `
      <div class="serif sky" style="font-size:18px; margin-top:16px">${t("Mode B – Compare All Plans")}</div>
      <div class="h" style="font-size:30px; margin-bottom:16px">
        <span>${t("Side-by-Side Field Simulation Comparison")}</span>
        <small class="sky">${multiResults.length} ${t("Plans Simulated")}</small>
      </div>
      <p class="mut" style="font-size:14px; margin-bottom:14px">${t("Each plan is simulated with its distinct agronomic parameters, water requirements, and management regime.")}</p>

      <!-- Side-by-Side Visual Field Visualizations (Requirement 5) -->
      <div class="multi-field-grid">
        ${cardsHtml}
      </div>

      <div class="card" style="margin-top:24px;">
        <div class="h">📊 ${t("Cross-Plan Simulation Takeaways")}</div>
        <div style="font-size:14px; line-height:1.6; color:#d1e2f6">
          <p>• <b>${safe(multiResults[0]?.planName)}</b>: ${safe(multiResults[0]?.fieldExplanation)}</p>
          <p style="margin-top:8px">• <b>${safe(multiResults[1]?.planName)}</b>: ${safe(multiResults[1]?.fieldExplanation)}</p>
        </div>
        <p class="mut" style="font-size:12px; margin-top:14px">
          ${t("Scientific Disclaimer: These simulation outputs are screening evaluations calculated from historical records and FAO-56/33 equations; they do not represent guaranteed real-world field contracts.")}
        </p>
      </div>
    `;
  }

  // ==========================================
  // RUN SIMULATION HANDLERS
  // ==========================================
  function runIndividualSimulation() {
    if (simulationTimer) return;
    $("current-sim-mode-badge").textContent = t("Mode A – Individual");
    $("ov").style.display = "grid";
    let progress = 0;
    $("steps").innerHTML = [
      "Load plan parameters & historical crop profile",
      "Apply climate scenario (temperature & rainfall)",
      "Calculate ETc, effective rainfall & net irrigation",
      "Evaluate water satisfaction & growth stress",
      "Generate dynamic field visual & agronomic assessment",
    ].map((stepText, idx) => `
      <div class="st ${idx === 0 ? "on" : ""}" data-step="${idx}">
        <span>◔ ${t(stepText)}</span><span>${t("In progress")}</span>
      </div>
    `).join("");

    simulationTimer = setInterval(() => {
      progress += 20;
      $("pi").style.width = `${progress}%`;
      $("pp").textContent = `${progress}%`;
      $("pt").textContent = t("Simulating individual plan…");
      $("steps").querySelectorAll("[data-step]").forEach((item, idx) => {
        item.className = `st ${idx < progress / 20 - 1 ? "ok" : idx === progress / 20 - 1 ? "on" : ""}`;
      });

      if (progress >= 100) {
        clearInterval(simulationTimer);
        simulationTimer = null;
        $("ov").style.display = "none";

        // Generate or fetch current plan
        syncFormToCurrentPlan();
        const simResult = window.FieldShift.SimulationEngine.simulatePlan(currentPlan, data);
        renderModeAResult(simResult);
        renderOverview();
        $("res").scrollIntoView({ behavior: "smooth" });
      }
    }, 120);
  }

  function runCompareAllPlans() {
    if (simulationTimer) return;
    $("current-sim-mode-badge").textContent = t("Mode B – All Plans");
    $("ov").style.display = "grid";
    let progress = 0;
    $("steps").innerHTML = [
      "Retrieve all generated agricultural plans",
      "Run separate ETc and irrigation calculations for every plan",
      "Determine water deficits and stress factors per plan",
      "Render visual field graphics side by side",
      "Aggregate comparative agronomic assessments",
    ].map((stepText, idx) => `
      <div class="st ${idx === 0 ? "on" : ""}" data-step="${idx}">
        <span>◔ ${t(stepText)}</span><span>${t("In progress")}</span>
      </div>
    `).join("");

    simulationTimer = setInterval(() => {
      progress += 20;
      $("pi").style.width = `${progress}%`;
      $("pp").textContent = `${progress}%`;
      $("pt").textContent = t("Simulating all plans in parallel…");
      $("steps").querySelectorAll("[data-step]").forEach((item, idx) => {
        item.className = `st ${idx < progress / 20 - 1 ? "ok" : idx === progress / 20 - 1 ? "on" : ""}`;
      });

      if (progress >= 100) {
        clearInterval(simulationTimer);
        simulationTimer = null;
        $("ov").style.display = "none";

        if (!activePlans.length) {
          activePlans = window.FieldShift.PlanningEngine.generatePlans(getFormConfig(), data);
        }
        const multiResults = window.FieldShift.SimulationEngine.simulateAllPlans(activePlans, data);
        renderModeBResults(multiResults);
        renderOverview();
        $("res").scrollIntoView({ behavior: "smooth" });
      }
    }, 130);
  }

  function cancelSimulation() {
    if (simulationTimer) clearInterval(simulationTimer);
    simulationTimer = null;
    $("ov").style.display = "none";
  }

  function syncFormToCurrentPlan() {
    if (!currentPlan) {
      currentPlan = window.FieldShift.getPlan() || activePlans[0] || window.FieldShift.PlanningEngine.generatePlans(getFormConfig(), data)[0];
    }
    const form = getFormConfig();
    currentPlan.areaHectares = form.area;
    currentPlan.soil = form.soil;
    currentPlan.season = form.season;
    currentPlan.scenario.temperatureChange = form.temperatureChange;
    currentPlan.scenario.rainfallChange = form.rainfallChange;
    currentPlan.scenario.availableWaterMm = form.availableMm;
    currentPlan.waterRequirements.availableWaterMm = form.availableMm;
    if (form.availableMm !== null) {
      currentPlan.waterRequirements.availableWaterM3 = Math.round(form.availableMm * form.area * 10);
      currentPlan.waterRequirements.balanceM3 = currentPlan.waterRequirements.availableWaterM3 - currentPlan.waterRequirements.totalWaterM3;
      currentPlan.waterRequirements.deficitMm = currentPlan.waterRequirements.balanceM3 < 0 ? Math.abs(currentPlan.waterRequirements.balanceM3) / (form.area * 10) : 0;
      currentPlan.waterRequirements.waterSatisfactionPct = Math.min(100, Math.round((currentPlan.waterRequirements.availableWaterM3 / currentPlan.waterRequirements.totalWaterM3) * 100));
    }
    window.FieldShift.savePlan(currentPlan);
  }

  function populatePlanSelector() {
    const selector = $("plan-select");
    if (!selector) return;
    selector.innerHTML = activePlans.map(p => `
      <option value="${safe(p.id)}" ${p.id === currentPlan?.id ? "selected" : ""}>
        ${safe(p.name)} (${safe(p.badge)})
      </option>
    `).join("");

    selector.addEventListener("change", () => {
      const selected = activePlans.find(p => p.id === selector.value);
      if (selected) {
        currentPlan = selected;
        window.FieldShift.savePlan(selected);
        loadPlanIntoForm(selected);
        renderOverview();
      }
    });
  }

  function loadPlanIntoForm(plan) {
    if (!plan) return;
    $("area").value = plan.areaHectares;
    $("soil").value = plan.soil;
    $("season").value = plan.season;
    if (plan.scenario?.availableWaterMm !== undefined && plan.scenario?.availableWaterMm !== null) {
      $("water").value = plan.scenario.availableWaterMm;
    }
    $("temp").value = String(plan.scenario?.temperatureChange || 0);
    $("rain").value = String(plan.scenario?.rainfallChange || 0);

    plots = { P1: plan.crop };
    $("fc").textContent = `${safe(plan.cropDisplay)} → P1`;
  }

  function reset() {
    if (currentPlan) {
      loadPlanIntoForm(currentPlan);
    } else {
      $("season").value = "winter";
      $("area").value = 10;
      $("water").value = "";
      $("temp").value = "0";
      $("rain").value = "0";
      $("soil").value = "clay";
      plots = { P1: "wheat" };
    }
    $("res").replaceChildren();
    latestResult = null;
    latestMultiResults = null;
    renderOverview();
  }

  // ==========================================
  // CHATBOT (ABU QERDAN) INTEGRATION
  // ==========================================
  function addChatMessage(role, text, evidence) {
    const item = document.createElement("div");
    item.className = `m ${role === "user" ? "u" : ""}`;
    item.textContent = text;
    if (evidence?.length) {
      const details = document.createElement("small");
      details.className = "mut";
      details.style.display = "block";
      details.style.marginTop = "8px";
      details.textContent = evidence.join(" ");
      item.appendChild(details);
    }
    $("msgs").appendChild(item);
    $("msgs").scrollTop = $("msgs").scrollHeight;
    try {
      const history = JSON.parse(localStorage.getItem(historyKey) || "[]");
      const messages = Array.isArray(history) ? history : [];
      messages.push({ role, text, evidence: evidence || [], mode: "" });
      localStorage.setItem(historyKey, JSON.stringify(messages.slice(-50)));
    } catch (err) {}
  }

  async function askQuestion(question) {
    const text = question.trim();
    if (!text) return;
    addChatMessage("user", text);
    $("ask").value = "";

    try {
      const result = await window.FieldShift.askAgent(
        text,
        currentPlan,
        data,
        conversationId
      );
      conversationId = result.conversation_id;
      sessionStorage.setItem("fieldShiftConversationId", conversationId);
      const answer = result.reply || result.local?.reply;
      addChatMessage("assistant", answer, result.local?.evidence);
    } catch (error) {
      addChatMessage("assistant", t("The assistant request failed: {error}", { error: error.message }));
    }
  }

  function renderOverview() {
    if (!data) return;
    const settings = getFormConfig();
    $("farm-area-label").textContent = `${window.FieldShift.format(settings.area)} ha`;
    $("farm-zone-label").textContent = window.FieldShiftI18n?.zone(zone) || zone;
    const cropCount = selectedCrops().length;
    $("farm-crop-count").textContent = cropCount === 1 ? t("1 crop") : t("{count} crops", { count: cropCount });

    $("tabs").innerHTML = ["NDVI", "Irrigation", "Temperature", "ETc"].map(view =>
      `<button type="button" class="${mapView === view ? "on" : ""}" data-view="${view}">${t(view)}</button>`
    ).join("");
    $("tabs").querySelectorAll("[data-view]").forEach(button => button.addEventListener("click", () => {
      mapView = button.dataset.view;
      renderOverview();
    }));

    $("selinfo").textContent = t("{plot} is a selectable illustration only. Plot boundaries and areas are not provided by the project datasets.", { plot: selectedPlot });
    const selected = selectedCrops();
    $("fp").textContent = selected.map(([plot]) => plot).join(", ") || t("No crops assigned");
    $("fc").textContent = selected.map(([plot, crop]) => `${window.FieldShift.displayCrop(crop)} → ${plot}`).join(" / ") || t("No crops assigned");

    const profiles = data.crops.map(crop => ({ crop, record: profile(crop) })).filter(item => item.record);
    const summaryProfile = selected.length ? profile(selected[0][1]) : profiles[0]?.record;
    const summaryCrop = selected.length
      ? window.FieldShift.displayCrop(selected[0][1])
      : profiles[0] ? window.FieldShift.displayCrop(profiles[0].crop) : "available crop";

    const soilLabel = window.FieldShiftI18n?.soil(settings.soil) || settings.soil;
    const seasonLabel = window.FieldShiftI18n?.season(settings.season) || settings.season;

    $("three").innerHTML = `
      <div class="card"><h3>🌱 ${t("Soil profile · historical data")}</h3>
        <div class="r"><span>${t("Soil type selected")}</span><b>${safe(soilLabel)}</b></div>
        <div class="r"><span>${t("Mean dataset pH · {crop}", { crop: summaryCrop })}</span><b>${window.FieldShift.format(summaryProfile?.soilPh, 2)}</b></div>
        <div class="r"><span>${t("Clay / sand · {crop}", { crop: summaryCrop })}</span><b>${window.FieldShift.format(summaryProfile?.clayPct)}% / ${window.FieldShift.format(summaryProfile?.sandPct)}%</b></div>
        <p class="mut" style="font-size:12px;margin-top:8px">${t("Selected soil type is screened against crop catalog preferences.")}</p>
      </div>
      <div class="card"><h3>💧 ${t("Water profile · {season}", { season: seasonLabel })}</h3>
        <div class="r"><span>${t("Selected area")}</span><b>${window.FieldShift.format(settings.area)} ha</b></div>
        <div class="r"><span>${t("Entered supply")}</span><b>${settings.availableMm === null ? t("Unknown") : `${window.FieldShift.format(settings.availableMm)} mm/season`}</b></div>
        <div class="r"><span>${t("Profiles found")}</span><b>${window.FieldShift.format(selected.length, 0)}</b></div>
      </div>
      <div class="card"><h3>⛅ ${t("Climate profile · {crop}", { crop: summaryCrop })}</h3>
        <div class="r"><span>${t("Historical temperature")}</span><b>${window.FieldShift.format(summaryProfile?.temperature)} °C</b></div>
        <div class="r"><span>${t("Mean monthly max")}</span><b>${window.FieldShift.format(summaryProfile?.temperatureMax)} °C</b></div>
        <div class="r"><span>${t("Scenario change")}</span><b>${settings.temperatureChange >= 0 ? "+" : ""}${window.FieldShift.format(settings.temperatureChange)} °C</b></div>
      </div>
    `;

    $("selc").innerHTML = selected.map(([plot, crop]) => `
      <div class="sel">
        <span>${safe(window.FieldShift.displayCrop(crop))}<br><small class="sky">${safe(plot)} · ${t("{value} ha illustrative share", { value: window.FieldShift.format(settings.area / Math.max(1, selected.length)) })}</small></span>
        <button type="button" data-remove="${safe(plot)}" aria-label="${safe(t("Remove crop from {plot}", { plot }))}">✕</button>
      </div>
    `).join("") || `<small class="mut">${t("No crops assigned")}</small>`;

    $("selc").querySelectorAll("[data-remove]").forEach(button => button.addEventListener("click", () => {
      delete plots[button.dataset.remove];
      renderOverview();
    }));

    $("lib").innerHTML = profiles.map(({ crop, record }) => {
      const details = window.FieldShift.displayCrop(crop);
      return `
        <div class="crop">
          <h4>${safe(details)}</h4>
          <div class="r"><span>${t("Historical seasonal irrigation")}</span><span>${window.FieldShift.format(record.irrigationMm)} mm</span></div>
          <div class="r"><span>${t("Season / records")}</span><span>${seasonLabel} / ${window.FieldShift.format(record.seasons, 0)}</span></div>
          <div class="r"><span>${t("NDVI / temperature")}</span><span>${window.FieldShift.format(record.ndvi, 3)} / ${window.FieldShift.format(record.temperature)} °C</span></div>
          <button class="y" type="button" data-add="${safe(crop)}">${t("Assign to {plot}", { plot: selectedPlot })}</button>
        </div>
      `;
    }).join("") || `<p class="mut">${t("No complete crop profile for this zone and season.")}</p>`;

    $("lib").querySelectorAll("[data-add]").forEach(button => button.addEventListener("click", () => {
      plots[selectedPlot] = button.dataset.add;
      renderOverview();
    }));

    drawMap();
  }

  async function initialize() {
    try {
      data = await window.FieldShift.loadProjectData();
      activePlans = window.FieldShift.getSavedPlans();
      currentPlan = window.FieldShift.getPlan();

      const sampleResponse = await fetch("/sample/farm_summary.json");
      const sample = sampleResponse.ok ? await sampleResponse.json() : {};
      window.fieldShiftSummary = currentPlan?.farmSummary || sample;

      zone = currentPlan?.zone || window.FieldShift.nearestZone(data, sample.location?.latitude, sample.location?.longitude) || "monufia";

      if (!activePlans.length) {
        activePlans = window.FieldShift.PlanningEngine.generatePlans({
          zone,
          crop: currentPlan?.crop || "wheat",
          season: currentPlan?.season || "winter",
          areaHectares: currentPlan?.areaHectares || 10,
          soil: currentPlan?.soil || "clay",
        }, data);
        window.FieldShift.savePlans(activePlans);
      }

      if (!currentPlan) {
        currentPlan = activePlans[0];
        window.FieldShift.savePlan(currentPlan);
      }

      populatePlanSelector();
      loadPlanIntoForm(currentPlan);

      $("farm-name").textContent = t("Illustrative sample farm");
      $("farm-location").textContent = "Monufia, Egypt";
      $("coord-label").textContent = "30.56° N · nearest data zone";

      // Form change listeners
      ["season", "water", "temp", "rain", "soil", "area"].forEach(id => {
        $(id)?.addEventListener("change", () => {
          syncFormToCurrentPlan();
          renderOverview();
        });
      });

      // Chat interactions
      $("chat").style.display = "none";
      $("ask").addEventListener("keydown", event => {
        if (event.key === "Enter") askQuestion(event.target.value);
      });

      $("qs").innerHTML = [
        "Why did Plan A perform better than Plan B?",
        "Why does this field look unhealthy?",
        "Is the water supply sufficient?",
        "What should I change to improve the selected plan?",
        "Which plan is most suitable for my conditions?",
        "What happens if I increase the available water?",
        "Can you explain the simulation results?",
      ].map(q => `<button class="q" type="button" data-question="${safe(t(q))}">${safe(t(q))}</button>`).join("");

      $("qs").querySelectorAll("button").forEach(b => {
        b.addEventListener("click", () => askQuestion(b.dataset.question));
      });

      renderOverview();
    } catch (error) {
      $("res").textContent = t("Simulation unavailable: {error}", { error: error.message });
    }
  }

  window.addEventListener("fieldshift:language-changed", () => {
    if (!data) return;
    populatePlanSelector();
    renderOverview();
    if (latestResult) renderModeAResult(latestResult);
    else if (latestMultiResults) renderModeBResults(latestMultiResults);
  });

  window.run = runIndividualSimulation;
  window.runAll = runCompareAllPlans;
  window.reset = reset;
  window.cancel = cancelSimulation;

  initialize();
})();
