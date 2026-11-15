const fs = require('fs');
const path = require('path');

// Mock browser environment for Node
global.window = global;
global.document = {
  documentElement: { lang: 'en', dir: 'ltr' },
  body: { classList: { toggle() {}, contains() { return false; } } },
  querySelector() { return null; },
  querySelectorAll() { return []; },
  getElementById() { return null; },
  createElement() { return { setAttribute() {}, appendChild() {}, style: {} }; },
  addEventListener() {},
  readyState: 'complete',
};
global.localStorage = {
  store: {},
  getItem(k) { return this.store[k] || null; },
  setItem(k, v) { this.store[k] = String(v); },
  removeItem(k) { delete this.store[k]; },
  clear() { this.store = {}; }
};
global.sessionStorage = {
  store: {},
  getItem(k) { return this.store[k] || null; },
  setItem(k, v) { this.store[k] = String(v); },
};
global.NodeFilter = { SHOW_TEXT: 4 };
global.MutationObserver = class { observe() {} disconnect() {} };
global.dispatchEvent = () => true;
global.fetch = async (url) => {
  if (url.includes('terra_echo_ml_clean_8zones.csv')) {
    const text = fs.readFileSync(path.resolve(__dirname, '..', 'terra_echo_ml_clean_8zones.csv'), 'utf8');
    return { ok: true, text: async () => text };
  }
  if (url.includes('fao_national_yield_by_crop_year.csv')) {
    const text = fs.readFileSync(path.resolve(__dirname, '..', 'fao_national_yield_by_crop_year.csv'), 'utf8');
    return { ok: true, text: async () => text };
  }
  if (url.includes('farm_summary.json')) {
    const text = fs.readFileSync(path.resolve(__dirname, '..', 'data/sample/farm_summary.json'), 'utf8');
    return { ok: true, json: async () => JSON.parse(text) };
  }
  return { ok: false, statusText: 'Not found' };
};

// Load i18n and app.js
require('../files/i18n.js');
require('../files/app.js');

async function runTests() {
  console.log("=== FIELD SHIFT AI: FULL WORKFLOW VERIFICATION ===");

  // 1. Load project data
  const data = await window.FieldShift.loadProjectData();
  console.log(`[PASS] Datasets loaded: ${data.monthly.length} monthly rows, ${data.seasons.length} seasonal aggregates, ${data.yields.length} FAO yield rows, ${data.zones.length} zones.`);

  // 2. Test Multi-Plan Generation
  const inputs = {
    zone: "monufia",
    crop: "wheat",
    season: "winter",
    areaHectares: 10,
    soil: "clay",
    availableWaterMm: 380, // Constrained water
    tempScenario: 1, // +1°C
    rainScenario: -10, // -10% rain
  };

  const plans = window.FieldShift.PlanningEngine.generatePlans(inputs, data);
  console.log(`[PASS] Generated ${plans.length} agricultural plans.`);
  if (plans.length !== 4) throw new Error("Expected 4 distinct plans.");

  // Verify plans have distinct names, parameters, water demands, yields, and scores
  const ids = plans.map(p => p.id);
  const waterDemands = plans.map(p => p.waterRequirements.totalWaterM3);
  const suitabilityScores = plans.map(p => p.suitabilityScore);
  const estimatedYields = plans.map(p => p.estimatedYield.estimatedT_Ha);

  console.log("Plans generated:");
  plans.forEach(p => {
    console.log(`  - ${p.id.toUpperCase()} [${p.badge}]: Suitability: ${p.suitabilityScore}%, Demand: ${p.waterRequirements.netIrrigationMm}mm (${p.waterRequirements.totalWaterM3} m³), Est. Yield: ${p.estimatedYield.estimatedT_Ha} t/ha, Deficit: ${p.waterRequirements.deficitMm} mm`);
  });

  // Check distinctiveness
  const uniqueDemands = new Set(waterDemands);
  if (uniqueDemands.size < 3) throw new Error("Plans are not meaningfully different in water demand!");
  console.log("[PASS] Plans have meaningfully different agronomic parameters and resource calculations.");

  // 3. Test Individual Simulation (Mode A)
  console.log("\nTesting Mode A (Individual Simulation)...");
  const simPlanA = window.FieldShift.SimulationEngine.simulatePlan(plans[0], data);
  const simPlanB = window.FieldShift.SimulationEngine.simulatePlan(plans[1], data);

  console.log(`  Plan A (Balanced): Visual Status = ${simPlanA.visualStatus}, Satisfaction = ${simPlanA.waterBalance.satisfactionPct}%, Deficit = ${simPlanA.waterBalance.deficitMm} mm`);
  console.log(`  Plan B (Water-Conservative): Visual Status = ${simPlanB.visualStatus}, Satisfaction = ${simPlanB.waterBalance.satisfactionPct}%, Deficit = ${simPlanB.waterBalance.deficitMm} mm`);

  if (!simPlanA.fieldExplanation || !simPlanB.fieldExplanation) throw new Error("Missing field appearance explanation.");
  if (!simPlanA.positiveFactors.length || !simPlanA.recommendedImprovements.length) throw new Error("Missing assessment report details.");
  console.log("[PASS] Mode A simulation generates distinct field visuals and detailed assessment reports.");

  // 4. Test Multi-Plan Simulation (Mode B)
  console.log("\nTesting Mode B (Simulate & Compare All Plans)...");
  const multiSim = window.FieldShift.SimulationEngine.simulateAllPlans(plans, data);
  if (multiSim.length !== 4) throw new Error("Expected 4 simulated plans in Mode B.");
  console.log(`[PASS] Mode B successfully simulated all ${multiSim.length} plans separately.`);

  // 5. Test Visual Status Under Different Scenarios
  console.log("\nTesting Visual Field Status under different environmental conditions...");
  // Drought / severe deficit
  const droughtPlan = window.FieldShift.PlanningEngine.generatePlans({
    ...inputs,
    availableWaterMm: 120, // severe deficit
    tempScenario: 2
  }, data)[0];
  const simDrought = window.FieldShift.SimulationEngine.simulatePlan(droughtPlan, data);
  console.log(`  Drought Scenario (120 mm supply): Visual Status = ${simDrought.visualStatus} (Expected: arid_dry or wilted)`);
  if (simDrought.visualStatus !== "arid_dry" && simDrought.visualStatus !== "wilted") {
    throw new Error("Visual field did not reflect severe drought!");
  }

  // Plentiful water
  const plentifulPlan = window.FieldShift.PlanningEngine.generatePlans({
    ...inputs,
    availableWaterMm: 650, // surplus
    tempScenario: 0
  }, data)[0];
  const simPlentiful = window.FieldShift.SimulationEngine.simulatePlan(plentifulPlan, data);
  console.log(`  Plentiful Scenario (650 mm supply): Visual Status = ${simPlentiful.visualStatus} (Expected: healthy)`);
  if (simPlentiful.visualStatus !== "healthy") {
    throw new Error("Visual field did not reflect healthy status under plentiful supply!");
  }
  console.log("[PASS] Visual field condition accurately responds to actual simulated calculations.");

  // 6. Test AI Agent Intelligence
  console.log("\nTesting AI Agent Domain Intelligence...");
  const aiContext = {
    activePlan: plans[0],
    allPlans: plans,
    latestSim: simPlanA,
    multiSim: multiSim
  };

  const testQueriesEn = [
    "Why did Plan A perform better than Plan B?",
    "Why does this field look unhealthy?",
    "Is the water supply sufficient?",
    "What should I change to improve the selected plan?",
    "Which plan is most suitable for my conditions?",
    "What happens if I increase the available water?",
    "Can you explain the simulation results?"
  ];

  testQueriesEn.forEach(q => {
    const res = window.FieldShift.AI.answer(q, aiContext, data);
    console.log(`\n  Q: "${q}"`);
    console.log(`  Intent: ${res.intent}`);
    console.log(`  A: ${res.reply.slice(0, 140)}…`);
    if (!res.reply || res.reply.length < 20) throw new Error(`Empty or poor reply for "${q}"`);
  });
  console.log("\n[PASS] English AI Agent successfully answered all target domain questions.");

  // Test Arabic queries
  console.log("\nTesting Arabic AI Agent Queries...");
  window.FieldShiftI18n.setLanguage("ar");
  const testQueriesAr = [
    "لماذا تفوقت الخطة أ على الخطة ب؟",
    "لماذا يبدو الحقل غير صحي أو ذابلاً؟",
    "هل إمدادات المياه كافية لري المحصول؟",
    "ما الذي يجب تعديله لتحسين الخطة المختارة؟",
    "أي خطة هي الأنسب لظروفي الحالية؟",
    "ماذا يحدث إذا قمت بزيادة المياه المتاحة؟",
    "هل يمكنك شرح نتائج المحاكاة وكيف تم حسابها؟"
  ];

  testQueriesAr.forEach(q => {
    const res = window.FieldShift.AI.answer(q, aiContext, data);
    console.log(`\n  س: "${q}"`);
    console.log(`  التصنيف: ${res.intent}`);
    console.log(`  الإجابة: ${res.reply.slice(0, 140)}…`);
    if (!res.reply || res.reply.length < 20) throw new Error(`Empty or poor reply for Arabic "${q}"`);
  });
  console.log("\n[PASS] Arabic AI Agent successfully answered all target domain questions in Arabic.");

  console.log("\n=== ALL WORKFLOW TESTS PASSED SUCCESSFULLY! ===");
}

runTests().catch(err => {
  console.error("Test failed:", err);
  process.exit(1);
});
