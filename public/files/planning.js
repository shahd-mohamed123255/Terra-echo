(() => {
  const $ = id => document.getElementById(id);
  const conversationKey = "fieldShiftConversationId";
  const historyKey = "fieldShiftChatHistory";
  const t = (text, values) => window.FieldShiftI18n?.t(text, values) || text;

  let data = null;
  let conversationId = sessionStorage.getItem(conversationKey) || "";
  let activePlans = [];
  let selectedPlanId = "plan_a";

  function appendMessage(role, text, evidence, mode, persist = true) {
    const message = document.createElement("div");
    message.className = `chat-message ${role}`;
    message.textContent = text;
    if (evidence?.length) {
      const evidenceBox = document.createElement("div");
      evidenceBox.className = "evidence";
      evidenceBox.textContent = evidence.join("\n\n");
      message.appendChild(evidenceBox);
    }
    if (mode) {
      const label = document.createElement("div");
      label.className = "mode-tag";
      label.textContent = mode;
      message.appendChild(label);
    }
    $("chat-log").appendChild(message);
    $("chat-log").scrollTop = $("chat-log").scrollHeight;
    if (persist) {
      const history = readHistory();
      history.push({ role, text, evidence: evidence || [], mode: mode || "" });
      localStorage.setItem(historyKey, JSON.stringify(history.slice(-50)));
    }
  }

  function readHistory() {
    try {
      const history = JSON.parse(localStorage.getItem(historyKey) || "[]");
      return Array.isArray(history) ? history.filter(item =>
        item && ["user", "assistant"].includes(item.role) && typeof item.text === "string"
      ) : [];
    } catch (error) {
      console.error("Saved chat history could not be read.", error);
      return [];
    }
  }

  function restoreHistory() {
    readHistory().forEach(item => appendMessage(item.role, item.text, item.evidence, t(item.mode), false));
  }

  function getFormInputs() {
    return {
      areaHectares: Number($("farm-area")?.value) || 10,
      crop: $("farm-crop")?.value || "wheat",
      soil: $("farm-soil")?.value || "clay",
      zone: $("farm-zone")?.value || "monufia",
      season: $("farm-season")?.value || "winter",
      availableWaterMm: $("farm-water")?.value !== "" ? Number($("farm-water")?.value) : null,
      tempScenario: Number($("farm-temp")?.value || 0),
      rainScenario: Number($("farm-rain")?.value || 0),
    };
  }

  function generateAndSavePlans() {
    if (!data) return [];
    const inputs = getFormInputs();
    const plans = window.FieldShift.PlanningEngine.generatePlans(inputs, data);
    activePlans = plans;
    window.FieldShift.savePlans(plans);

    // Maintain active plan selection or select first
    let selected = plans.find(p => p.id === selectedPlanId);
    if (!selected) {
      selected = plans[0];
      selectedPlanId = selected?.id || "plan_a";
    }
    if (selected) {
      window.FieldShift.savePlan(selected);
    }
    return plans;
  }

  function selectPlan(planId) {
    selectedPlanId = planId;
    const plan = activePlans.find(p => p.id === planId);
    if (plan) {
      window.FieldShift.savePlan(plan);
      // Sync form crop if primary
      if ($("farm-crop") && $("farm-crop").value !== plan.crop) {
        $("farm-crop").value = plan.crop;
      }
      renderPlansUI();
      renderComparisonTable();
      $("plan-note").textContent = t("{crop} · {season} selected. Continue to Simulation to adjust scenario assumptions.", {
        crop: plan.cropDisplay,
        season: window.FieldShiftI18n?.season(plan.season) || plan.season,
      });
    }
  }

  function openPlanModal(planId) {
    const plan = activePlans.find(p => p.id === planId);
    if (!plan) return;
    const isAr = window.FieldShiftI18n?.language() === "ar";
    const escape = window.FieldShift.escapeHtml;

    $("modal-plan-title").textContent = plan.name;
    const water = plan.waterRequirements;
    const cond = plan.expectedConditions;
    const yieldData = plan.estimatedYield;
    const breakdown = plan.scoreBreakdown;

    $("modal-plan-body").innerHTML = `
      <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
        <span class="plan-badge plan-badge--featured">${escape(plan.badge)}</span>
        <span style="font-size:18px; font-weight:700; color:var(--sky)">${t("Suitability Score")}: ${plan.suitabilityScore}/100</span>
      </div>
      <p style="color:var(--muted); font-size:15px; margin-bottom:18px; line-height:1.6">${escape(plan.description)}</p>

      <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:20px;">
        <div class="card" style="padding:14px; background:rgba(5,8,22,.6)">
          <h4 style="font-size:15px; color:var(--sky); margin-bottom:8px">💧 ${t("Water Consumption")}</h4>
          <div class="plan-spec-row"><span>${t("Crop ETc")}:</span><b>${window.FieldShift.format(water.seasonalEtcMm)} mm</b></div>
          <div class="plan-spec-row"><span>${t("Effective Rain")}:</span><b>${window.FieldShift.format(water.effectiveRainMm)} mm</b></div>
          <div class="plan-spec-row"><span>${t("Net Irrigation")}:</span><b>${window.FieldShift.format(water.netIrrigationMm)} mm</b></div>
          <div class="plan-spec-row"><span>${t("Total Volume")}:</span><b>${window.FieldShift.format(water.totalWaterM3)} m³</b></div>
          <div class="plan-spec-row"><span>${t("Satisfaction")}:</span><b>${water.waterSatisfactionPct}%</b></div>
        </div>

        <div class="card" style="padding:14px; background:rgba(5,8,22,.6)">
          <h4 style="font-size:15px; color:var(--sky); margin-bottom:8px">🌾 ${t("Estimated Production")}</h4>
          <div class="plan-spec-row"><span>${t("Crop")}:</span><b>${escape(plan.cropDisplay)}</b></div>
          <div class="plan-spec-row"><span>${t("Yield per Ha")}:</span><b>${window.FieldShift.format(yieldData.estimatedT_Ha, 2)} t/ha</b></div>
          <div class="plan-spec-row"><span>${t("Total Harvest")}:</span><b>${window.FieldShift.format(yieldData.totalTons)} t</b></div>
          <div class="plan-spec-row"><span>${t("Historical Benchmark")}:</span><b>${window.FieldShift.format(yieldData.baselineT_Ha, 2)} t/ha</b></div>
          <small class="mut" style="display:block; margin-top:6px; font-size:11px">${escape(yieldData.citation)}</small>
        </div>

        <div class="card" style="padding:14px; background:rgba(5,8,22,.6)">
          <h4 style="font-size:15px; color:var(--sky); margin-bottom:8px">⛅ ${t("Environmental Compatibility")}</h4>
          <div class="plan-spec-row"><span>${t("Mean Temperature")}:</span><b>${window.FieldShift.format(cond.meanTempC)} °C</b></div>
          <div class="plan-spec-row"><span>${t("Optimum Range")}:</span><b>${cond.optimalTempRange[0]}–${cond.optimalTempRange[1]} °C</b></div>
          <div class="plan-spec-row"><span>${t("Soil pH")}:</span><b>${window.FieldShift.format(cond.soilPh, 2)}</b></div>
          <div class="plan-spec-row"><span>${t("Observed NDVI")}:</span><b>${window.FieldShift.format(cond.ndvi, 3)}</b></div>
        </div>
      </div>

      <div style="margin-bottom:16px;">
        <h4 style="font-size:15px; color:var(--sky); margin-bottom:6px">📊 ${t("Suitability Score")} & Breakdown</h4>
        <p style="font-size:13.5px; color:#d1e2f6">${escape(breakdown.explanation)}</p>
      </div>

      <div style="margin-bottom:16px;">
        <h4 style="font-size:15px; color:var(--sky); margin-bottom:6px">⚙️ ${t("Resource Requirements")}</h4>
        <p style="font-size:13.5px; color:#d1e2f6">${escape(plan.requiredResources)}</p>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:20px;">
        <div style="background:rgba(61,220,132,.08); border:1px solid rgba(61,220,132,.3); border-radius:12px; padding:12px;">
          <h5 style="color:#3ddc84; margin-bottom:6px">✔ ${isAr ? "المزايا الإيجابية" : "Advantages"}</h5>
          <ul style="font-size:13px; list-style:disc; padding-inline-start:16px; color:#d1e2f6">
            ${plan.advantages.map(a => `<li>${escape(a)}</li>`).join("")}
          </ul>
        </div>
        <div style="background:rgba(255,93,93,.08); border:1px solid rgba(255,93,93,.3); border-radius:12px; padding:12px;">
          <h5 style="color:#ff914d; margin-bottom:6px">⚠️ ${isAr ? "المحددات والمخاطر" : "Risks & Constraints"}</h5>
          <ul style="font-size:13px; list-style:disc; padding-inline-start:16px; color:#d1e2f6">
            ${plan.risks.map(r => `<li>${escape(r)}</li>`).join("")}
          </ul>
        </div>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:12px;">
        <button class="btn-action btn-select" id="modal-select-btn" type="button">${t("Select This Plan")}</button>
        <a class="btn-action btn-sim" href="/simulation">${t("Run Simulation")}</a>
      </div>
    `;

    $("modal-select-btn").addEventListener("click", () => {
      selectPlan(plan.id);
      $("plan-detail-modal").style.display = "none";
    });

    $("plan-detail-modal").style.display = "grid";
  }

  function renderPlansUI() {
    if (!data || !activePlans.length) return;
    const escape = window.FieldShift.escapeHtml;
    const isAr = window.FieldShiftI18n?.language() === "ar";

    $("crop-plans").innerHTML = activePlans.map(plan => {
      const isSelected = plan.id === selectedPlanId;
      const water = plan.waterRequirements;
      const cond = plan.expectedConditions;
      const yieldData = plan.estimatedYield;

      return `
        <article class="plan-card ${isSelected ? "plan--active" : ""}" data-plan-id="${escape(plan.id)}">
          <div class="plan-card__top">
            <h3 class="plan-card__title">${escape(plan.name)}</h3>
            <span class="plan-badge ${plan.type === "water_conservative" ? "plan-badge--featured" : ""}">${escape(plan.badge)}</span>
          </div>

          <div class="plan-score-box">
            <div class="plan-score-val">${plan.suitabilityScore}%</div>
            <div class="plan-score-bar-bg">
              <div class="plan-score-bar-fill" style="width:${plan.suitabilityScore}%"></div>
            </div>
            <small style="color:var(--muted); font-size:12px;">${t("Suitability Score")}</small>
          </div>

          <p style="font-size:13.5px; color:var(--muted); line-height:1.5; margin-bottom:12px">${escape(plan.description)}</p>

          <div class="plan-specs">
            <div class="plan-spec-row">
              <span>${t("Crop")}:</span>
              <b>${escape(plan.cropDisplay)} · ${t(plan.season === "summer" ? "Summer" : "Winter")}</b>
            </div>
            <div class="plan-spec-row">
              <span>${t("Water Consumption")}:</span>
              <b>${window.FieldShift.format(water.netIrrigationMm)} mm (${window.FieldShift.format(water.totalWaterM3)} m³)</b>
            </div>
            <div class="plan-spec-row">
              <span>${t("Estimated Production")}:</span>
              <b>${window.FieldShift.format(yieldData.estimatedT_Ha, 2)} t/ha (~${window.FieldShift.format(yieldData.totalTons)} t)</b>
            </div>
            <div class="plan-spec-row">
              <span>${t("Management")}:</span>
              <b style="font-size:12px">${escape(plan.requiredResources.slice(0, 38))}…</b>
            </div>
            <div class="plan-spec-row">
              <span>${t("Primary Risks")}:</span>
              <b style="color:${plan.risks[0]?.includes("deficit") || plan.risks[0]?.includes("عجز") ? "#ff914d" : "inherit"}; font-size:12px">${escape(plan.risks[0] ? plan.risks[0].slice(0, 42) + "…" : "Low")}</b>
            </div>
          </div>

          <div class="plan-actions-grid">
            <button class="btn-action view-details-btn" type="button" data-plan-id="${escape(plan.id)}">🔍 ${t("View Details")}</button>
            <button class="btn-action compare-plans-btn" type="button">📊 ${t("Compare Plans")}</button>
            <button class="btn-action btn-select select-plan-btn" type="button" data-plan-id="${escape(plan.id)}">
              ${isSelected ? `✔ ${t("Selected Plan")}` : t("Select This Plan")}
            </button>
            <button class="btn-action btn-sim run-sim-btn" type="button" data-plan-id="${escape(plan.id)}">▷ ${t("Run Simulation")}</button>
          </div>
        </article>
      `;
    }).join("");

    // Bind card action buttons
    $("crop-plans").querySelectorAll(".view-details-btn").forEach(btn => {
      btn.addEventListener("click", () => openPlanModal(btn.dataset.planId));
    });
    $("crop-plans").querySelectorAll(".compare-plans-btn").forEach(btn => {
      btn.addEventListener("click", () => {
        $("plan-comparison-section").style.display = "block";
        renderComparisonTable();
        $("plan-comparison-section").scrollIntoView({ behavior: "smooth" });
      });
    });
    $("crop-plans").querySelectorAll(".select-plan-btn").forEach(btn => {
      btn.addEventListener("click", () => selectPlan(btn.dataset.planId));
    });
    $("crop-plans").querySelectorAll(".run-sim-btn").forEach(btn => {
      btn.addEventListener("click", () => {
        selectPlan(btn.dataset.planId);
        window.location.href = "/simulation";
      });
    });
  }

  function renderComparisonTable() {
    if (!activePlans.length) return;
    const escape = window.FieldShift.escapeHtml;
    const isAr = window.FieldShiftI18n?.language() === "ar";

    let html = `
      <table class="comparison-table">
        <thead>
          <tr>
            <th class="col-feature">${t("Features / Metrics")}</th>
            ${activePlans.map(p => `
              <th style="${p.id === selectedPlanId ? "background:rgba(30,167,255,.2); color:var(--sky)" : ""}">
                ${escape(p.name)}
                <div style="font-size:11px; font-weight:normal; margin-top:4px">${escape(p.badge)}</div>
              </th>
            `).join("")}
          </tr>
        </thead>
        <tbody>
          <tr>
            <th class="col-feature">${t("Agricultural Suitability")}</th>
            ${activePlans.map(p => `
              <td class="${p.id === selectedPlanId ? "col-plan-active" : ""}">
                <b style="font-size:16px; color:var(--sky)">${p.suitabilityScore}%</b>
                <div style="font-size:11px; color:var(--muted)">${escape(p.scoreBreakdown.explanation.slice(0, 48))}…</div>
              </td>
            `).join("")}
          </tr>
          <tr>
            <th class="col-feature">${t("Water Consumption")}</th>
            ${activePlans.map(p => `
              <td class="${p.id === selectedPlanId ? "col-plan-active" : ""}">
                <b>${window.FieldShift.format(p.waterRequirements.netIrrigationMm)} mm</b><br>
                <small class="mut">${window.FieldShift.format(p.waterRequirements.totalWaterM3)} m³ total</small>
              </td>
            `).join("")}
          </tr>
          <tr>
            <th class="col-feature">${t("Environmental Compatibility")}</th>
            ${activePlans.map(p => `
              <td class="${p.id === selectedPlanId ? "col-plan-active" : ""}">
                <b>${window.FieldShift.format(p.expectedConditions.meanTempC)} °C</b> (Opt: ${p.expectedConditions.optimalTempRange[0]}–${p.expectedConditions.optimalTempRange[1]} °C)<br>
                <small class="mut">Soil pH: ${window.FieldShift.format(p.expectedConditions.soilPh, 2)}</small>
              </td>
            `).join("")}
          </tr>
          <tr>
            <th class="col-feature">${t("Estimated Production")}</th>
            ${activePlans.map(p => `
              <td class="${p.id === selectedPlanId ? "col-plan-active" : ""}">
                <b style="color:#3ddc84">${window.FieldShift.format(p.estimatedYield.estimatedT_Ha, 2)} t/ha</b><br>
                <small class="mut">Total: ~${window.FieldShift.format(p.estimatedYield.totalTons)} t</small>
              </td>
            `).join("")}
          </tr>
          <tr>
            <th class="col-feature">${t("Primary Risks")}</th>
            ${activePlans.map(p => `
              <td class="${p.id === selectedPlanId ? "col-plan-active" : ""}" style="font-size:12.5px; line-height:1.4">
                ${escape(p.risks[0] || "Low risk")}
              </td>
            `).join("")}
          </tr>
          <tr>
            <th class="col-feature">${t("Resource Requirements")}</th>
            ${activePlans.map(p => `
              <td class="${p.id === selectedPlanId ? "col-plan-active" : ""}" style="font-size:12px; line-height:1.4">
                ${escape(p.requiredResources)}
              </td>
            `).join("")}
          </tr>
          <tr>
            <th class="col-feature">${t("Actions")}</th>
            ${activePlans.map(p => `
              <td class="${p.id === selectedPlanId ? "col-plan-active" : ""}">
                <button class="btn-action btn-select select-plan-table-btn" type="button" data-plan-id="${escape(p.id)}" style="width:100%; height:34px; font-size:12px">
                  ${p.id === selectedPlanId ? `✔ ${t("Selected Plan")}` : t("Select This Plan")}
                </button>
              </td>
            `).join("")}
          </tr>
        </tbody>
      </table>
    `;

    $("comparison-table-container").innerHTML = html;
    $("comparison-table-container").querySelectorAll(".select-plan-table-btn").forEach(b => {
      b.addEventListener("click", () => selectPlan(b.dataset.planId));
    });
  }

  function renderStats() {
    if (!data) return;
    const locale = window.FieldShiftI18n?.language() === "ar" ? "ar-EG" : "en-US";
    $("dataset-stats").innerHTML = [
      [data.monthly.length.toLocaleString(locale), "Crop-zone-month rows"],
      [data.seasons.length.toLocaleString(locale), "Complete seasonal profiles"],
      [data.yields.length.toLocaleString(locale), "National crop-year records"],
      [data.crops.length.toLocaleString(locale), "Crop types in monthly data"],
    ].map(([value, label]) => `<div class="dataset-stat"><b>${value}</b><span>${t(label)}</span></div>`).join("");
  }

  function populateSelect(select, values, selected, label = value => value) {
    if (!select) return;
    select.replaceChildren(...values.map(value => {
      const option = document.createElement("option");
      option.value = value;
      option.textContent = label(value);
      return option;
    }));
    if (values.includes(selected)) select.value = selected;
  }

  async function submitQuestion(question) {
    const text = question.trim();
    if (!text) return;
    appendMessage("user", text);
    $("chat-input").value = "";
    $("agent-mode").textContent = t("Retrieving evidence…");
    const pending = document.createElement("div");
    pending.className = "chat-message assistant";
    pending.textContent = t("Checking the local datasets and Agent API…");
    pending.setAttribute("aria-label", t("Assistant is thinking"));
    $("chat-log").appendChild(pending);
    $("chat-log").scrollTop = $("chat-log").scrollHeight;

    try {
      const activePlan = activePlans.find(p => p.id === selectedPlanId) || activePlans[0];
      const result = await window.FieldShift.askAgent(text, activePlan, data, conversationId);
      conversationId = result.conversation_id;
      sessionStorage.setItem(conversationKey, conversationId);

      const answer = result.reply || result.local?.reply;
      const evidence = result.local?.evidence || [];

      pending.remove();
      appendMessage("assistant", answer, evidence, result.assistant_mode === "llm"
        ? t("LLM response · retrieved dataset context supplied")
        : t("Local data answer · Agent API is in deterministic fallback mode"));
      $("agent-mode").textContent = t(result.assistant_mode === "llm" ? "LLM connected" : "Local data ready");
    } catch (error) {
      pending.remove();
      appendMessage("assistant", t("I couldn’t complete that request: {error}", { error: error.message }));
      $("agent-mode").textContent = t("Service unavailable");
    }
  }

  async function initialize() {
    try {
      const [projectData, sampleResponse] = await Promise.all([
        window.FieldShift.loadProjectData(),
        fetch("/sample/farm_summary.json"),
      ]);
      if (!sampleResponse.ok) throw new Error("Illustrative farm sample could not be loaded.");
      const sample = await sampleResponse.json();
      const savedPlan = window.FieldShift.getPlan();
      window.fieldShiftSummary = savedPlan?.farmSummary || sample;
      data = projectData;

      const dataZone = window.FieldShift.nearestZone(data, sample.location.latitude, sample.location.longitude);
      populateSelect($("farm-crop"), data.crops, savedPlan?.crop || sample.current_crop, value => window.FieldShift.displayCrop(value));
      populateSelect($("farm-zone"), data.zones.map(zone => zone.name), savedPlan?.zone || dataZone, value => window.FieldShiftI18n?.zone(value) || value);

      $("farm-area").value = savedPlan?.areaHectares || sample.farm_area_hectares || 10;
      $("farm-soil").value = savedPlan?.soil || sample.soil?.type || "clay";
      $("farm-season").value = savedPlan?.season || "winter";
      if (savedPlan?.scenario?.availableWaterMm !== undefined) {
        $("farm-water").value = savedPlan.scenario.availableWaterMm;
      }
      if (savedPlan?.scenario?.temperatureChange !== undefined) {
        $("farm-temp").value = String(savedPlan.scenario.temperatureChange);
      }
      if (savedPlan?.scenario?.rainfallChange !== undefined) {
        $("farm-rain").value = String(savedPlan.scenario.rainfallChange);
      }

      renderStats();
      generateAndSavePlans();
      renderPlansUI();
      restoreHistory();
      $("agent-mode").textContent = t("Local data ready");
    } catch (error) {
      $("plan-note").textContent = t("Project data unavailable: {error}", { error: error.message });
      $("crop-plans").innerHTML = `<p class="loading-note">${t("Could not load crop profiles. Confirm the application is being served by the local API.")}</p>`;
      $("agent-mode").textContent = t("Dataset unavailable");
    }
  }

  // Event Listeners for inputs
  ["farm-area", "farm-crop", "farm-soil", "farm-zone", "farm-season", "farm-water", "farm-temp", "farm-rain"].forEach(id => {
    $(id)?.addEventListener("change", () => {
      if (!data) return;
      generateAndSavePlans();
      renderPlansUI();
      renderComparisonTable();
    });
  });

  $("refresh-plans")?.addEventListener("click", () => {
    if (!data) return;
    generateAndSavePlans();
    renderPlansUI();
    renderComparisonTable();
  });

  $("modal-plan-close")?.addEventListener("click", () => {
    $("plan-detail-modal").style.display = "none";
  });

  $("plan-detail-modal")?.addEventListener("click", event => {
    if (event.target === $("plan-detail-modal")) {
      $("plan-detail-modal").style.display = "none";
    }
  });

  window.addEventListener("fieldshift:language-changed", () => {
    if (!data) return;
    populateSelect($("farm-crop"), data.crops, $("farm-crop").value, value => window.FieldShift.displayCrop(value));
    populateSelect($("farm-zone"), data.zones.map(zone => zone.name), $("farm-zone").value, value => window.FieldShiftI18n?.zone(value) || value);
    renderStats();
    generateAndSavePlans();
    renderPlansUI();
    renderComparisonTable();
    $("agent-mode").textContent = t("Local data ready");
  });

  $("chat-form")?.addEventListener("submit", event => {
    event.preventDefault();
    submitQuestion($("chat-input").value);
  });

  document.querySelectorAll(".chat-suggestions button").forEach(button => {
    button.addEventListener("click", () => submitQuestion(button.dataset.question));
  });

  initialize();
})();
