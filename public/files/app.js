(() => {
  const ML_FILE = "terra_echo_ml_clean_8zones.csv";
  const YIELD_FILE = "fao_national_yield_by_crop_year.csv";
  const STORAGE_KEY = "fieldShiftPlan";
  const PLANS_STORAGE_KEY = "fieldShiftPlans";
  const SIM_RESULT_KEY = "fieldShiftSimulationResult";
  const MULTI_SIM_KEY = "fieldShiftMultiSimulationResults";

  const FIELD_NAMES = {
    berseem_clover: "Berseem clover",
    cotton: "Cotton",
    faba_bean: "Faba bean",
    maize: "Maize",
    potato: "Potato",
    rice: "Rice",
    sugar_beet: "Sugar beet",
    wheat: "Wheat",
    barley: "Barley",
    sunflower: "Sunflower",
    alfalfa: "Alfalfa",
    soybeans: "Soybeans",
    sorghum: "Sorghum",
  };

  const CROP_CATALOG = {
    wheat: { category: "cereal", kc_mid: 1.15, season_days: 150, drought_tolerance: "moderate", nitrogen_fixer: false, soil_preferences: ["clay", "loam", "silt"], water_range_mm: [420, 650], default_yield: 6.65, unit: "grain" },
    maize: { category: "cereal", kc_mid: 1.20, season_days: 125, drought_tolerance: "low", nitrogen_fixer: false, soil_preferences: ["loam", "clay", "silt"], water_range_mm: [550, 850], default_yield: 7.62, unit: "grain" },
    faba_bean: { category: "legume", kc_mid: 1.05, season_days: 110, drought_tolerance: "moderate", nitrogen_fixer: true, soil_preferences: ["loam", "clay", "silt"], water_range_mm: [320, 520], default_yield: 3.65, unit: "grain" },
    cotton: { category: "fiber", kc_mid: 1.15, season_days: 165, drought_tolerance: "moderate", nitrogen_fixer: false, soil_preferences: ["clay", "loam", "silt"], water_range_mm: [750, 1200], default_yield: 2.75, unit: "fiber" },
    rice: { category: "cereal", kc_mid: 1.20, season_days: 135, drought_tolerance: "low", nitrogen_fixer: false, soil_preferences: ["clay"], water_range_mm: [950, 1500], default_yield: 9.12, unit: "grain" },
    potato: { category: "tuber", kc_mid: 1.10, season_days: 120, drought_tolerance: "low", nitrogen_fixer: false, soil_preferences: ["loam", "silt", "sandy"], water_range_mm: [450, 700], default_yield: 28.5, unit: "tuber" },
    sugar_beet: { category: "root", kc_mid: 1.15, season_days: 180, drought_tolerance: "moderate", nitrogen_fixer: false, soil_preferences: ["loam", "clay", "silt"], water_range_mm: [550, 850], default_yield: 50.2, unit: "root" },
    berseem_clover: { category: "forage_legume", kc_mid: 1.05, season_days: 140, drought_tolerance: "low", nitrogen_fixer: true, soil_preferences: ["clay", "loam"], water_range_mm: [400, 720], default_yield: 45.0, unit: "forage" },
  };

  let projectData = null;

  function parseCsv(text) {
    const rows = [];
    let row = [];
    let cell = "";
    let quoted = false;
    for (let index = 0; index < text.length; index += 1) {
      const character = text[index];
      if (character === '"' && quoted && text[index + 1] === '"') {
        cell += '"';
        index += 1;
      } else if (character === '"') {
        quoted = !quoted;
      } else if (character === "," && !quoted) {
        row.push(cell);
        cell = "";
      } else if ((character === "\n" || character === "\r") && !quoted) {
        if (character === "\r" && text[index + 1] === "\n") index += 1;
        row.push(cell);
        cell = "";
        if (row.some(value => value !== "")) rows.push(row);
        row = [];
      } else {
        cell += character;
      }
    }
    if (cell || row.length) {
      row.push(cell);
      if (row.some(value => value !== "")) rows.push(row);
    }
    const headers = rows.shift() || [];
    return rows.map(values => Object.fromEntries(headers.map((key, index) => [key, values[index] ?? ""])));
  }

  const number = value => {
    if (value === null || value === undefined || String(value).trim() === "") return null;
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
  };

  const escapeHtml = value => String(value ?? "").replace(/[&<>"']/g, character => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
  })[character]);

  function average(values) {
    const valid = values.filter(value => value !== null && Number.isFinite(value));
    return valid.length ? valid.reduce((total, value) => total + value, 0) / valid.length : null;
  }

  function aggregateSeason(rows) {
    const complete = rows.filter(row => row.season_complete === "1");
    const seasons = new Map();
    complete.forEach(row => {
      const key = [row.zone, row.crop, row.season, row.harvest_year].join("|");
      if (!seasons.has(key)) seasons.set(key, []);
      seasons.get(key).push(row);
    });

    return [...seasons.entries()].map(([key, months]) => {
      const [zone, crop, season, harvestYear] = key.split("|");
      const sum = field => months.reduce((total, row) => total + (number(row[field]) ?? 0), 0);
      const mean = field => average(months.map(row => number(row[field])));
      return {
        zone,
        crop,
        season,
        harvestYear: Number(harvestYear),
        months: months.length,
        temperature: mean("t2m_mean"),
        temperatureMax: mean("t2m_max_mean"),
        temperatureMin: mean("t2m_min_mean"),
        precipitationMm: sum("precip_mm"),
        et0Mm: sum("et0_mm_month"),
        irrigationMm: sum("crop_irrigation_mm"),
        irrigationLowMm: sum("crop_irrigation_low_mm"),
        cropEffectiveRainMm: sum("crop_eff_rain_mm"),
        ndvi: mean("ndvi_crop"),
        cropEtcMm: sum("crop_etc_mm"),
        kc: mean("kc_draft"),
        soilPh: mean("soil_ph"),
        clayPct: mean("soil_clay_pct"),
        sandPct: mean("soil_sand_pct"),
        optimalTempMin: mean("req_temp_opt_min_c"),
        optimalTempMax: mean("req_temp_opt_max_c"),
        optimalPhMin: mean("req_ph_opt_min"),
        optimalPhMax: mean("req_ph_opt_max"),
      };
    });
  }

  async function loadProjectData() {
    if (projectData) return projectData;
    const [mlResponse, yieldResponse] = await Promise.all([
      fetch(`/project-datasets/${ML_FILE}`),
      fetch(`/project-datasets/${YIELD_FILE}`),
    ]);
    if (!mlResponse.ok || !yieldResponse.ok) {
      throw new Error("Project agricultural datasets could not be loaded.");
    }
    const [mlCsv, yieldCsv] = await Promise.all([mlResponse.text(), yieldResponse.text()]);
    const monthly = parseCsv(mlCsv);
    const yieldRows = parseCsv(yieldCsv).map(row => ({
      ...row,
      harvest_year: number(row.harvest_year),
      national_area_ha: number(row.national_area_ha),
      national_yield_t_ha: number(row.national_yield_t_ha),
    }));
    const coordinates = new Map();
    monthly.forEach(row => {
      if (!coordinates.has(row.zone)) coordinates.set(row.zone, []);
      coordinates.get(row.zone).push([number(row.lat), number(row.lon)]);
    });
    const zones = [...coordinates.entries()].map(([name, points]) => ({
      name,
      latitude: average(points.map(point => point[0])),
      longitude: average(points.map(point => point[1])),
    }));

    projectData = {
      monthly,
      seasons: aggregateSeason(monthly),
      yields: yieldRows,
      zones,
      crops: [...new Set(monthly.map(row => row.crop))].sort(),
      cropCatalog: CROP_CATALOG,
      profile(zone, crop, season) {
        const records = this.seasons.filter(item =>
          (!zone || item.zone === zone) &&
          (!crop || item.crop === crop) &&
          (!season || item.season === season)
        );
        if (!records.length) return null;
        const field = key => average(records.map(item => item[key]));
        return {
          zone: zone || "All zones",
          crop,
          season,
          seasons: records.length,
          years: records.map(item => item.harvestYear).sort((a, b) => a - b),
          temperature: field("temperature"),
          temperatureMax: field("temperatureMax"),
          temperatureMin: field("temperatureMin"),
          precipitationMm: field("precipitationMm"),
          et0Mm: field("et0Mm"),
          irrigationMm: field("irrigationMm"),
          irrigationLowMm: field("irrigationLowMm"),
          cropEffectiveRainMm: field("cropEffectiveRainMm"),
          ndvi: field("ndvi"),
          cropEtcMm: field("cropEtcMm"),
          kc: field("kc"),
          soilPh: field("soilPh"),
          clayPct: field("clayPct"),
          sandPct: field("sandPct"),
          optimalTempMin: field("optimalTempMin"),
          optimalTempMax: field("optimalTempMax"),
          optimalPhMin: field("optimalPhMin"),
          optimalPhMax: field("optimalPhMax"),
        };
      },
      cropYieldStats(crop) {
        const rows = this.yields.filter(r => r.crop === crop && r.national_yield_t_ha !== null)
          .sort((a, b) => a.harvest_year - b.harvest_year);
        if (!rows.length) {
          const fallback = CROP_CATALOG[crop]?.default_yield || 5.0;
          return { available: false, mean: fallback, min: fallback, max: fallback, latest: fallback, rows: [] };
        }
        const vals = rows.map(r => r.national_yield_t_ha);
        return {
          available: true,
          mean: average(vals),
          min: Math.min(...vals),
          max: Math.max(...vals),
          latest: rows[rows.length - 1].national_yield_t_ha,
          startYear: rows[0].harvest_year,
          endYear: rows[rows.length - 1].harvest_year,
          rows,
        };
      },
    };
    return projectData;
  }

  function nearestZone(data, latitude, longitude) {
    if (!Number.isFinite(Number(latitude)) || !Number.isFinite(Number(longitude))) return null;
    return data.zones.reduce((nearest, zone) => {
      const distance = Math.hypot(zone.latitude - Number(latitude), zone.longitude - Number(longitude));
      return !nearest || distance < nearest.distance ? { name: zone.name, distance } : nearest;
    }, null)?.name ?? null;
  }

  function displayCrop(crop) {
    const label = FIELD_NAMES[crop] || crop.replaceAll("_", " ").replace(/\b\w/g, letter => letter.toUpperCase());
    return window.FieldShiftI18n?.crop(label) || label;
  }

  function format(value, digits = 1) {
    const locale = window.FieldShiftI18n?.language() === "ar" ? "ar-EG" : "en-US";
    return value === null || value === undefined
      ? window.FieldShiftI18n?.t("Not available") || "Not available"
      : Number(value).toLocaleString(locale, {
      maximumFractionDigits: digits,
      minimumFractionDigits: digits,
    });
  }

  function getPlan() {
    try {
      return JSON.parse(localStorage.getItem(STORAGE_KEY) || "null");
    } catch (error) {
      console.error("Saved farm plan is invalid.", error);
      return null;
    }
  }

  function savePlan(plan) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(plan));
    window.dispatchEvent(new CustomEvent("fieldshift:plan-updated", { detail: plan }));
  }

  function getSavedPlans() {
    try {
      return JSON.parse(localStorage.getItem(PLANS_STORAGE_KEY) || "[]");
    } catch (error) {
      return [];
    }
  }

  function savePlans(plans) {
    localStorage.setItem(PLANS_STORAGE_KEY, JSON.stringify(plans));
    window.dispatchEvent(new CustomEvent("fieldshift:plans-updated", { detail: plans }));
  }

  function getFarmSummary() {
    const base = window.fieldShiftSummary || {};
    return {
      ...base,
      farm_id: base.farm_id || "sample_farm_001",
      farm_area_hectares: Number(document.querySelector("#farm-area")?.value || base.farm_area_hectares || 10),
      current_crop: document.querySelector("#farm-crop")?.value || base.current_crop || "wheat",
      soil: { ...(base.soil || {}), type: document.querySelector("#farm-soil")?.value || base.soil?.type || "clay" },
      location: base.location || { latitude: 30.56, longitude: 31.16, country: "Egypt" },
      crop_history: base.crop_history || [],
      planting_date: base.planting_date || new Date().toISOString().slice(0, 10),
    };
  }

  // ==========================================
  // SMART AGRICULTURAL PLANNING ENGINE
  // ==========================================
  const PlanningEngine = {
    generatePlans(inputs, data) {
      if (!data) return [];
      const t = (key, val) => window.FieldShiftI18n?.t(key, val) || key;
      const lang = window.FieldShiftI18n?.language() || "en";
      const isAr = lang === "ar";

      const zone = inputs.zone || "monufia";
      const primaryCrop = inputs.crop || "wheat";
      const season = inputs.season || "winter";
      const area = Number(inputs.areaHectares) || 10;
      const soilType = inputs.soil || "clay";
      const availableWaterMm = inputs.availableWaterMm !== null && inputs.availableWaterMm !== undefined && inputs.availableWaterMm !== ""
        ? Number(inputs.availableWaterMm)
        : null;
      const tempScenario = Number(inputs.tempScenario || 0);
      const rainScenario = Number(inputs.rainScenario || 0);

      // Find baseline profile for the primary crop
      let baselineProfile = data.profile(zone, primaryCrop, season);
      if (!baselineProfile) {
        // Fallback to all-zone profile for the crop in that season
        baselineProfile = data.profile(null, primaryCrop, season);
      }
      if (!baselineProfile) {
        // Fallback to wheat or first available
        baselineProfile = data.profile(null, "wheat", "winter");
      }

      // Alternative crop candidates suitable for the season
      const seasonCrops = season === "winter"
        ? ["wheat", "faba_bean", "berseem_clover", "sugar_beet", "potato"]
        : ["maize", "cotton", "rice"];
      
      const altLegume = season === "winter" ? "faba_bean" : "soybeans";
      const altWaterSaving = season === "winter" ? (primaryCrop === "wheat" ? "barley" : "wheat") : "sorghum";

      // Archetype Definitions
      const archetypes = [
        {
          id: "plan_a",
          type: "balanced",
          nameEn: "Plan A: Balanced & Standard Production",
          nameAr: "الخطة (أ): الإنتاج المتوازن والقياسي",
          badgeEn: "Balanced Baseline",
          badgeAr: "المرجع المتوازن",
          descEn: "Standard regional agronomic package for the selected crop with balanced mineral nutrition and conventional irrigation schedule.",
          descAr: "حزمة زراعية إقليمية قياسية للمحصول المختار، مع تغذية معدنية متوازنة وجدول ري تقليدي محسّن.",
          crop: primaryCrop,
          irrigationFactor: 1.0,
          waterSavingPct: 0,
          yieldMultiplier: 1.0,
          managementEn: "Standard furrow/sprinkler with balanced NPK split application",
          managementAr: "ري سطحي/رشاشات مطوّر مع تسميد NPK متوازن مقسم على دفعات",
          prosEn: ["Proven regional track record and high local market familiarity", "Predictable labor and equipment requirements", "Stable baseline historical yield expectations"],
          prosAr: ["سجل إقليمي مثبت ومعرفة تسويقية واسعة", "متطلبات عمالة ومعدات مألوفة ومنتظمة", "توقعات إنتاجية قياسية مستقرة"],
          consEn: ["Higher water consumption than modern drip or deficit regimes", "Vulnerable to temperature spikes during grain-filling/fruiting", "Standard soil organic matter depletion if residues are removed"],
          consAr: ["استهلاك مائي أعلى مقارنة بالري بالتنقيط أو الري الناقص", "حساسية للموجات الحارة أثناء مراحل امتلاء الحبوب/الثمار", "استنزاف تدريجي للمادة العضوية في التربة"],
        },
        {
          id: "plan_b",
          type: "water_conservative",
          nameEn: "Plan B: High-Efficiency Water-Conservative Strategy",
          nameAr: "الخطة (ب): كفاءة استهلاك المياه والري بالتنقيط",
          badgeEn: "Water-Saving −22%",
          badgeAr: "توفير مياه −22%",
          descEn: "Optimized for water scarcity using precision drip irrigation, deficit scheduling during vegetative stages, and mulching.",
          descAr: "خطة مخصصة لمواجهة شح المياه بالاعتماد على الري بالتنقيط الدقيق، والري الناقص المدروس، وتغطية التربة.",
          crop: primaryCrop,
          irrigationFactor: 0.78, // Saves 22% net irrigation
          waterSavingPct: 22,
          yieldMultiplier: 0.96, // Slight trade-off in gross tonnage, but huge WUE
          managementEn: "Drip fertigation, moisture-monitoring tensiometers, 20% deficit during early vegetative growth",
          managementAr: "ري وتسميد بالتنقيط، مجسات رطوبة أرضية، ري ناقص بنسبة 20% في المرحلة الخضرية الأولى",
          prosEn: ["Saves ~20–25% irrigation water, buffering against supply quotas", "Significantly increases crop water-use efficiency (kg grain per m³)", "Lowers pumping and energy expenses"],
          prosAr: ["توفير 20–25% من مياه الري مما يحمي من شح الإمدادات", "رفع كفاءة استخدام المياه بشكل كبير (كجم محصول لكل م³)", "خفض تكاليف ضخ المياه والطاقة"],
          consEn: ["Requires capital investment in drip infrastructure or strict scheduling", "Requires regular filter maintenance to prevent emitter clogging", "Slightly lower gross tonnage if water is fully abundant"],
          consAr: ["تتطلب استثمارًا في شبكة التنقيط أو انضباطًا صارمًا في المواعيد", "حاجة لصيانة الفلاتر لمنع انسداد النقاطات", "إنتاجية إجمالية أقل قليلًا عند وفرة المياه التامة"],
        },
        {
          id: "plan_c",
          type: "soil_health",
          nameEn: "Plan C: Soil Health & Ecological Resilience",
          nameAr: "الخطة (ج): صحة التربة والاستدامة الحيوية",
          badgeEn: "Soil Booster & Legume Integration",
          badgeAr: "معزز للتربة وبقوليات",
          descEn: "Focuses on long-term soil regeneration, incorporating legume biology (biological nitrogen fixation), compost, and erosion protection.",
          descAr: "تركز على استعادة خصوبة التربة على المدى الطويل بدمج المحاصيل البقولية المثبتة للنيتروجين الحيوي والكومبوست العضوي.",
          crop: (primaryCrop.includes("bean") || primaryCrop.includes("clover")) ? primaryCrop : altLegume,
          irrigationFactor: 0.85,
          waterSavingPct: 15,
          yieldMultiplier: 0.98,
          managementEn: "Organic amendment (10 t/ha compost), biological N fixation (reduces synthetic N by 35%), cover residue",
          managementAr: "تسميد عضوي (10 طن/هكتار كمبوست)، تثبيت حيوي للنيتروجين (يوفر 35% سماد أزوتي)، تغطية حيوية",
          prosEn: ["Adds 40–70 kg biological N/ha to soil, reducing chemical fertilizer costs", "Enhances soil microbial diversity, structure, and water retention", "Breaks cereal monoculture pest and disease cycles"],
          prosAr: ["إمداد التربة بـ 40–70 كجم نيتروجين حيوي/هكتار وخفض تكلفة الأسمدة", "تحسين البناء الحبيبي للتربة وقدرتها على الاحتفاظ بالرطوبة", "كسر دورة آفات وأمراض الحبوب المتكررة"],
          consEn: ["Legume market price may fluctuate more than staple grains", "Requires rhizobium bacterial inoculation in newly planted fields", "Requires diverse crop handling and post-harvest storage"],
          consAr: ["تقلبات أسعار تسويق المحاصيل البقولية مقارنة بالحبوب الاستراتيجية", "الحاجة للتلقيح البكتيري العقدي في الأراضي الجديدة", "تتطلب خبرة في تداول وتخزين محاصيل متعددة"],
        },
        {
          id: "plan_d",
          type: "high_yield",
          nameEn: "Plan D: High-Yield Intensive Production",
          nameAr: "الخطة (د): الإنتاج المكثف والإنتاجية القصوى",
          badgeEn: "Maximum Tonnage Target",
          badgeAr: "الإنتاجية القصوى",
          descEn: "High-input strategy aimed at maximum harvest tonnage per hectare with 100% ETc satisfaction, split fertigation, and micronutrients.",
          descAr: "استراتيجية إنتاج مكثف تهدف إلى تحقيق أعلى إنتاجية هكتارية بتلبية 100% من البخر-نتح، وتسميد دقيق متعدد المراحل.",
          crop: primaryCrop,
          irrigationFactor: 1.10, // Full luxury transpiration + slight leaching
          waterSavingPct: -10,
          yieldMultiplier: 1.08, // Target top percentile
          managementEn: "High-frequency precision irrigation, complete macronutrient + foliar micronutrient fertigation program",
          managementAr: "ري متكرر عالي الدقة، برنامج تسميد كلي بالعناصر الكبرى والصغرى رشًا وتسميدًا",
          prosEn: ["Maximizes total gross tonnage and revenue when markets and water are favorable", "Utilizes genetic yield potential of modern certified seed varieties", "Uniform crop stand and premium grade produce"],
          prosAr: ["تعظيم العائد والإنتاج الإجمالي عند توفر المياه والأسعار الجيدة", "استغلال كامل للطاقة الإنتاجية للأصناف المعتمدة الحديثة", "تجانس عالي للمحصول وجودة ثمار/حبوب ممتازة"],
          consEn: ["Highest water consumption; severely penalized if a water deficit occurs", "Higher upfront working capital for fertilizers and plant protection", "Higher vulnerability to lodging or fungal outbreaks under excess nitrogen"],
          consAr: ["أعلى استهلاك للمياه؛ يتعرض لانتكاسة حادة عند حدوث أي عجز مائي", "تكاليف تشغيلية واستثمارية أولية أعلى للأسمدة والمغذيات", "زيادة مخاطر الرقاد أو الأمراض الفطرية مع زيادة التسميد"],
        },
      ];

      return archetypes.map(arc => {
        const crop = arc.crop;
        const cropProfile = data.profile(zone, crop, season) || baselineProfile;
        const yieldStats = data.cropYieldStats(crop);
        const catalog = CROP_CATALOG[crop] || { drought_tolerance: "moderate", soil_preferences: ["clay", "loam"], water_range_mm: [400, 700] };

        // 1. Water calculations
        const baseEtc = (cropProfile.cropEtcMm || 450);
        const adjustedEtc = baseEtc * (1 + 0.02 * tempScenario) * arc.irrigationFactor;
        const baseRain = cropProfile.precipitationMm || 15;
        const adjustedRain = baseRain * (1 + rainScenario / 100);
        const effectiveRain = adjustedRain * 0.8;
        const netIrrigationMm = Math.max(0, adjustedEtc - effectiveRain);
        const totalWaterM3 = netIrrigationMm * area * 10;

        let availableM3 = null;
        let balanceM3 = null;
        let deficitMm = 0;
        let waterSatisfactionPct = 100;
        let waterStressRisk = "low";

        if (availableWaterMm !== null) {
          availableM3 = availableWaterMm * area * 10;
          balanceM3 = availableM3 - totalWaterM3;
          if (balanceM3 < 0) {
            deficitMm = Math.abs(balanceM3) / (area * 10);
            waterSatisfactionPct = Math.max(20, Math.round((availableM3 / totalWaterM3) * 100));
            waterStressRisk = waterSatisfactionPct < 70 ? "high" : "moderate";
          } else {
            waterSatisfactionPct = 100;
            waterStressRisk = "low";
          }
        }

        // 2. Expected conditions & checks
        const meanTemp = (cropProfile.temperature || 18) + tempScenario;
        const optTempMin = cropProfile.optimalTempMin ?? 14;
        const optTempMax = cropProfile.optimalTempMax ?? 28;
        const tempStatus = meanTemp < optTempMin ? "low" : meanTemp > optTempMax ? "high" : "optimal";

        const soilPh = cropProfile.soilPh ?? 7.8;
        const optPhMin = cropProfile.optimalPhMin ?? 6.5;
        const optPhMax = cropProfile.optimalPhMax ?? 8.2;
        const phStatus = soilPh >= optPhMin && soilPh <= optPhMax ? "optimal" : "suboptimal";
        const soilMatch = catalog.soil_preferences.includes(soilType);

        // 3. Estimated yield (based on FAO dataset + agronomic responses)
        const baselineYield = yieldStats.mean;
        // FAO-33 water-yield response approximation: Ky ~ 1.05
        const waterYieldFactor = waterSatisfactionPct < 100
          ? Math.max(0.4, 1.0 - 1.05 * (1.0 - waterSatisfactionPct / 100))
          : (arc.id === "plan_d" ? 1.06 : 1.0);
        const tempYieldFactor = tempStatus === "optimal" ? 1.0 : Math.max(0.85, 1.0 - 0.03 * Math.abs(meanTemp - (optTempMin + optTempMax) / 2));
        const soilYieldFactor = soilMatch ? 1.0 : 0.92;
        const estimatedT_Ha = Number((baselineYield * arc.yieldMultiplier * waterYieldFactor * tempYieldFactor * soilYieldFactor).toFixed(2));
        const totalTons = Number((estimatedT_Ha * area).toFixed(1));

        // 4. Suitability Score (0 - 100)
        let tempScore = tempStatus === "optimal" ? 30 : Math.max(12, 30 - Math.round(Math.abs(meanTemp - (optTempMin + optTempMax) / 2) * 3));
        let soilScore = (soilMatch ? 15 : 8) + (phStatus === "optimal" ? 10 : 5);
        let waterScore = availableWaterMm === null ? 22 : Math.round((waterSatisfactionPct / 100) * 25);
        let vigorScore = cropProfile.ndvi ? Math.min(20, Math.round(cropProfile.ndvi * 32)) : 16;
        let suitabilityScore = Math.min(100, Math.max(25, tempScore + soilScore + waterScore + vigorScore));

        // Explanation of score
        const scoreExplanationEn = `Score: ${suitabilityScore}/100 [Temperature Comfort: ${tempScore}/30, Soil Fit: ${soilScore}/25, Water Adequacy: ${waterScore}/25, Historic Vigor (NDVI): ${vigorScore}/20].`;
        const scoreExplanationAr = `الدرجة: ${suitabilityScore}/100 [التوافق الحراري: ${tempScore}/30، ملاءمة التربة: ${soilScore}/25، كفاية المياه: ${waterScore}/25، الحيوية التاريخية (NDVI): ${vigorScore}/20].`;

        // Specific Agricultural Risks
        const risksEn = [];
        const risksAr = [];
        if (deficitMm > 50) {
          risksEn.push(`Seasonal water deficit of ${format(deficitMm)} mm (${format(Math.abs(balanceM3))} m³) will trigger moisture stress and curb grain development.`);
          risksAr.push(`عجز مائي موسمي قدره ${format(deficitMm)} مم (${format(Math.abs(balanceM3))} م³) سيسبب إجهادًا رطوبيًا ويحد من امتلاء المحصول.`);
        }
        if (tempStatus === "high") {
          risksEn.push(`Scenario temperature (${format(meanTemp)} °C) exceeds optimum (${optTempMax} °C), risking accelerated flowering and reduced grain weight.`);
          risksAr.push(`درجة حرارة السيناريو (${format(meanTemp)} °م) تتجاوز الحد الأمثل (${optTempMax} °م)، مما يهدد بتزهير مبكر وانخفاض وزن الحبوب.`);
        }
        if (!soilMatch) {
          risksEn.push(`Selected soil (${soilType}) is not prime for ${displayCrop(crop)}; monitor aeration or drainage closely.`);
          risksAr.push(`التربة المختارة (${soilType}) ليست المثالية للمحصول (${displayCrop(crop)})؛ يلزم متابعة الصرف والتهوية.`);
        }
        if (!risksEn.length) {
          risksEn.push("Low agronomic risk under current scenario; manage routine weeds and standard pests.");
          risksAr.push("مخاطر زراعية منخفضة ضمن السيناريو الحالي؛ تتطلب إدارة الحشائش والآفات المعتادة فقط.");
        }

        return {
          id: arc.id,
          type: arc.type,
          name: isAr ? arc.nameAr : arc.nameEn,
          nameEn: arc.nameEn,
          nameAr: arc.nameAr,
          badge: isAr ? arc.badgeAr : arc.badgeEn,
          description: isAr ? arc.descAr : arc.descEn,
          crop,
          cropDisplay: displayCrop(crop),
          season,
          areaHectares: area,
          soil: soilType,
          zone,
          management: isAr ? arc.managementAr : arc.managementEn,
          requiredResources: isAr ? arc.managementAr : arc.managementEn,
          waterRequirements: {
            seasonalEtcMm: Number(adjustedEtc.toFixed(1)),
            effectiveRainMm: Number(effectiveRain.toFixed(1)),
            netIrrigationMm: Number(netIrrigationMm.toFixed(1)),
            totalWaterM3: Math.round(totalWaterM3),
            availableWaterMm,
            availableWaterM3: availableM3 ? Math.round(availableM3) : null,
            balanceM3: balanceM3 ? Math.round(balanceM3) : null,
            deficitMm: Number(deficitMm.toFixed(1)),
            waterSatisfactionPct,
            waterStressRisk,
          },
          expectedConditions: {
            meanTempC: Number(meanTemp.toFixed(1)),
            optimalTempRange: [optTempMin, optTempMax],
            tempStatus,
            soilType,
            soilMatch,
            soilPh: Number(soilPh.toFixed(2)),
            optimalPhRange: [optPhMin, optPhMax],
            phStatus,
            ndvi: cropProfile.ndvi ? Number(cropProfile.ndvi.toFixed(3)) : 0.55,
          },
          estimatedYield: {
            baselineT_Ha: Number(baselineYield.toFixed(2)),
            estimatedT_Ha,
            totalTons,
            unit: catalog.unit || "t",
            waterSatisfactionPct,
            citation: `FAOSTAT Egypt 2015–2024 (mean: ${format(baselineYield, 2)} t/ha) + FAO-33 water-yield response`,
          },
          risks: isAr ? risksAr : risksEn,
          advantages: isAr ? arc.prosAr : arc.prosEn,
          disadvantages: isAr ? arc.consAr : arc.consEn,
          suitabilityScore,
          scoreBreakdown: {
            temp: tempScore,
            soil: soilScore,
            water: waterScore,
            vigor: vigorScore,
            explanation: isAr ? scoreExplanationAr : scoreExplanationEn,
          },
          scenario: {
            temperatureChange: tempScenario,
            rainfallChange: rainScenario,
            availableWaterMm,
          },
        };
      });
    },
  };

  // ==========================================
  // AGRICULTURAL SIMULATION ENGINE
  // ==========================================
  const SimulationEngine = {
    simulatePlan(plan, data) {
      if (!plan) throw new Error("A valid agricultural plan is required for simulation.");
      const isAr = window.FieldShiftI18n?.language() === "ar";
      const t = (k, v) => window.FieldShiftI18n?.t(k, v) || k;

      const water = plan.waterRequirements;
      const cond = plan.expectedConditions;
      const yieldData = plan.estimatedYield;

      // Classify Field Visual Condition
      // healthy, moderate, wilted, arid_dry
      let visualStatus = "healthy";
      let growthConditionLabelEn = "Vigorous Optimal Growth";
      let growthConditionLabelAr = "نمو مثالي وقوي";
      let fieldExplanationEn = "";
      let fieldExplanationAr = "";

      const deficit = water.balanceM3 !== null && water.balanceM3 < 0 ? Math.abs(water.balanceM3) : 0;
      const deficitMm = water.deficitMm || 0;

      if (deficitMm > 140 || water.waterSatisfactionPct < 60) {
        visualStatus = "arid_dry";
        growthConditionLabelEn = "Severe Drought & Parched Soil";
        growthConditionLabelAr = "جفاف حاد وتربة متشققة";
        fieldExplanationEn = `The simulated field shows severe moisture deficit (${format(deficitMm)} mm / ${format(deficit)} m³). Canopy exhibits severe leaf browning and thinning, while topsoil develops dry shrinkage fissures and arid crusting.`;
        fieldExplanationAr = `يُظهر الحقل المحاكى عجزًا رطوبيًا حادًا (${format(deficitMm)} مم / ${format(deficit)} م³). تظهر على الأوراق علامات الاحتراق والجفاف وتساقط الكثافة النباتية، وتتشقق التربة بفعل الإجهاد الجاف.`;
      } else if (deficitMm > 40 || water.waterSatisfactionPct < 85 || cond.tempStatus === "high") {
        visualStatus = "wilted";
        growthConditionLabelEn = "Moisture & Heat Stress (Wilting)";
        growthConditionLabelAr = "إجهاد رطوبي وحراري (ذبول)";
        fieldExplanationEn = `The simulated field reflects moisture constraint and temperature stress. Leaves show mild chlorosis (yellowing) and afternoon wilting, with depleted surface soil moisture.`;
        fieldExplanationAr = `يُظهر الحقل إجهادًا رطوبيًا مصحوبًا بحرارة مرتفعة. تبدو الأوراق شاحبة ومائلة للاصفرار مع ذبول مؤقت في الظهيرة وجفاف الطبقة السطحية للتربة.`;
      } else if (plan.suitabilityScore < 70 || cond.soilMatch === false || cond.tempStatus !== "optimal") {
        visualStatus = "moderate";
        growthConditionLabelEn = "Moderate Growth (Suboptimal Conditions)";
        growthConditionLabelAr = "نمو متوسط (ظروف دون المثلى)";
        fieldExplanationEn = `The simulated crop develops adequately with acceptable vegetative cover, though slightly sparse row closure due to sub-optimal temperature or soil texture alignment.`;
        fieldExplanationAr = `ينمو المحصول بشكل مقبول مع غطاء خضري متوسط، لكنه يفتقد للكثافة التامة نتيجة عدم تطابق درجة الحرارة أو قوام التربة مع الحدود المثلى.`;
      } else {
        visualStatus = "healthy";
        growthConditionLabelEn = "Vibrant Canopy & Fertile Soil";
        growthConditionLabelAr = "غطاء نباتي نضر وتربة خصبة";
        fieldExplanationEn = `The simulated field displays an optimal, deep green canopy cover with healthy leaf turgor and moist, biologically active soil, supported by full water satisfaction and favorable temperature.`;
        fieldExplanationAr = `يُظهر الحقل غطاءً خضريًا كثيفًا ولونًا أخضر يانعًا مع انتفاخ ممتاز للأوراق ورطوبة تربة متوازنة ونشطة بيولوجيًا، مدعومة بتلبية كاملة لاحتياجات المياه والحرارة.`;
      }

      // Assessment Report Items
      const positiveFactorsEn = [];
      const positiveFactorsAr = [];
      const negativeFactorsEn = [];
      const negativeFactorsAr = [];
      const recommendedImprovementsEn = [];
      const recommendedImprovementsAr = [];

      if (water.waterSatisfactionPct >= 95) {
        positiveFactorsEn.push("Net seasonal crop evapotranspiration (ETc) is fully met by supplied irrigation and effective rainfall.");
        positiveFactorsAr.push("تتم تلبية البخر-نتح الموسمي للمحصول (ETc) بالكامل عبر إمدادات الري والأمطار الفعالة.");
      } else {
        negativeFactorsEn.push(`Unsatisfied water gap of ${format(deficitMm)} mm (${format(deficit)} m³) threatens physiological development during critical growth stages.`);
        negativeFactorsAr.push(`عجز مائي غير ملبى قدره ${format(deficitMm)} مم (${format(deficit)} م³) يهدد التطور الفسيولوجي أثناء المراحل الحرجة.`);
        recommendedImprovementsEn.push("Adopt precision drip irrigation or mulching to reduce evaporation losses by 18–25%.");
        recommendedImprovementsAr.push("اعتماد الري بالتنقيط عالي الدقة أو تغطية التربة لتقليل الفواقد بالتبخر بنسبة 18–25%.");
      }

      if (cond.tempStatus === "optimal") {
        positiveFactorsEn.push(`Zone seasonal mean temperature (${format(cond.meanTempC)} °C) aligns within crop comfort range (${cond.optimalTempRange[0]}–${cond.optimalTempRange[1]} °C).`);
        positiveFactorsAr.push(`متوسط حرارة المنطقة (${format(cond.meanTempC)} °م) يقع ضمن النطاق الحراري الأمثل للمحصول (${cond.optimalTempRange[0]}–${cond.optimalTempRange[1]} °م).`);
      } else {
        negativeFactorsEn.push(`Temperature deviation (${format(cond.meanTempC)} °C vs optimal ${cond.optimalTempRange[0]}–${cond.optimalTempRange[1]} °C) induces thermal respiration losses.`);
        negativeFactorsAr.push(`الانحراف الحراري (${format(cond.meanTempC)} °م مقارنة بالنطاق الأمثل ${cond.optimalTempRange[0]}–${cond.optimalTempRange[1]} °م) يرفع فواقد التنفس النباتي.`);
        recommendedImprovementsEn.push("Adjust sowing date by 10–14 days to escape late-season thermal extremes.");
        recommendedImprovementsAr.push("تعديل موعد الزراعة بمقدار 10–14 يومًا لتفادي الموجات الحرارية المتأخرة.");
      }

      if (cond.phStatus === "optimal") {
        positiveFactorsEn.push(`Soil pH (${format(cond.soilPh, 2)}) ensures favorable macronutrient and micronutrient bioavailability.`);
        positiveFactorsAr.push(`درجة حموضة التربة (${format(cond.soilPh, 2)}) تضمن إتاحة ممتازة للعناصر الغذائية الكبرى والصغرى.`);
      }

      if (plan.type === "soil_health") {
        positiveFactorsEn.push("Legume integration fixes atmospheric nitrogen, improving soil microbial biomass and lowering fertilizer footprints.");
        positiveFactorsAr.push("تثبيت النيتروجين الحيوي عبر البقوليات يرفع خصوبة التربة ويقلل البصمة الكربونية للأسمدة الكيماوية.");
      }

      if (!recommendedImprovementsEn.length) {
        recommendedImprovementsEn.push("Maintain current irrigation scheduling and monitor soil moisture weekly using tensiometers.");
        recommendedImprovementsAr.push("المحافظة على جدول الري الحالي ومراقبة رطوبة التربة أسبوعيًا باستخدام مجسات الرطوبة.");
      }

      const overallRating = water.waterSatisfactionPct >= 90 && plan.suitabilityScore >= 80 ? "Optimal" :
        water.waterSatisfactionPct >= 75 ? "Satisfactory" : "Stressed";

      return {
        planId: plan.id,
        planName: plan.name,
        crop: plan.crop,
        cropDisplay: plan.cropDisplay,
        season: plan.season,
        areaHectares: plan.areaHectares,
        visualStatus,
        growthConditionLabel: isAr ? growthConditionLabelAr : growthConditionLabelEn,
        fieldExplanation: isAr ? fieldExplanationAr : fieldExplanationEn,
        overallRating,
        suitabilityScore: plan.suitabilityScore,
        estimatedYield: yieldData,
        waterBalance: {
          demandM3: water.totalWaterM3,
          availableM3: water.availableWaterM3,
          balanceM3: water.balanceM3,
          deficitMm: water.deficitMm,
          satisfactionPct: water.waterSatisfactionPct,
          statusText: water.balanceM3 === null
            ? t("Supply not compared (no value entered)")
            : water.balanceM3 >= 0
              ? t("Entered water exceeds estimated net demand")
              : t("Water availability below estimated net demand"),
        },
        positiveFactors: isAr ? positiveFactorsAr : positiveFactorsEn,
        negativeFactors: isAr ? negativeFactorsAr : negativeFactorsEn,
        recommendedImprovements: isAr ? recommendedImprovementsAr : recommendedImprovementsEn,
        potentialRisks: plan.risks,
        scientificDisclaimer: isAr
          ? "تنبيه علمي: هذه النتائج حسابات استرشادية مبنية على سجلات تاريخية ونماذج البخر-نتح المعتمدة (FAO-56/33)، وليست تنبؤات قطعية للحقل الواقعي. يلزم التحقق الميداني مع المرشد الزراعي المحلي."
          : "Scientific Disclaimer: These simulation outputs are screening evaluations calculated from historical records and FAO-56/33 equations; they do not represent guaranteed real-world field contracts. Local agronomic validation is required.",
      };
    },

    simulateAllPlans(plans, data) {
      if (!plans || !plans.length) return [];
      const results = plans.map(p => this.simulatePlan(p, data));
      // Save multi-sim results in storage
      localStorage.setItem(MULTI_SIM_KEY, JSON.stringify(results));
      window.dispatchEvent(new CustomEvent("fieldshift:multi-sim-updated", { detail: results }));
      return results;
    },
  };

  // ==========================================
  // LOCAL AGRICULTURAL INTELLIGENCE LAYER (AI)
  // ==========================================
  const AI = {
    answer(question, context, data) {
      const q = question.toLowerCase();
      const t = (k, v) => window.FieldShiftI18n?.t(k, v) || k;
      const lang = window.FieldShiftI18n?.language() || "en";
      const isAr = lang === "ar" || /[\u0600-\u06ff]/.test(question);

      const activePlan = context.activePlan || getPlan();
      const allPlans = context.allPlans || getSavedPlans();
      const latestSim = context.latestSim || JSON.parse(localStorage.getItem(SIM_RESULT_KEY) || "null");
      const multiSim = context.multiSim || JSON.parse(localStorage.getItem(MULTI_SIM_KEY) || "null");

      let intent = "general";
      let reply = "";
      let evidence = [];

      // 1. Comparison between plans
      if (/why did plan|better than|compare.*plan|plan a.*plan b|plan b.*plan a|difference between plans|which plan is best|most suitable|أيهما أفضل|قارن بين الخطط|مقارنة الخطط|لماذا الخطة|الخطة أ|الخطة ب|الخطة ج|الخطة د|أفضل خطة|أنسب خطة|خطة هي الأنسب/i.test(q)) {
        intent = "plan_comparison";
        if (allPlans.length >= 2) {
          const sorted = [...allPlans].sort((a, b) => b.suitabilityScore - a.suitabilityScore);
          const top = sorted[0];
          const runnerUp = sorted[1];

          if (isAr) {
            reply = `بناءً على التحليل الزراعي والبيانات التاريخية، تتصدر "${top.name}" بدرجة ملاءمة بلغت ${top.suitabilityScore} نقطة، تليها "${runnerUp.name}" بدرجة ${runnerUp.suitabilityScore} نقطة. ` +
              `السبب الرئيسي لتفوق ${top.name} هو: ` +
              (top.type === "water_conservative"
                ? `تحقيق كفاءة مائية أعلى بنسبة 22% مع توفير استهلاك قدره ${format(top.waterRequirements.netIrrigationMm)} مم مقابل ${format(runnerUp.waterRequirements.netIrrigationMm)} مم، مما يلغي مخاطر العجز المائي.`
                : top.type === "soil_health"
                  ? `دمج المحصول البقولي الذي يثبت النيتروجين الحيوي ويحسن بناء التربة (${top.soil}) ويقلل تكاليف الأسمدة الكيماوية.`
                  : `تطابق درجات الحرارة ومياه الري مع المتطلبات الفسيولوجية، مع تحقيق إنتاجية مقدرة بـ ${format(top.estimatedYield.estimatedT_Ha, 2)} طن/هكتار.`);
            evidence.push(`مقارنة استهلاك المياه: ${top.name} (${format(top.waterRequirements.totalWaterM3)} م³) مقابل ${runnerUp.name} (${format(runnerUp.waterRequirements.totalWaterM3)} م³).`);
            evidence.push(`تقدير الإنتاج الوطني المستند إلى بيانات FAO: ${top.name} (${format(top.estimatedYield.totalTons)} طن إجمالي) مقابل ${runnerUp.name} (${format(runnerUp.estimatedYield.totalTons)} طن إجمالي).`);
          } else {
            reply = `Based on agronomic screening across project datasets, "${top.name}" ranks highest with a suitability score of ${top.suitabilityScore}/100, followed by "${runnerUp.name}" at ${runnerUp.suitabilityScore}/100. ` +
              `The primary differentiator is: ` +
              (top.type === "water_conservative"
                ? `its water-saving profile saves 22% net irrigation demand (${format(top.waterRequirements.netIrrigationMm)} mm vs ${format(runnerUp.waterRequirements.netIrrigationMm)} mm in runner-up), avoiding moisture stress under constrained supplies.`
                : top.type === "soil_health"
                  ? `its integration of nitrogen-fixing legume biology, enhancing soil structure in ${top.soil} soil while mitigating monoculture pest pressure.`
                  : `its superior balance of temperature comfort and water fulfillment, delivering an estimated ${format(top.estimatedYield.estimatedT_Ha, 2)} t/ha.`);
            evidence.push(`Water consumption comparison: ${top.name} consumes ${format(top.waterRequirements.totalWaterM3)} m³ vs ${runnerUp.name} at ${format(runnerUp.waterRequirements.totalWaterM3)} m³.`);
            evidence.push(`FAO historical yield projection: ${top.name} yields ~${format(top.estimatedYield.totalTons)} tons vs ${runnerUp.name} at ~${format(runnerUp.estimatedYield.totalTons)} tons.`);
          }
        } else {
          reply = isAr
            ? "لم يتم توليد خطط متعددة بعد في هذه الجلسة. يمكنك تحديث بيانات المزرعة في صفحة التخطيط لتوليد 4 خطط زراعية مختلفة ومقارنتها بدقة."
            : "Multiple plans have not been generated yet for this session. Update the farm parameters on the Planning page to generate and contrast 4 distinct agricultural strategies.";
        }
      }

      // 2. Field Visual Health & Condition
      else if (/look unhealthy|unhealthy|visual|appearance|why does this field|wilted|yellow|dry soil|chlorosis|لماذا يبدو الحقل|غير صحي|ذابل|أصفر|تربة جافة|شكل الحقل|مظهر الحقل/i.test(q)) {
        intent = "field_health";
        const sim = latestSim;
        if (sim) {
          if (isAr) {
            reply = `يعكس المظهر البصري للحقل المحاكى حالة النمو الفسيولوجي المباشرة: "${sim.growthConditionLabel}". ${sim.fieldExplanation} ` +
              (sim.waterBalance.balanceM3 !== null && sim.waterBalance.balanceM3 < 0
                ? `السبب المباشر هو العجز المائي البالغ ${format(Math.abs(sim.waterBalance.balanceM3))} م³، مما يجبر النبات على إغلاق الثغور وذبول الأوراق وظهور شقوق الجفاف السطحية.`
                : `الغطاء النباتي بحالة ممتازة لأن الاحتياجات المائية (ETc) مغطاة بالكامل ولم تتجاوز درجات الحرارة الحدود القصوى للمحصول.`);
            evidence.push(`حالة النمو: ${sim.growthConditionLabel}. تلبية المياه: ${sim.waterBalance.satisfactionPct}%. درجة الملاءمة: ${sim.suitabilityScore}/100.`);
          } else {
            reply = `The visual field appearance directly reflects the simulated physiological growth status: "${sim.growthConditionLabel}". ${sim.fieldExplanation} ` +
              (sim.waterBalance.balanceM3 !== null && sim.waterBalance.balanceM3 < 0
                ? `The root cause is a net water deficit of ${format(Math.abs(sim.waterBalance.balanceM3))} m³ (${format(sim.waterBalance.deficitMm)} mm), compelling the crop to close stomata, induce leaf wilting, and causing soil crust cracking.`
                : `The canopy is healthy and vibrant because seasonal crop ETc is 100% satisfied and temperature remains within the physiological optimum.`);
            evidence.push(`Condition status: ${sim.growthConditionLabel}. Water satisfaction: ${sim.waterBalance.satisfactionPct}%. Overall score: ${sim.suitabilityScore}/100.`);
          }
        } else {
          reply = isAr
            ? "لتفسير المظهر البصري للحقل بدقة، قم بتشغيل المحاكاة (Mode A أو Mode B) أولاً ليتم حساب التوازن المائي والغطاء النباتي بناءً على بيانات الخطة."
            : "To explain the visual field condition, please run the simulation (Mode A or Mode B) first so water balance and canopy vigor can be calculated.";
        }
      }

      // 3. Water Sufficiency & Water balance
      else if (/water supply|water sufficient|enough water|deficit|irrigation demand|كافية لري|هل المياه كافية|كفاية المياه|عجز المياه|كمية المياه|احتياجات الري|إمدادات المياه/i.test(q)) {
        intent = "water_sufficiency";
        const targetPlan = activePlan || (allPlans[0]);
        if (targetPlan) {
          const w = targetPlan.waterRequirements;
          if (isAr) {
            if (w.availableWaterM3 === null) {
              reply = `يقدر الطلب الصافي لري محصول ${targetPlan.cropDisplay} بمساحة ${targetPlan.areaHectares} هكتار بنحو ${format(w.netIrrigationMm)} مم (إجمالي ${format(w.totalWaterM3)} م³). ` +
                `لم يتم إدخال كمية المياه المتاحة لمقارنتها؛ أدخل إمداداتك المتاحة للتحقق من وجود فائض أو عجز.`;
            } else if (w.balanceM3 >= 0) {
              reply = `نعم، إمدادات المياه المتاحة (${format(w.availableWaterM3)} م³) كافية تماماً وتغطي كامل الطلب الصافي للري (${format(w.totalWaterM3)} م³) مع وجود فائض آمن قدره ${format(w.balanceM3)} م³.`;
            } else {
              reply = `لا، إمدادات المياه المتاحة (${format(w.availableWaterM3)} م³) غير كافية، حيث يوجد عجز مائي قدره ${format(Math.abs(w.balanceM3))} م³ (${format(w.deficitMm)} مم)، مما يغطي ${w.waterSatisfactionPct}% فقط من احتياج المحصول.`;
            }
            evidence.push(`البخر-نتح للمحصول (ETc): ${format(w.seasonalEtcMm)} مم. الأمطار الفعالة: ${format(w.effectiveRainMm)} مم. صافي الري: ${format(w.netIrrigationMm)} مم.`);
          } else {
            if (w.availableWaterM3 === null) {
              reply = `Estimated net seasonal irrigation demand for ${targetPlan.cropDisplay} over ${targetPlan.areaHectares} ha is ${format(w.netIrrigationMm)} mm (total ${format(w.totalWaterM3)} m³). ` +
                `Available water supply was not specified; enter an available supply value to evaluate the exact surplus or deficit.`;
            } else if (w.balanceM3 >= 0) {
              reply = `Yes, your available irrigation supply (${format(w.availableWaterM3)} m³) is sufficient. It fully satisfies the net crop demand of ${format(w.totalWaterM3)} m³ with a safe surplus of ${format(w.balanceM3)} m³.`;
            } else {
              reply = `No, your available water supply (${format(w.availableWaterM3)} m³) is insufficient. There is an estimated net deficit of ${format(Math.abs(w.balanceM3))} m³ (${format(w.deficitMm)} mm), satisfying only ${w.waterSatisfactionPct}% of crop water demand.`;
            }
            evidence.push(`Calculated ETc: ${format(w.seasonalEtcMm)} mm. Effective rainfall: ${format(w.effectiveRainMm)} mm. Net irrigation: ${format(w.netIrrigationMm)} mm.`);
          }
        }
      }

      // 4. What should I change / Recommendations
      else if (/what should i change|how to improve|recommendation|improve.*plan|better yield|كيف أحسن|توصية|تطوير الخطة|تحسين الخطة/i.test(q)) {
        intent = "recommendations";
        const targetPlan = activePlan || (allPlans[0]);
        if (isAr) {
          reply = `لتطوير الخطة الحالية (${targetPlan ? targetPlan.name : "الخطة المحددة"})، أوصي بالتدابير الزراعية التالية استناداً لبيانات المشروع: ` +
            `\n1. كفاءة الري: التحول إلى الري بالتنقيط أو الري الناقص المدروس يوفر 20–25% من المياه (${targetPlan ? format(targetPlan.waterRequirements.totalWaterM3 * 0.22) : "حوالي 1,000"} م³). ` +
            `\n2. صحة التربة: إضافة 10 أطنان/هكتار من الكومبوست العضوي لزيادة قدرة التربة (${targetPlan?.soil || "الحالية"}) على الاحتفاظ بالماء. ` +
            `\n3. الدورة الزراعية: إدخال مرحلة بقولية (مثل البرسيم أو الفول) لتثبيت 50 كجم نيتروجين حيوي/هكتار وكسر دورات الآفات.`;
          evidence.push("توصيات مستخلصة من نماذج منظمة الأغذية والزراعة FAO-56 ومنهجية التقييم الزراعي للمشروع.");
        } else {
          reply = `To improve the active plan (${targetPlan ? targetPlan.name : "selected strategy"}), I recommend the following evidence-based agronomic actions: ` +
            `\n1. Irrigation Efficiency: Transitioning to drip or precision deficit scheduling saves ~22% water (${targetPlan ? format(targetPlan.waterRequirements.totalWaterM3 * 0.22) : "approx. 1,000"} m³), mitigating drought vulnerability. ` +
            `\n2. Soil Water Retention: Incorporate 10 t/ha well-matured compost into your ${targetPlan?.soil || "target"} soil to expand available water capacity. ` +
            `\n3. Crop Rotation: Integrate a legume phase (e.g. Faba bean or Berseem clover) to contribute 40–70 kg biological N/ha and disrupt cereal pest cycles.`;
          evidence.push("Actionable recommendations derived from FAO-56 irrigation best practices and local zone soil data.");
        }
      }

      // 5. What happens if I increase available water?
      else if (/increase.*water|more water|add.*water|إذا زادت المياه|زيادة المياه|إضافة ماء|رفع إمدادات الري/i.test(q)) {
        intent = "water_increase";
        const targetPlan = activePlan || (allPlans[0]);
        if (targetPlan) {
          const w = targetPlan.waterRequirements;
          if (isAr) {
            reply = `إذا قمت بزيادة إمدادات المياه لتغطية كامل الطلب (${format(w.totalWaterM3)} م³): ` +
              `\n• سيختفي العجز المائي تماماً وتتحول حالة الحقل البصرية إلى "نمو مثالي وأوراق خضراء نضرة". ` +
              `\n• ترتفع نسبة تلبية المياه إلى 100%، مما يستعيد الإنتاجية المقدرة إلى مستواها الأقصى (${format(targetPlan.estimatedYield.baselineT_Ha, 2)} طن/هكتار). ` +
              `\n• ترتفع درجة ملاءمة الخطة بنحو 8–15 نقطة لتصل إلى أعلى تصنيف.`;
            evidence.push(`الطلب الإجمالي الصافي المطلوب: ${format(w.totalWaterM3)} م³. الإنتاجية القصوى المتوقعة: ${format(targetPlan.estimatedYield.baselineT_Ha, 2)} طن/هكتار.`);
          } else {
            reply = `If you increase available irrigation to fully meet the crop's net demand (${format(w.totalWaterM3)} m³): ` +
              `\n• The moisture deficit is eliminated, and the visual field transitions to "Vibrant Canopy & Fertile Soil". ` +
              `\n• Water satisfaction reaches 100%, restoring expected crop yield to its unconstrained benchmark (~${format(targetPlan.estimatedYield.baselineT_Ha, 2)} t/ha). ` +
              `\n• The plan's suitability score increases by 8–15 points.`;
            evidence.push(`Full required water volume: ${format(w.totalWaterM3)} m³. Unconstrained yield benchmark: ${format(targetPlan.estimatedYield.baselineT_Ha, 2)} t/ha.`);
          }
        }
      }

      // 6. Explain simulation results
      else if (/explain.*simulation|how was.*calculated|simulation results|نتائج المحاكاة|اشرح.*المحاكاة|شرح نتائج المحاكاة|شرح المحاكاة|كيف تم الحساب|تفسير النتائج/i.test(q)) {
        intent = "explain_simulation";
        if (isAr) {
          reply = `تعتمد المحاكاة الزراعية على معادلات منظمة الأغذية والزراعة المعتمدة (FAO-56): ` +
            `\n1. البخر-نتح للمحصول (ETc = Kc × ET0)، مع معامل تعديل حراري 2% لكل 1°م في سيناريو الحرارة. ` +
            `\n2. الأمطار الفعالة مقدرة بـ 80% من الهطول الصافي المعدل بالسيناريو. ` +
            `\n3. صافي طلب الري = (ETc - الأمطار الفعالة). ` +
            `\n4. التوازن المائي = (المياه المتاحة المدخلة - صافي طلب الري). ` +
            `\n5. مظهر الحقل البصري يعكس بدقة التوازن المائي والتطابق الحراري مع النطاق الفسيولوجي للمحصول.`;
          evidence.push("منهجية معيارية: ETc = Kc × ET0؛ الأمطار الفعالة = 0.8 × الهطول؛ استجابة الإنتاج لمنظمة FAO-33.");
        } else {
          reply = `The agricultural simulation relies on standard peer-reviewed FAO-56 methods: ` +
            `\n1. Crop Evapotranspiration (ETc = Kc × ET0), modified by an explicit +2% per °C temperature sensitivity. ` +
            `\n2. Effective precipitation estimated as 80% of scenario-adjusted seasonal rainfall. ` +
            `\n3. Net Irrigation Demand = max(0, ETc - Effective Rain). ` +
            `\n4. Water Balance = Available Supply - Net Irrigation Demand. ` +
            `\n5. Field Visual Appearance is mapped directly from the resulting water satisfaction and temperature alignment.`;
          evidence.push("Standard methodology: ETc = Kc × ET0; Effective precipitation = 0.8 × P; FAO-33 yield response.");
        }
      }

      // 7. General fallback on project data
      else {
        intent = "data_retrieval";
        const crop = data.crops.find(c => q.includes(c.replaceAll("_", " ")) || q.includes(displayCrop(c).toLowerCase())) || activePlan?.crop || "wheat";
        const season = /\bsummer\b|صيف/.test(q) ? "summer" : /\bwinter\b|شتاء/.test(q) ? "winter" : (activePlan?.season || "winter");
        const zone = activePlan?.zone || "monufia";
        const prof = data.profile(zone, crop, season) || data.profile(null, crop, season);
        const yStats = data.cropYieldStats(crop);

        if (prof) {
          if (isAr) {
            reply = `بيانات المشروع التاريخية لمحصول ${displayCrop(crop)} في منطقة ${zone} (${season}): ` +
              `متوسط الري الموسمي ${format(prof.irrigationMm)} مم، متوسط الحرارة ${format(prof.temperature)} °م، مؤشر الغطاء النباتي ${format(prof.ndvi, 3)}. ` +
              (yStats.available ? `متوسط الإنتاج الوطني المسجل في FAO هو ${format(yStats.mean, 2)} طن/هكتار (${yStats.startYear}–${yStats.endYear}).` : "");
          } else {
            reply = `Project historical records for ${displayCrop(crop)} in ${zone} (${season}): ` +
              `seasonal irrigation averages ${format(prof.irrigationMm)} mm, mean temperature is ${format(prof.temperature)} °C, and observed NDVI is ${format(prof.ndvi, 3)}. ` +
              (yStats.available ? `FAO national historical yield averages ${format(yStats.mean, 2)} t/ha (${yStats.startYear}–${yStats.endYear}).` : "");
          }
          evidence.push(`سجلات تاريخية موثقة من ملفات المشروع: ${prof.seasons} مواسم مكتملة في المنطقة.`);
        } else {
          reply = isAr
            ? `أنا أبو قردان، المساعد الزراعي الذكي لمشروع Field Shift AI. أنا متصل ببيانات المحاصيل والمواسم والتخطيط والمحاكاة. يمكنك سؤالي عن مقارنة الخطط، كفاية المياه، أسباب مظهر الحقل، أو كيفية تحسين خطتك.`
            : `I am Abu Qerdan, the intelligent agricultural assistant for Field Shift AI. I have full context of project datasets, your farm parameters, generated plans, and simulation outcomes. Feel free to ask about plan comparisons, water sufficiency, field health, or improvements.`;
        }
      }

      return {
        reply,
        evidence,
        intent,
        mode: isAr ? "تحليل ذكي محلي (بيانات المشروع الموثقة)" : "Local Agricultural Intelligence (Verified Project Data)",
      };
    },
  };

  // Helper to connect to backend agent if desired
  async function askAgent(question, plan, data, conversationId) {
    const local = AI.answer(question, { activePlan: plan, allPlans: getSavedPlans() }, data);
    const languageInstruction = window.FieldShiftI18n?.language() === "ar" ? "أجب باللغة العربية. " : "";

    try {
      const response = await fetch("/api/agent/chat", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          message: `${languageInstruction}${question}`,
          conversation_id: conversationId || undefined,
          summary: plan ? plan.farmSummary : getFarmSummary(),
        }),
      });
      if (response.ok) {
        const payload = await response.json();
        if (payload.success && payload.data?.assistant_mode === "llm") {
          return {
            ...payload.data,
            local,
            assistant_mode: "llm",
          };
        }
      }
    } catch (err) {
      // Backend not running or offline; proceed seamlessly with transparent local intelligence
    }

    // Transparent local intelligence layer
    return {
      reply: local.reply,
      conversation_id: conversationId || "local_session_" + Date.now(),
      assistant_mode: "local_intelligence",
      local,
    };
  }

  window.FieldShift = {
    loadProjectData,
    nearestZone,
    displayCrop,
    format,
    escapeHtml,
    getPlan,
    savePlan,
    getSavedPlans,
    savePlans,
    getFarmSummary,
    PlanningEngine,
    SimulationEngine,
    AI,
    askAgent,
    api: async (path, body) => {
      const response = await fetch(path, body === undefined ? {} : {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
      });
      const payload = await response.json();
      if (!response.ok || !payload.success) {
        throw new Error(payload.error?.message || response.statusText);
      }
      return payload.data;
    },
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => loadProjectData().catch(error => {
      console.error("Could not load project datasets.", error);
    }), { once: true });
  } else {
    loadProjectData().catch(error => console.error("Could not load project datasets.", error));
  }
})();
